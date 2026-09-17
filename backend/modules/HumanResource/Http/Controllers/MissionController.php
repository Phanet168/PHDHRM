<?php

namespace Modules\HumanResource\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\Mission;
use Modules\HumanResource\Entities\MissionDestination;
use Modules\HumanResource\Entities\MissionDocument;
use Modules\HumanResource\Entities\MissionType;
use Modules\HumanResource\Entities\TransportType;
use Modules\HumanResource\Http\Resources\MissionResource;
use Modules\HumanResource\Enums\Mission\DestinationScope;
use Modules\HumanResource\Enums\Mission\MissionCreationPath;
use Modules\HumanResource\Enums\Mission\MissionCreationSource;
use Modules\HumanResource\Enums\Mission\MissionStatus;
use Modules\HumanResource\Support\MissionAccess;
use Modules\HumanResource\Support\MissionWorkflowService;

class MissionController extends Controller
{
    /** Maps mission_types.code (admin-manageable master list) to the legacy Mission::TYPES key. */
    private const TYPE_CODE_TO_LEGACY = [
        'inspection' => 'inspection',
        'community_visit' => 'community_visit',
        'provincial_assignment' => 'provincial_assignment',
        'training' => 'training',
        'meeting' => 'meeting',
        'other_mission' => 'other',
    ];

    public function __construct(
        private readonly MissionAccess $access,
        private readonly MissionWorkflowService $workflow,
    ) {
    }

    private function isApi(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    private function ok(mixed $data, int $status = 200)
    {
        return response()->json(['response' => ['status' => 'ok', 'data' => $data]], $status);
    }

    private function load(Mission $mission): Mission
    {
        return $mission->load(['assigner:id,full_name', 'assignments.employee:id,first_name,last_name,employee_id', 'documents', 'reports']);
    }

    private function result(Request $request, Mission $mission, int $status = 200)
    {
        if ($this->isApi($request)) {
            return $this->ok((new MissionResource($this->load($mission)))->resolve($request), $status);
        }

        return redirect()->route('missions.show', $mission->id)->with('success', 'បានរក្សាទុកបេសកកម្មដោយជោគជ័យ។');
    }

    public function index(Request $request): mixed
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'mission_type' => ['nullable', Rule::in(array_keys(Mission::TYPES))],
            'status' => ['nullable', Rule::in(['draft', 'pending', 'approved', 'rejected', 'cancelled', 'in_progress', 'completed'])],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from_date'), 'after_or_equal:from_date')],
            'per_page' => ['nullable', 'integer', 'between:1,100'], 'page' => ['nullable', 'integer', 'min:1'],
            'scope' => ['nullable', Rule::in(['mine', 'managed'])],
        ]);
        $managed = ! $this->isApi($request) || ($filters['scope'] ?? 'mine') === 'managed';
        if ($managed) {
            abort_unless($this->access->can('read_mission'), 403);
        }
        $query = $managed ? $this->access->managed() : $this->access->visible(true);
        $query->when($filters['mission_type'] ?? null, fn ($q, $type) => $q->where('mission_type', $type));
        if (! empty($filters['q'])) {
            $query->where(fn ($q) => $q->where('title', 'like', '%'.$filters['q'].'%')->orWhere('destination', 'like', '%'.$filters['q'].'%'));
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('status', in_array($status, ['in_progress', 'completed']) ? 'approved' : $status);
            if ($status === 'in_progress') {
                $query->whereNotNull('started_at')->whereNull('completed_at');
            } elseif ($status === 'completed') {
                $query->whereNotNull('completed_at');
            }
        }
        $query->when($filters['from_date'] ?? null, fn ($q, $date) => $q->whereDate('end_date', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('start_date', '<=', $date));
        $missions = $query->with(['assigner:id,full_name', 'assignments.employee:id,first_name,last_name,employee_id', 'documents'])
            ->withCount('assignments')->orderByDesc('id')->paginate($filters['per_page'] ?? 20)->withQueryString();
        if ($this->isApi($request)) {
            return $this->ok([
                'data' => $missions->getCollection()->map(fn ($m) => (new MissionResource($m))->resolve($request)),
                'current_page' => $missions->currentPage(), 'per_page' => $missions->perPage(),
                'last_page' => $missions->lastPage(), 'total' => $missions->total(),
            ]);
        }
        $employees = $this->access->employees()->where('is_active', 1)->orderBy('last_name')->orderBy('first_name')->get();

        return view('humanresource::attendance.missions.index', compact('missions', 'employees'));
    }

    public function employees(Request $request)
    {
        abort_unless($this->access->can('create_mission'), 403);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $rows = $this->access->employees()->where('is_active', 1)
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('first_name', 'like', '%'.$term.'%')
                ->orWhere('last_name', 'like', '%'.$term.'%')->orWhere('employee_id', 'like', '%'.$term.'%')))
            ->orderBy('last_name')->orderBy('id')->paginate(30, ['id', 'first_name', 'last_name', 'employee_id']);
        $rows->setCollection($rows->getCollection()->map(fn ($e) => ['id' => $e->id, 'name' => $e->full_name, 'employee_id' => $e->employee_id]));

        return $this->ok($rows);
    }

    public function types()
    {
        return $this->ok(collect(Mission::TYPES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values());
    }

    /** Converts a solar (Gregorian) date to its Khmer lunar-calendar text, for auto-filling order_details.lunar_date. */
    public function lunarDate(Request $request): mixed
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        $text = '';
        try {
            $khmerDate = new \PPhatDev\LunarDate\KhmerDate($data['date']);
            $text = trim((string) $khmerDate->toLunarDate());
            $text = preg_replace('/^\s*ត្រូវនឹង\s*/u', '', $text) ?: $text;
        } catch (\Throwable $e) {
            // Leave the field blank if conversion fails; the user can still type it manually.
        }

        return $this->ok(['lunar_date' => $text]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'destination' => ['required', 'string', 'max:255'],
            'mission_type' => ['sometimes', Rule::in(array_keys(Mission::TYPES))],
            'order_number' => ['nullable', 'string', 'max:100'],
            'order_details' => ['sometimes', 'array:issuing_authority,issuing_department,issue_place,issued_on,lunar_date,reference,transport,funding_source,signatory_title,signatory_name,preferred_office_head'],
            'order_details.issuing_authority' => ['nullable', 'string', 'max:255'],
            'order_details.issuing_department' => ['nullable', 'string', 'max:255'],
            'order_details.issue_place' => ['nullable', 'string', 'max:100'],
            'order_details.issued_on' => ['nullable', 'date_format:Y-m-d'],
            'order_details.lunar_date' => ['nullable', 'string', 'max:255'],
            'order_details.reference' => ['nullable', 'string', 'max:2000'],
            'order_details.transport' => ['nullable', 'string', 'max:255'],
            'order_details.funding_source' => ['nullable', 'string', 'max:500'],
            'order_details.signatory_title' => ['nullable', 'string', 'max:255'],
            'order_details.signatory_name' => ['nullable', 'string', 'max:255'],
            'order_details.preferred_office_head' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date_format:Y-m-d'], 'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'purpose' => ['nullable', 'string', 'max:10000'], 'status' => ['sometimes', Rule::in(['draft', 'pending'])],
            'employee_ids' => ['required', 'array', 'min:1', 'max:100'], 'employee_ids.*' => ['required', 'integer', 'distinct'],
            // Phase 3 fields — optional so legacy callers/tests keep working unchanged.
            'mission_type_id' => ['sometimes', 'nullable', 'integer', 'exists:mission_types,id'],
            'funding_source_id' => ['sometimes', 'nullable', 'integer', 'exists:funding_sources,id'],
            'sponsor_name' => ['nullable', 'string', 'max:255'],
            'transport_type_id' => ['sometimes', 'nullable', 'integer', 'exists:transport_types,id'],
            'transport_description' => ['nullable', 'string', 'max:255'],
            'destination_scope' => ['sometimes', 'nullable', Rule::in(['within_province', 'outside_province'])],
            'destination_province_code' => ['sometimes', 'nullable', 'string', 'max:2'],
            'destination_district_code' => ['nullable', 'string', 'max:4'],
            'destination_commune_code' => ['nullable', 'string', 'max:6'],
            'destination_village_code' => ['nullable', 'string', 'max:8'],
            'destination_venue_name' => ['nullable', 'string', 'max:255'],
            'destination_address' => ['nullable', 'string', 'max:1000'],
            'reference_document' => ['sometimes', 'nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
            'reference_number' => ['nullable', 'string', 'max:150'],
            'reference_issuer' => ['nullable', 'string', 'max:255'],
            'reference_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $ids = array_map('intval', $data['employee_ids']);
        abort_unless($this->access->employees()->where('is_active', 1)->whereIn('id', $ids)->count() === count($ids), 403, 'Team members must be active employees in your managed units.');

        return $data;
    }

    /** Resolves both mission_type_id (new master table) and the legacy mission_type string together. */
    private function resolveMissionType(array $data): array
    {
        if (empty($data['mission_type_id'])) {
            return ['mission_type_id' => null, 'mission_type' => $data['mission_type'] ?? 'other'];
        }

        $type = MissionType::find($data['mission_type_id']);
        $legacy = $type ? (self::TYPE_CODE_TO_LEGACY[$type->code] ?? 'other') : ($data['mission_type'] ?? 'other');

        return ['mission_type_id' => $data['mission_type_id'], 'mission_type' => $legacy];
    }

    /** Persists the optional structured destination alongside the free-text destination column. */
    private function syncDestination(Mission $mission, array $data): void
    {
        if (empty($data['destination_province_code'])) {
            return;
        }

        $mission->destinations()->delete();
        $mission->destinations()->create([
            'scope' => $data['destination_scope'] ?? DestinationScope::WithinProvince->value,
            'destination_type' => 'venue',
            'province_code' => $data['destination_province_code'],
            'district_code' => $data['destination_district_code'] ?? null,
            'commune_code' => $data['destination_commune_code'] ?? null,
            'village_code' => $data['destination_village_code'] ?? null,
            'venue_name' => $data['destination_venue_name'] ?? null,
            'address' => $data['destination_address'] ?? null,
            'sort_order' => 0,
        ]);
    }

    /**
     * Attaches an optional reference letter. The mission_documents table requires either a stored
     * file or a linked correspondence letter (DB constraint mission_document_source_check); this
     * pass only supports the file-backed form, so reference_number/issuer/date without a file are
     * not persisted (the create forms note that a file is required to record those details).
     */
    private function syncReference(Request $request, Mission $mission, array $data): void
    {
        if (!$request->hasFile('reference_document')) {
            return;
        }

        $file = $request->file('reference_document');
        $path = $file->store('missions/'.$mission->id, 'local');

        $mission->documents()->create([
            'name' => Str::limit(basename($file->getClientOriginalName()), 250, ''),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => Auth::id(),
            'document_kind' => 'reference',
            'reference_number' => $data['reference_number'] ?? null,
            'reference_date' => $data['reference_date'] ?? null,
            'reference_issuer' => $data['reference_issuer'] ?? null,
        ]);
    }

    private function syncTeam(Mission $mission, array $ids): void
    {
        $mission->assignments()->whereNotIn('employee_id', $ids)->delete();
        $employees = Employee::with('position')->whereIn('id', $ids)->get()->keyBy('id');
        foreach (array_values($ids) as $index => $id) {
            $assignment = $mission->assignments()->firstOrNew(['employee_id' => $id]);
            $employee = $employees->get($id);
            $assignment->fill(['status' => 'active', 'sort_order' => $index]);
            // Freeze the officer identity/title used by this order when first assigned.
            if ($assignment->name_on_order === null) {
                $assignment->name_on_order = $employee->full_name;
                $assignment->position_on_order = $employee->position?->position_name_km ?: $employee->position?->position_name;
            }
            $assignment->save();
        }
    }

    public function store(Request $request): mixed
    {
        abort_unless($this->access->can('create_mission'), 403);
        $data = $this->validated($request);
        $mission = DB::transaction(function () use ($data) {
            $mission = Mission::create(collect($data)->except('employee_ids')->all() + [
                'uuid' => (string) Str::uuid(), 'requested_by' => auth()->id(), 'status' => 'pending',
            ]);
            $this->syncTeam($mission, $data['employee_ids']);

            return $mission;
        });

        return $this->result($request, $mission, 201);
    }

    /** Flow A: GET the "ស្នើសុំបេសកកម្ម" request form. */
    public function requestCreate(Request $request)
    {
        abort_unless($this->access->can('create_mission'), 403);
        $employees = $this->access->employees()->where('is_active', 1)->orderBy('last_name')->get();
        $missionTypes = MissionType::query()->where('is_active', 1)->orderBy('sort_order')->get();
        $transportTypes = TransportType::query()->where('is_active', 1)->orderBy('sort_order')->get();
        $fundingSources = \Modules\Planning\Entities\FundingSource::query()->orderBy('id')->get();
        $requesterEmployeeId = $this->access->employeeId();
        $requesterEmployee = $requesterEmployeeId
            ? Employee::with('position')->find($requesterEmployeeId)
            : null;
        $approvalRoute = $this->workflow->previewApprovalRoute($requesterEmployeeId);

        return view('humanresource::attendance.missions.create', compact(
            'employees', 'missionTypes', 'transportTypes', 'fundingSources', 'requesterEmployee', 'approvalRoute'
        ));
    }

    /** Flow A: POST the employee's own mission request; starts the office-head/director workflow. */
    public function requestStore(Request $request): mixed
    {
        abort_unless($this->access->can('create_mission'), 403);
        $data = $this->validated($request);
        $type = $this->resolveMissionType($data);

        $mission = DB::transaction(function () use ($request, $data, $type) {
            $mission = Mission::create(collect($data)
                ->only(['title', 'destination', 'order_number', 'order_details', 'start_date', 'end_date', 'purpose', 'funding_source_id', 'sponsor_name', 'transport_type_id', 'transport_description'])
                ->all() + [
                    'uuid' => (string) Str::uuid(),
                    'requested_by' => Auth::id(),
                    'requester_employee_id' => $this->access->employeeId(),
                    'creation_path' => MissionCreationPath::EmployeeRequest,
                    'creation_source' => MissionCreationSource::EmployeeRequest,
                    'status' => 'pending',
                    'mission_type' => $type['mission_type'],
                    'mission_type_id' => $type['mission_type_id'],
                ]);
            $this->syncTeam($mission, $data['employee_ids']);
            $this->syncDestination($mission, $data);
            $this->syncReference($request, $mission, $data);
            $this->workflow->initialize($mission, Auth::id());

            return $mission;
        });

        return $this->result($request, $mission, 201);
    }

    /** Flow B: GET the Letter Manager's "បង្កើតបេសកកម្មដោយផ្ទាល់" direct-creation form. */
    public function directCreate(Request $request)
    {
        abort_unless($this->access->can('manage_mission_order'), 403);
        $employees = $this->access->employees()->where('is_active', 1)->orderBy('last_name')->get();
        $missionTypes = MissionType::query()->where('is_active', 1)->orderBy('sort_order')->get();
        $transportTypes = TransportType::query()->where('is_active', 1)->orderBy('sort_order')->get();
        $fundingSources = \Modules\Planning\Entities\FundingSource::query()->orderBy('id')->get();

        return view('humanresource::attendance.missions.direct-create', compact('employees', 'missionTypes', 'transportTypes', 'fundingSources'));
    }

    /** Flow B: POST a mission created directly by the Letter Manager; skips the approval workflow. */
    public function directStore(Request $request): mixed
    {
        abort_unless($this->access->can('manage_mission_order'), 403);
        $data = $this->validated($request);
        $data['creation_source'] = $request->validate([
            'creation_source' => ['sometimes', Rule::in(['invitation_letter', 'director_instruction', 'administration_direct', 'other'])],
        ])['creation_source'] ?? 'administration_direct';
        $type = $this->resolveMissionType($data);

        $mission = DB::transaction(function () use ($request, $data, $type) {
            $mission = Mission::create(collect($data)
                ->only(['title', 'destination', 'order_number', 'order_details', 'start_date', 'end_date', 'purpose', 'funding_source_id', 'sponsor_name', 'transport_type_id', 'transport_description'])
                ->all() + [
                    'uuid' => (string) Str::uuid(),
                    'requested_by' => Auth::id(),
                    'creation_path' => MissionCreationPath::Direct,
                    'creation_source' => MissionCreationSource::from($data['creation_source']),
                    'status' => 'approved',
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                    'mission_type' => $type['mission_type'],
                    'mission_type_id' => $type['mission_type_id'],
                ]);
            // lifecycle_status is intentionally excluded from mass assignment; set it explicitly.
            $mission->forceFill(['lifecycle_status' => MissionStatus::PendingMissionOrder])->save();
            $this->syncTeam($mission, $data['employee_ids']);
            $this->syncDestination($mission, $data);
            $this->syncReference($request, $mission, $data);
            $this->workflow->recordHistory($mission, null, MissionStatus::PendingMissionOrder, 'submit', Auth::id(), null);

            return $mission;
        });

        return $this->result($request, $mission, 201);
    }

    public function show(Request $request, int $id): mixed
    {
        $mission = $this->load($this->access->visible()->findOrFail($id));
        if ($this->isApi($request)) {
            return $this->ok((new MissionResource($mission))->resolve($request));
        }
        $canManage = $this->access->can('update_mission') && $this->access->managed()->whereKey($id)->exists();
        $canDelete = $this->access->can('delete_mission') && $this->access->managed()->whereKey($id)->exists();
        $canReview = $this->access->can('approve_mission') && $this->access->managed()->whereKey($id)->exists();
        $canManageOrder = $this->access->can('manage_mission_order');
        $canDecide = ! $mission->isLegacyFlow() && $this->workflow->canAct(Auth::user(), $mission);
        $employees = $canManage ? $this->access->employees()->where('is_active', 1)->orderBy('last_name')->get() : collect();
        $detail = (new MissionResource($mission))->resolve($request);
        $mission->load(['statusHistories.actor:id,full_name', 'destinations', 'requesterEmployee.position']);
        $pendingStatuses = [MissionStatus::PendingOfficeHead->value, MissionStatus::PendingDirector->value];
        $currentApprovers = (! $mission->isLegacyFlow() && $mission->lifecycle_status && in_array($mission->lifecycle_status->value, $pendingStatuses, true))
            ? $this->workflow->currentStepApproverNames($mission)
            : [];

        return view('humanresource::attendance.missions.show', compact('mission', 'employees', 'canManage', 'canReview', 'canDelete', 'canManageOrder', 'canDecide', 'detail', 'currentApprovers'));
    }

    public function update(Request $request, int $id): mixed
    {
        $mission = DB::transaction(function () use ($request, $id) {
            $mission = Mission::lockForUpdate()->findOrFail($id);
            $this->access->manage($mission);
            abort_unless(in_array($mission->status, ['draft', 'pending']), 409, 'Only drafts and pending missions can be edited.');
            $data = $this->validated($request);
            $mission->update(collect($data)->except('employee_ids')->all());
            $this->syncTeam($mission, $data['employee_ids']);

            return $mission;
        });

        return $this->result($request, $mission);
    }

    public function order(Request $request, int $id)
    {
        $mission = $this->access->visible()->with('assignments.employee.position')->findOrFail($id);
        $details = $mission->order_details ?? [];

        return response()->view('humanresource::attendance.missions.order', compact('mission', 'details'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function review(Request $request, int $id): mixed
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])],
            'reason' => ['required_if:decision,rejected', 'nullable', 'string', 'max:1000']]);
        $mission = DB::transaction(function () use ($id, $data) {
            $mission = Mission::lockForUpdate()->findOrFail($id);
            $this->access->manage($mission, 'approve_mission');
            abort_unless($mission->status === 'pending', 409, 'Only pending missions can be reviewed.');
            if ($data['decision'] === 'approved') {
                abort_unless($mission->assignments()->where('status', 'active')->exists(), 422, 'Assign a team before approval.');
                $ids = $mission->assignments()->where('status', 'active')->pluck('employee_id');
                Employee::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
                $overlap = Mission::where('status', 'approved')->whereKeyNot($id)
                    ->whereDate('start_date', '<=', $mission->end_date)->whereDate('end_date', '>=', $mission->start_date)
                    ->whereHas('assignments', fn ($q) => $q->where('status', 'active')->whereIn('employee_id', $ids))->exists();
                abort_if($overlap, 409, 'A team member already has an approved mission during these dates.');
            }
            $mission->update(['status' => $data['decision'],
                'approved_by' => $data['decision'] === 'approved' ? auth()->id() : null,
                'approved_at' => $data['decision'] === 'approved' ? now() : null,
                'rejected_reason' => $data['decision'] === 'rejected' ? $data['reason'] : null]);

            return $mission;
        });

        return $this->result($request, $mission);
    }

    public function cancel(Request $request, int $id): mixed
    {
        $mission = DB::transaction(function () use ($id) {
            $mission = Mission::lockForUpdate()->findOrFail($id);
            $this->access->manage($mission);
            abort_unless(in_array($mission->status, ['draft', 'pending', 'approved']) && ! $mission->completed_at, 409, 'This mission cannot be cancelled.');
            $from = $mission->lifecycle_status;
            $update = ['status' => 'cancelled'];
            if (! $mission->isLegacyFlow()) {
                $update['lifecycle_status'] = MissionStatus::Cancelled;
            }
            $mission->forceFill($update)->save();
            $mission->assignments()->update(['status' => 'cancelled']);
            if (! $mission->isLegacyFlow()) {
                $this->workflow->recordHistory($mission, $from, MissionStatus::Cancelled, 'cancel', auth()->id(), null);
            }

            return $mission;
        });

        return $this->result($request, $mission);
    }

    public function start(Request $request, int $id): mixed
    {
        $mission = DB::transaction(function () use ($id) {
            $mission = $this->access->visible()->lockForUpdate()->findOrFail($id);
            abort_unless($this->access->participant($mission), 403);
            abort_unless($mission->status === 'approved' && ! $mission->completed_at, 409, 'Only approved, unfinished missions can start.');
            abort_unless($mission->isLegacyFlow() || $mission->lifecycle_status === MissionStatus::Issued, 409, 'The mission order must be issued before the mission can start.');
            if (! $mission->started_at) {
                abort_unless(now()->toDateString() >= $mission->start_date->toDateString() && now()->toDateString() <= $mission->end_date->toDateString(), 409, 'Start within the mission date range.');
                $from = $mission->lifecycle_status;
                $update = ['started_at' => now(), 'started_by' => auth()->id()];
                if (! $mission->isLegacyFlow()) {
                    $update['lifecycle_status'] = MissionStatus::OnMission;
                }
                $mission->forceFill($update)->save();
                if (! $mission->isLegacyFlow()) {
                    $this->workflow->recordHistory($mission, $from, MissionStatus::OnMission, 'start', auth()->id(), null);
                }
            }

            return $mission;
        });

        return $this->result($request, $mission);
    }

    public function report(Request $request, int $id): mixed
    {
        $data = $request->validate(['summary' => ['required', 'string', 'max:20000']]);
        $mission = DB::transaction(function () use ($id, $data) {
            $mission = $this->access->visible()->lockForUpdate()->findOrFail($id);
            abort_unless($this->access->participant($mission), 403);
            abort_unless($mission->status === 'approved' && $mission->started_at && ! $mission->completed_at, 409, 'Start the mission before reporting; completed missions are read only.');
            // This existing endpoint is an explicit report submission, not a draft save.
            $report = $mission->reports()->firstOrNew(['employee_id' => $this->access->employeeId()]);
            $report->summary = $data['summary'];
            $report->submitted_by = auth()->id();
            $report->submitted_at = now();
            $report->revision = $report->exists ? ((int) $report->revision + 1) : 1;
            $report->save();

            if (! $mission->isLegacyFlow() && $mission->lifecycle_status !== MissionStatus::PendingReport) {
                $from = $mission->lifecycle_status;
                $mission->forceFill(['lifecycle_status' => MissionStatus::PendingReport])->save();
                $this->workflow->recordHistory($mission, $from, MissionStatus::PendingReport, 'report', auth()->id(), null);
            }

            return $mission;
        });

        return $this->result($request, $mission);
    }

    public function complete(Request $request, int $id): mixed
    {
        $mission = DB::transaction(function () use ($id) {
            $mission = Mission::lockForUpdate()->findOrFail($id);
            $this->access->manage($mission);
            abort_unless($mission->status === 'approved' && $mission->started_at && ! $mission->completed_at && $mission->hasSubmittedReport(), 409, 'An ongoing mission needs a formally submitted report before completion.');
            $from = $mission->lifecycle_status;
            $update = ['completed_at' => now(), 'completed_by' => auth()->id()];
            if (! $mission->isLegacyFlow()) {
                $update['lifecycle_status'] = MissionStatus::Completed;
            }
            $mission->forceFill($update)->save();
            if (! $mission->isLegacyFlow()) {
                $this->workflow->recordHistory($mission, $from, MissionStatus::Completed, 'complete', auth()->id(), null);
            }

            return $mission;
        });

        return $this->result($request, $mission);
    }

    public function upload(Request $request, int $id): mixed
    {
        $request->validate(['document' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240']]);
        $path = null;
        try {
            $mission = DB::transaction(function () use ($request, $id, &$path) {
                $mission = Mission::lockForUpdate()->findOrFail($id);
                $this->access->manage($mission);
                abort_unless(in_array($mission->status, ['draft', 'pending', 'approved']) && ! $mission->completed_at, 409);
                $file = $request->file('document');
                $path = $file->store('missions/'.$id, 'local');
                abort_unless($path, 500, 'Unable to store document.');
                $mission->documents()->create(['name' => Str::limit(basename($file->getClientOriginalName()), 250, ''),
                    'path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'uploaded_by' => auth()->id()]);

                return $mission;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }

        return $this->result($request, $mission, 201);
    }

    public function download(Request $request, int $id, int $document)
    {
        $mission = $this->access->visible()->findOrFail($id);

        return $this->fileResponse($mission->documents()->findOrFail($document));
    }

    public function signedUrl(Request $request, int $id, int $document)
    {
        $this->access->visible()->findOrFail($id)->documents()->findOrFail($document);

        return $this->ok(['url' => URL::temporarySignedRoute('api.missions.documents.signed', now()->addMinutes(2), compact('id', 'document'))]);
    }

    public function signedDownload(Request $request, int $id, int $document)
    {
        return $this->fileResponse(Mission::findOrFail($id)->documents()->findOrFail($document));
    }

    private function fileResponse(MissionDocument $document)
    {
        abort_unless($document->path && Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    private const QUEUE_DEFS = [
        'mine' => ['title' => 'សំណើរបស់ខ្ញុំ', 'permission' => 'create_mission'],
        'office-head' => ['title' => 'សំណើរង់ចាំប្រធានការិយាល័យ', 'permission' => null, 'decide' => 'endorse'],
        'director' => ['title' => 'សំណើរង់ចាំប្រធានមន្ទីរ', 'permission' => null, 'decide' => 'approve'],
        'approved' => ['title' => 'សំណើដែលបានអនុម័ត', 'permission' => 'read_mission'],
        'on-mission' => ['title' => 'កំពុងបេសកកម្ម', 'permission' => 'read_mission'],
        'pending-report' => ['title' => 'រង់ចាំរបាយការណ៍', 'permission' => 'read_mission'],
        'history' => ['title' => 'ប្រវត្តិបេសកកម្ម', 'permission' => 'read_mission'],
    ];

    public function dashboard(Request $request)
    {
        abort_unless($this->access->can('read_mission') || $this->access->can('create_mission'), 403);

        $counts = [
            'mine' => (clone $this->access->myRequests())->count(),
            'office-head' => (clone $this->access->officeHeadQueue())->count(),
            'director' => (clone $this->access->directorQueue())->count(),
            'approved' => (clone $this->access->approvedQueue())->count(),
            'on-mission' => Mission::query()->whereIn('lifecycle_status', [MissionStatus::Issued->value, MissionStatus::OnMission->value])->count(),
            'pending-report' => Mission::query()->whereNotNull('started_at')->whereNull('completed_at')
                ->whereDoesntHave('reports', fn ($q) => $q->submitted())->count(),
        ];
        $recent = $this->access->visible()->with(['assigner:id,full_name'])->orderByDesc('id')->limit(8)->get();
        $canCreateRequest = $this->access->can('create_mission');
        $canCreateDirect = $this->access->can('manage_mission_order');

        return view('humanresource::attendance.missions.dashboard', compact('counts', 'recent', 'canCreateRequest', 'canCreateDirect'));
    }

    public function queue(Request $request, string $key): mixed
    {
        abort_unless(array_key_exists($key, self::QUEUE_DEFS), 404);
        $def = self::QUEUE_DEFS[$key];
        if ($def['permission']) {
            abort_unless($this->access->can($def['permission']), 403);
        }

        $query = match ($key) {
            'mine' => $this->access->myRequests(),
            'office-head' => $this->access->officeHeadQueue(),
            'director' => $this->access->directorQueue(),
            'approved' => $this->access->approvedQueue(),
            'on-mission' => Mission::query()->whereIn('lifecycle_status', [MissionStatus::Issued->value, MissionStatus::OnMission->value]),
            'pending-report' => Mission::query()->whereNotNull('started_at')->whereNull('completed_at')
                ->whereDoesntHave('reports', fn ($q) => $q->submitted()),
            'history' => Mission::query()->where(function ($q) {
                $q->whereIn('lifecycle_status', [MissionStatus::Completed->value, MissionStatus::Rejected->value, MissionStatus::Cancelled->value])
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('lifecycle_status')->where(function ($s) {
                            $s->whereNotNull('completed_at')->orWhereIn('status', ['rejected', 'cancelled']);
                        });
                    });
            }),
            default => Mission::query()->whereRaw('1 = 0'),
        };

        if (in_array($key, ['approved', 'on-mission', 'pending-report', 'history'], true)) {
            $query->whereIn('id', $this->access->managed()->select('missions.id'));
        }

        $missions = $query->with(['assigner:id,full_name', 'assignments.employee:id,first_name,last_name,employee_id'])
            ->withCount('assignments')->orderByDesc('id')->paginate(20)->withQueryString();

        return view('humanresource::attendance.missions.queue', [
            'missions' => $missions,
            'title' => $def['title'],
            'queueKey' => $key,
            'decideAction' => $def['decide'] ?? null,
        ]);
    }

    public function decide(Request $request, int $id): mixed
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['endorse', 'approve', 'reject', 'return'])],
            'note' => ['required_if:decision,reject', 'required_if:decision,return', 'nullable', 'string', 'max:1000'],
        ]);

        $mission = Mission::findOrFail($id);
        abort_if($mission->isLegacyFlow(), 404, 'Legacy missions use the single-step review action.');
        abort_unless($this->workflow->canAct(Auth::user(), $mission), 403);

        $result = $this->workflow->decide($mission, $data['decision'], Auth::user(), $data['note'] ?? null);
        abort_unless($result['ok'], 422, $result['message'] ?? 'មិនអាចដំណើរការសំណើនេះបានទេ។');

        return $this->result($request, $mission->fresh());
    }

    public function prepareOrder(Request $request, int $id)
    {
        abort_unless($this->access->can('manage_mission_order'), 403);
        $mission = Mission::with(['assignments.employee.position', 'destinations', 'documents'])->findOrFail($id);
        abort_unless(in_array($mission->lifecycle_status, [MissionStatus::PendingMissionOrder, MissionStatus::PreparingMissionOrder], true), 409, 'This mission is not awaiting order preparation.');

        if ($mission->lifecycle_status === MissionStatus::PendingMissionOrder) {
            $from = $mission->lifecycle_status;
            $mission->forceFill(['lifecycle_status' => MissionStatus::PreparingMissionOrder])->save();
            $this->workflow->recordHistory($mission, $from, MissionStatus::PreparingMissionOrder, 'start_preparing_order', Auth::id(), null);
        }

        $transportTypes = TransportType::query()->where('is_active', 1)->orderBy('sort_order')->get();
        $fundingSources = \Modules\Planning\Entities\FundingSource::query()->orderBy('id')->get();
        $details = $mission->order_details ?? [];

        return view('humanresource::attendance.missions.order-prepare', compact('mission', 'details', 'transportTypes', 'fundingSources'));
    }

    public function updateOrder(Request $request, int $id): mixed
    {
        abort_unless($this->access->can('manage_mission_order'), 403);
        $data = $request->validate([
            'order_details' => ['sometimes', 'array:issuing_authority,issuing_department,issue_place,issued_on,lunar_date,reference,signatory_title,signatory_name'],
            'order_details.issuing_authority' => ['nullable', 'string', 'max:255'],
            'order_details.issuing_department' => ['nullable', 'string', 'max:255'],
            'order_details.issue_place' => ['nullable', 'string', 'max:100'],
            'order_details.issued_on' => ['nullable', 'date_format:Y-m-d'],
            'order_details.lunar_date' => ['nullable', 'string', 'max:255'],
            'order_details.reference' => ['nullable', 'string', 'max:2000'],
            'order_details.signatory_title' => ['nullable', 'string', 'max:255'],
            'order_details.signatory_name' => ['nullable', 'string', 'max:255'],
            'order_number' => ['nullable', 'string', 'max:100'],
            'transport_type_id' => ['nullable', 'integer', 'exists:transport_types,id'],
            'transport_description' => ['nullable', 'string', 'max:255'],
            'funding_source_id' => ['nullable', 'integer', 'exists:funding_sources,id'],
        ]);

        $mission = DB::transaction(function () use ($id, $data) {
            $mission = Mission::lockForUpdate()->findOrFail($id);
            abort_unless(in_array($mission->lifecycle_status, [MissionStatus::PendingMissionOrder, MissionStatus::PreparingMissionOrder], true), 409);
            $mission->update($data);

            return $mission;
        });

        return $this->result($request, $mission);
    }

    public function issueOrder(Request $request, int $id): mixed
    {
        abort_unless($this->access->can('manage_mission_order'), 403);
        $mission = DB::transaction(function () use ($id) {
            $mission = Mission::lockForUpdate()->findOrFail($id);
            abort_unless(in_array($mission->lifecycle_status, [MissionStatus::PendingMissionOrder, MissionStatus::PreparingMissionOrder], true), 409, 'This mission is not ready to be issued.');
            abort_unless($mission->assignments()->where('status', 'active')->exists(), 422, 'Assign at least one officer before issuing the order.');
            $from = $mission->lifecycle_status;
            $mission->forceFill([
                'lifecycle_status' => MissionStatus::Issued,
                'issued_at' => now(),
                'status' => 'approved',
            ])->save();
            $this->workflow->recordHistory($mission, $from, MissionStatus::Issued, 'issue', Auth::id(), null);

            return $mission;
        });

        return $this->result($request, $mission);
    }

    public function destroy(Request $request, int $id): mixed
    {
        DB::transaction(function () use ($id) {
            $mission = Mission::lockForUpdate()->findOrFail($id);
            $this->access->manage($mission, 'delete_mission');
            abort_unless(in_array($mission->status, ['draft', 'rejected', 'cancelled']), 409, 'Cancel this mission before deleting it.');
            $mission->delete();
        });

        return $this->isApi($request) ? $this->ok(['deleted' => true]) : redirect()->route('missions.index')->with('success', 'បានលុបបេសកកម្ម។');
    }
}
