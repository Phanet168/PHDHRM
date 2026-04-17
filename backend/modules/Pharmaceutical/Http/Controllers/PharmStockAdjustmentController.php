<?php

namespace Modules\Pharmaceutical\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Modules\HumanResource\Entities\Department;
use Modules\Pharmaceutical\Entities\PharmFacilityStock;
use Modules\Pharmaceutical\Entities\PharmMedicine;
use Modules\Pharmaceutical\Entities\PharmStockAdjustment;
use Modules\Pharmaceutical\Traits\PharmScope;

class PharmStockAdjustmentController extends Controller
{
    use PharmScope;

    public function index(Request $request)
    {
        $this->authorizeRead();

        $deptIds = $this->pharmAccessibleDepartmentIds();
        $search = trim((string) $request->query('search', ''));
        $typeFilter = trim((string) $request->query('type', ''));
        $deptFilter = (int) $request->query('dept', 0);

        $adjustments = PharmStockAdjustment::query()
            ->with(['department', 'medicine', 'adjuster'])
            ->when($deptIds !== null, fn ($q) => $q->whereIn('department_id', $deptIds))
            ->when($deptFilter > 0, fn ($q) => $q->where('department_id', $deptFilter))
            ->when($typeFilter !== '', fn ($q) => $q->where('adjustment_type', $typeFilter))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%")
                        ->orWhereHas('medicine', fn ($m) => $m->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->latest('adjustment_date')
            ->latest('id')
            ->paginate(20)
            ->appends($request->query());

        $facilityTypes = array_keys(\Modules\HumanResource\Support\OrgScopeService::LEVEL_MAP);
        $departments = Department::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('is_active', 1)
            ->whereIn('unit_type_id', $facilityTypes)
            ->when($deptIds !== null, fn ($q) => $q->whereIn('id', $deptIds))
            ->orderBy('department_name')
            ->get(['id', 'department_name']);

        return view('pharmaceutical::stock-adjustments.index', compact('adjustments', 'search', 'typeFilter', 'deptFilter', 'departments'));
    }

    public function create()
    {
        $this->authorizeRead();

        $deptIds = $this->pharmAccessibleDepartmentIds();
        $facilityTypes = array_keys(\Modules\HumanResource\Support\OrgScopeService::LEVEL_MAP);
        $departments = Department::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('is_active', 1)
            ->whereIn('unit_type_id', $facilityTypes)
            ->when($deptIds !== null, fn ($q) => $q->whereIn('id', $deptIds))
            ->orderBy('department_name')
            ->get(['id', 'department_name']);
        $medicines = PharmMedicine::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'name_kh', 'unit']);

        return view('pharmaceutical::stock-adjustments.create', compact('departments', 'medicines'));
    }

    public function store(Request $request)
    {
        $this->authorizeRead();

        $deptIds = $this->pharmAccessibleDepartmentIds();
        if ($deptIds !== null) {
            abort_unless(in_array((int) $request->input('department_id'), $deptIds, true), 403);
        }

        $validated = $request->validate([
            'department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')->where(fn ($q) => $q->whereNull('deleted_at')->where('is_active', 1)),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medicine_id' => ['required', 'integer', 'exists:pharm_medicines,id'],
            'items.*.adjustment_type' => ['required', Rule::in([
                PharmStockAdjustment::TYPE_DAMAGED,
                PharmStockAdjustment::TYPE_EXPIRED,
                PharmStockAdjustment::TYPE_LOSS,
                PharmStockAdjustment::TYPE_CORRECTION,
            ])],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.batch_no' => ['nullable', 'string', 'max:100'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.reason' => ['nullable', 'string', 'max:500'],
            'items.*.adjustment_date' => ['required', 'date'],
        ]);

        $deptId = $validated['department_id'];
        $refNo = $this->generateReferenceNo();

        foreach ($validated['items'] as $item) {
            PharmStockAdjustment::create([
                'reference_no' => $refNo,
                'department_id' => $deptId,
                'medicine_id' => $item['medicine_id'],
                'adjustment_type' => $item['adjustment_type'],
                'quantity' => $item['quantity'],
                'batch_no' => $item['batch_no'] ?? null,
                'expiry_date' => $item['expiry_date'] ?? null,
                'adjustment_date' => $item['adjustment_date'],
                'reason' => $item['reason'] ?? null,
                'adjusted_by' => Auth::id(),
                'created_by' => Auth::id(),
            ]);

            // Deduct from facility stock (damage/expired/loss reduce stock; correction adds)
            $qty = (float) $item['quantity'];
            if ($item['adjustment_type'] === PharmStockAdjustment::TYPE_CORRECTION) {
                $this->adjustStock($deptId, $item['medicine_id'], $qty, $item['batch_no'] ?? null, $item['expiry_date'] ?? null);
            } else {
                $this->adjustStock($deptId, $item['medicine_id'], -$qty, $item['batch_no'] ?? null, $item['expiry_date'] ?? null);
            }
        }

        return redirect()->route('pharmaceutical.stock-adjustments.index')
            ->with('success', localize('data_save', 'Saved successfully.'));
    }

    private function adjustStock(int $deptId, int $medicineId, float $qty, ?string $batchNo, ?string $expiryDate): void
    {
        $stock = PharmFacilityStock::firstOrCreate(
            [
                'department_id' => $deptId,
                'medicine_id' => $medicineId,
                'batch_no' => $batchNo,
            ],
            [
                'quantity' => 0,
                'expiry_date' => $expiryDate,
                'unit_price' => 0,
            ]
        );

        $stock->quantity = max(0, (float) $stock->quantity + $qty);
        $stock->updated_by = Auth::id();
        $stock->save();
    }

    private function generateReferenceNo(): string
    {
        $prefix = 'ADJ-' . now()->format('Ymd') . '-';
        $last = PharmStockAdjustment::where('reference_no', 'like', $prefix . '%')->max('reference_no');
        $seq = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;
        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function authorizeRead(): void
    {
        // All pharmaceutical users can manage stock adjustments
        abort_unless(Auth::check(), 403);
    }
}
