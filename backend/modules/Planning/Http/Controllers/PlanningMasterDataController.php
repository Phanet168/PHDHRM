<?php

namespace Modules\Planning\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\HumanResource\Entities\Department;
use Modules\Planning\Entities\Account;
use Modules\Planning\Entities\ActivityCluster;
use Modules\Planning\Entities\Chapter;
use Modules\Planning\Entities\FundingSource;
use Modules\Planning\Entities\Indicator;
use Modules\Planning\Entities\OrgUnit;
use Modules\Planning\Entities\Program;
use Modules\Planning\Entities\SubAccount;
use Modules\Planning\Entities\SubProgram;
use Modules\Planning\Http\Requests\MasterDataRequest;
use Modules\Planning\Services\PlanningModuleStateService;

class PlanningMasterDataController extends Controller
{
    public function __construct(private readonly PlanningModuleStateService $moduleStateService)
    {
    }

    public function index(Request $request, string $resource): View
    {
        if (!$this->moduleStateService->isInstalled()) {
            return view('planning::setup.index');
        }

        $this->authorizeAccess($request);
        $meta = $this->meta($resource);
        $query = $meta['model']::query();

        foreach ($meta['with'] ?? [] as $relation) {
            $query->with($relation);
        }

        if ($request->filled('q')) {
            $term = '%' . trim((string) $request->string('q')) . '%';
            $query->where(function ($builder) use ($meta, $term) {
                foreach ($meta['search'] as $index => $column) {
                    $index === 0
                        ? $builder->where($column, 'like', $term)
                        : $builder->orWhere($column, 'like', $term);
                }
            });
        }

        if ($resource === 'org_units') {
            $query
                ->orderByRaw("CASE WHEN hierarchy_path IS NULL OR hierarchy_path = '' THEN 0 ELSE 1 END")
                ->orderBy('hierarchy_path')
                ->orderBy('level')
                ->orderBy('name');
        } else {
            $query->orderBy($meta['order_by'][0], $meta['order_by'][1]);
        }

        $records = $query->paginate(15)->withQueryString();

        return view('planning::master-data.index', [
            'meta' => $meta,
            'resource' => $resource,
            'records' => $records,
            'filters' => $request->only('q'),
        ]);
    }

    public function create(Request $request, string $resource): View
    {
        if (!$this->moduleStateService->isInstalled()) {
            return view('planning::setup.index');
        }

        $this->authorizeAccess($request);
        $meta = $this->meta($resource);
        $modelClass = $meta['model'];

        return view('planning::master-data.form', [
            'meta' => $meta,
            'resource' => $resource,
            'record' => new $modelClass(),
            'options' => $this->options($resource),
            'isEdit' => false,
        ]);
    }

    public function store(MasterDataRequest $request, string $resource): RedirectResponse
    {
        $meta = $this->meta($resource);
        $meta['model']::create($this->normalizedPayload($resource, $request->validated()));

        return redirect()
            ->route('planning.master-data.index', $resource)
            ->with('success', 'បានបង្កើត' . $meta['singular_label'] . 'ដោយជោគជ័យ។');
    }

    public function edit(Request $request, string $resource, int $recordId): View
    {
        if (!$this->moduleStateService->isInstalled()) {
            return view('planning::setup.index');
        }

        $this->authorizeAccess($request);
        $meta = $this->meta($resource);
        $record = $meta['model']::query()->findOrFail($recordId);

        return view('planning::master-data.form', [
            'meta' => $meta,
            'resource' => $resource,
            'record' => $record,
            'options' => $this->options($resource, $recordId),
            'isEdit' => true,
        ]);
    }

    public function update(MasterDataRequest $request, string $resource, int $recordId): RedirectResponse
    {
        $meta = $this->meta($resource);
        $record = $meta['model']::query()->findOrFail($recordId);
        $record->update($this->normalizedPayload($resource, $request->validated()));

        return redirect()
            ->route('planning.master-data.index', $resource)
            ->with('success', 'បានកែប្រែ' . $meta['singular_label'] . 'ដោយជោគជ័យ។');
    }

    public function destroy(Request $request, string $resource, int $recordId): RedirectResponse
    {
        $this->authorizeAccess($request);
        $meta = $this->meta($resource);
        $record = $meta['model']::query()->findOrFail($recordId);

        try {
            $record->delete();
        } catch (QueryException) {
            return back()->with('error', 'ទិន្នន័យនេះកំពុងត្រូវបានប្រើ ហើយមិនអាចលុបបានទេ។');
        }

        return redirect()
            ->route('planning.master-data.index', $resource)
            ->with('success', 'បានលុប' . $meta['singular_label'] . 'ដោយជោគជ័យ។');
    }

    private function authorizeAccess(Request $request): void
    {
        abort_unless($request->user()?->can('planning.manage_master_data'), 403);
    }

    private function normalizedPayload(string $resource, array $data): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        if ($resource === 'indicators') {
            unset($data['program_id'], $data['sub_program_id']);
        }

        return $data;
    }

    private function options(string $resource, ?int $recordId = null): array
    {
        return match ($resource) {
            'org_units' => [
                'parents' => OrgUnit::query()
                    ->when($recordId, fn ($query) => $query->where('id', '!=', $recordId))
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'code', 'name']),
                'departments' => Department::query()
                    ->orderBy('department_name')
                    ->get(['id', 'department_name']),
            ],
            'accounts' => [
                'chapters' => Chapter::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('code')
                    ->get(['id', 'code', 'name']),
            ],
            'sub_accounts' => [
                'accounts' => Account::query()
                    ->with('chapter')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('code')
                    ->get(['id', 'chapter_id', 'code', 'name']),
            ],
            'sub_programs' => [
                'programs' => Program::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('code')
                    ->get(['id', 'code', 'name']),
            ],
            'activity_clusters' => [
                'programs' => Program::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('code')
                    ->get(['id', 'code', 'name']),
                'sub_programs' => SubProgram::query()
                    ->with('program')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('code')
                    ->get(['id', 'program_id', 'code', 'name']),
            ],
            'indicators' => [
                'programs' => Program::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('code')
                    ->get(['id', 'code', 'name']),
                'sub_programs' => SubProgram::query()
                    ->with('program')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('code')
                    ->get(['id', 'program_id', 'code', 'name']),
                'activity_clusters' => ActivityCluster::query()
                    ->with('subProgram.program')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('code')
                    ->get(['id', 'sub_program_id', 'code', 'name']),
            ],
            default => [],
        };
    }

    private function meta(string $resource): array
    {
        $map = [
            'org_units' => [
                'model' => OrgUnit::class,
                'label' => 'អង្គភាព',
                'singular_label' => 'អង្គភាព',
                'order_by' => ['name', 'asc'],
                'search' => ['code', 'name', 'name_km', 'manager_name', 'org_path_code'],
                'with' => ['parent', 'sourceDepartment'],
                'columns' => [
                    'code' => 'កូដ',
                    'name' => 'ឈ្មោះ',
                    'unit_type' => 'ប្រភេទអង្គភាព',
                    'manager_name' => 'អ្នកគ្រប់គ្រង',
                    'is_active' => 'ស្ថានភាព',
                ],
            ],
            'chapters' => [
                'model' => Chapter::class,
                'label' => 'ជំពូក',
                'singular_label' => 'ជំពូក',
                'order_by' => ['sort_order', 'asc'],
                'search' => ['code', 'name', 'name_km'],
                'columns' => [
                    'code' => 'កូដ',
                    'name' => 'ឈ្មោះ',
                    'sort_order' => 'លំដាប់',
                    'is_active' => 'ស្ថានភាព',
                ],
            ],
            'accounts' => [
                'model' => Account::class,
                'label' => 'គណនេយ្យ',
                'singular_label' => 'គណនេយ្យ',
                'order_by' => ['sort_order', 'asc'],
                'search' => ['code', 'name', 'name_km'],
                'with' => ['chapter'],
                'columns' => [
                    'code' => 'កូដ',
                    'name' => 'ឈ្មោះ',
                    'chapter.name' => 'ជំពូក',
                    'sort_order' => 'លំដាប់',
                    'is_active' => 'ស្ថានភាព',
                ],
            ],
            'sub_accounts' => [
                'model' => SubAccount::class,
                'label' => 'អនុគណនេយ្យ',
                'singular_label' => 'អនុគណនេយ្យ',
                'order_by' => ['sort_order', 'asc'],
                'search' => ['code', 'name', 'name_km'],
                'with' => ['account.chapter'],
                'columns' => [
                    'code' => 'កូដ',
                    'name' => 'ឈ្មោះ',
                    'account.name' => 'គណនេយ្យ',
                    'account.chapter.name' => 'ជំពូក',
                    'sort_order' => 'លំដាប់',
                    'is_active' => 'ស្ថានភាព',
                ],
            ],
            'programs' => [
                'model' => Program::class,
                'label' => 'កម្មវិធី',
                'singular_label' => 'កម្មវិធី',
                'order_by' => ['sort_order', 'asc'],
                'search' => ['code', 'name', 'name_km'],
                'columns' => [
                    'code' => 'កូដ',
                    'name' => 'ឈ្មោះ',
                    'sort_order' => 'លំដាប់',
                    'is_active' => 'ស្ថានភាព',
                ],
            ],
            'sub_programs' => [
                'model' => SubProgram::class,
                'label' => 'អនុកម្មវិធី',
                'singular_label' => 'អនុកម្មវិធី',
                'order_by' => ['sort_order', 'asc'],
                'search' => ['code', 'name', 'name_km'],
                'with' => ['program'],
                'columns' => [
                    'code' => 'កូដ',
                    'name' => 'ឈ្មោះ',
                    'program.name' => 'កម្មវិធី',
                    'sort_order' => 'លំដាប់',
                    'is_active' => 'ស្ថានភាព',
                ],
            ],
            'activity_clusters' => [
                'model' => ActivityCluster::class,
                'label' => 'ចង្កោមសកម្មភាព',
                'singular_label' => 'ចង្កោមសកម្មភាព',
                'order_by' => ['sort_order', 'asc'],
                'search' => ['code', 'name', 'name_km'],
                'with' => ['subProgram.program'],
                'columns' => [
                    'code' => 'កូដ',
                    'name' => 'ចង្កោមសកម្មភាព',
                    'subProgram.name' => 'អនុកម្មវិធី',
                    'subProgram.program.name' => 'កម្មវិធី',
                    'sort_order' => 'លំដាប់',
                    'is_active' => 'ស្ថានភាព',
                ],
            ],
            'indicators' => [
                'model' => Indicator::class,
                'label' => 'សូចនាករ',
                'singular_label' => 'សូចនាករ',
                'order_by' => ['sort_order', 'asc'],
                'search' => ['code', 'name', 'name_km', 'unit_of_measure'],
                'with' => ['activityCluster.subProgram.program'],
                'columns' => [
                    'code' => 'កូដ',
                    'name' => 'សូចនាករ',
                    'activityCluster.subProgram.program.name' => 'កម្មវិធី',
                    'activityCluster.subProgram.name' => 'អនុកម្មវិធី',
                    'activityCluster.name' => 'ចង្កោមសកម្មភាព',
                    'unit_of_measure' => 'ឯកតា',
                    'sort_order' => 'លំដាប់',
                    'is_active' => 'ស្ថានភាព',
                ],
            ],
            'funding_sources' => [
                'model' => FundingSource::class,
                'label' => 'ប្រភពថវិកា',
                'singular_label' => 'ប្រភពថវិកា',
                'order_by' => ['name', 'asc'],
                'search' => ['code', 'name', 'name_km', 'description'],
                'columns' => [
                    'code' => 'កូដ',
                    'name' => 'ឈ្មោះ',
                    'name_km' => 'ឈ្មោះខ្មែរ',
                    'description' => 'ពិពណ៌នា',
                    'is_active' => 'ស្ថានភាព',
                ],
            ],
        ];

        abort_unless(isset($map[$resource]), 404);

        return $map[$resource];
    }
}
