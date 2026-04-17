<?php

namespace Modules\Pharmaceutical\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\HumanResource\Entities\Department;
use Modules\Pharmaceutical\Entities\PharmDistribution;
use Modules\Pharmaceutical\Entities\PharmDistributionItem;
use Modules\Pharmaceutical\Entities\PharmFacilityStock;
use Modules\Pharmaceutical\Entities\PharmMedicine;
use Modules\Pharmaceutical\Traits\PharmScope;

class PharmDistributionController extends Controller
{
    use PharmScope;

    public function index(Request $request)
    {
        $deptIds = $this->pharmAccessibleDepartmentIds();
        $search = trim((string) $request->query('search', ''));
        $type = trim((string) $request->query('type', ''));
        $status = trim((string) $request->query('status', ''));

        $distributions = PharmDistribution::query()
            ->with(['fromDepartment', 'toDepartment'])
            ->when($deptIds !== null, fn ($q) => $q->where(fn ($qq) => $qq->whereIn('from_department_id', $deptIds)->orWhereIn('to_department_id', $deptIds)))
            ->when($type !== '', fn ($q) => $q->where('distribution_type', $type))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('reference_no', 'like', "%{$search}%")
                        ->orWhereHas('fromDepartment', fn ($d) => $d->where('department_name', 'like', "%{$search}%"))
                        ->orWhereHas('toDepartment', fn ($d) => $d->where('department_name', 'like', "%{$search}%"));
                });
            })
            ->latest('distribution_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $level = $this->pharmLevel();
        $canDistribute = $this->pharmCanDistribute();
        return view('pharmaceutical::distributions.index', compact('distributions', 'search', 'type', 'status', 'level', 'canDistribute'));
    }

    public function create(Request $request)
    {
        abort_unless($this->pharmCanDistribute(), 403);

        $type = trim((string) $request->query('type', PharmDistribution::TYPE_PHD_TO_OD));
        $deptIds = $this->pharmAccessibleDepartmentIds();
        $departments = Department::withoutGlobalScopes()
            ->where('is_active', 1)
            ->when($deptIds !== null, fn ($q) => $q->whereIn('id', $deptIds))
            ->orderBy('department_name')
            ->get(['id', 'department_name', 'parent_id', 'unit_type_id']);
        $medicines = PharmMedicine::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'name_kh', 'unit']);

        return view('pharmaceutical::distributions.create', compact('type', 'departments', 'medicines'));
    }

    public function store(Request $request)
    {
        abort_unless($this->pharmCanDistribute(), 403);

        $validated = $request->validate([
            'distribution_type' => ['required', Rule::in([
                PharmDistribution::TYPE_PHD_TO_HOSPITAL,
                PharmDistribution::TYPE_PHD_TO_OD,
                PharmDistribution::TYPE_OD_TO_HC,
            ])],
            'from_department_id' => ['required', 'integer', 'exists:departments,id'],
            'to_department_id' => ['required', 'integer', 'exists:departments,id'],
            'distribution_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medicine_id' => ['required', 'integer', 'exists:pharm_medicines,id'],
            'items.*.quantity_sent' => ['required', 'numeric', 'min:0.01'],
            'items.*.batch_no' => ['nullable', 'string', 'max:100'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $distribution = DB::transaction(function () use ($validated) {
            $dist = PharmDistribution::create([
                'reference_no' => $this->generateReferenceNo(),
                'distribution_type' => $validated['distribution_type'],
                'from_department_id' => $validated['from_department_id'],
                'to_department_id' => $validated['to_department_id'],
                'distribution_date' => $validated['distribution_date'],
                'status' => PharmDistribution::STATUS_DRAFT,
                'note' => $validated['note'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['items'] as $item) {
                PharmDistributionItem::create([
                    'distribution_id' => $dist->id,
                    'medicine_id' => $item['medicine_id'],
                    'quantity_sent' => $item['quantity_sent'],
                    'batch_no' => $item['batch_no'] ?? null,
                    'expiry_date' => $item['expiry_date'] ?? null,
                    'unit_price' => $item['unit_price'] ?? 0,
                ]);
            }

            return $dist;
        });

        return redirect()->route('pharmaceutical.distributions.show', $distribution->id)
            ->with('success', localize('data_save', 'Saved successfully.'));
    }

    public function show(PharmDistribution $distribution)
    {
        $deptIds = $this->pharmAccessibleDepartmentIds();
        if ($deptIds !== null) {
            abort_unless(
                in_array((int) $distribution->from_department_id, $deptIds, true)
                || in_array((int) $distribution->to_department_id, $deptIds, true),
                403
            );
        }

        $distribution->load([
            'fromDepartment',
            'toDepartment',
            'items.medicine.category',
            'sender',
            'receiver',
        ]);

        $canDistribute = $this->pharmCanDistribute();
        $canReceive = $deptIds === null || in_array((int) $distribution->to_department_id, $deptIds, true);

        return view('pharmaceutical::distributions.show', compact('distribution', 'canDistribute', 'canReceive'));
    }

    public function send(PharmDistribution $distribution)
    {
        abort_unless($this->pharmCanDistribute(), 403);

        $deptIds = $this->pharmAccessibleDepartmentIds();
        if ($deptIds !== null) {
            abort_unless(in_array((int) $distribution->from_department_id, $deptIds, true), 403);
        }

        if ($distribution->status !== PharmDistribution::STATUS_DRAFT) {
            return back()->with('error', localize('already_sent', 'This distribution has already been sent.'));
        }

        DB::transaction(function () use ($distribution) {
            $distribution->update([
                'status' => PharmDistribution::STATUS_SENT,
                'sent_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            // Deduct from sender stock
            foreach ($distribution->items as $item) {
                $this->adjustStock(
                    (int) $distribution->from_department_id,
                    (int) $item->medicine_id,
                    -abs((float) $item->quantity_sent),
                    $item->batch_no,
                    $item->expiry_date
                );
            }
        });

        return redirect()->route('pharmaceutical.distributions.show', $distribution->id)
            ->with('success', localize('distribution_sent', 'Distribution sent successfully.'));
    }

    public function receive(Request $request, PharmDistribution $distribution)
    {
        $deptIds = $this->pharmAccessibleDepartmentIds();
        if ($deptIds !== null) {
            abort_unless(in_array((int) $distribution->to_department_id, $deptIds, true), 403);
        }

        if (!in_array($distribution->status, [PharmDistribution::STATUS_SENT, PharmDistribution::STATUS_PARTIAL], true)) {
            return back()->with('error', localize('cannot_receive', 'This distribution cannot be received at this stage.'));
        }

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:pharm_distribution_items,id'],
            'items.*.quantity_received' => ['required', 'numeric', 'min:0'],
            'received_note' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($distribution, $validated) {
            foreach ($validated['items'] as $data) {
                $item = PharmDistributionItem::findOrFail((int) $data['id']);
                $item->update(['quantity_received' => $data['quantity_received']]);

                // Add to receiver stock
                $this->adjustStock(
                    (int) $distribution->to_department_id,
                    (int) $item->medicine_id,
                    abs((float) $data['quantity_received']),
                    $item->batch_no,
                    $item->expiry_date
                );
            }

            $allReceived = $distribution->items()->get()->every(fn ($i) => (float) $i->quantity_received >= (float) $i->quantity_sent);

            $distribution->update([
                'status' => $allReceived ? PharmDistribution::STATUS_COMPLETED : PharmDistribution::STATUS_PARTIAL,
                'received_date' => now()->toDateString(),
                'received_note' => $validated['received_note'] ?? null,
                'received_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
        });

        return redirect()->route('pharmaceutical.distributions.show', $distribution->id)
            ->with('success', localize('distribution_received', 'Distribution received successfully.'));
    }

    // ─── helpers ───

    private function adjustStock(int $departmentId, int $medicineId, float $qty, ?string $batchNo, $expiryDate): void
    {
        $stock = PharmFacilityStock::firstOrCreate(
            [
                'department_id' => $departmentId,
                'medicine_id' => $medicineId,
                'batch_no' => $batchNo,
            ],
            [
                'quantity' => 0,
                'expiry_date' => $expiryDate,
                'updated_by' => Auth::id(),
            ]
        );

        $stock->update([
            'quantity' => max(0, (float) $stock->quantity + $qty),
            'updated_by' => Auth::id(),
        ]);
    }

    private function generateReferenceNo(): string
    {
        $prefix = 'DIST-' . now()->format('Ymd') . '-';
        $last = PharmDistribution::where('reference_no', 'like', $prefix . '%')->max('reference_no');
        $seq = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;
        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
