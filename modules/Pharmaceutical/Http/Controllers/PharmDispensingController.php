<?php

namespace Modules\Pharmaceutical\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\HumanResource\Entities\Department;
use Modules\Pharmaceutical\Entities\PharmDispensing;
use Modules\Pharmaceutical\Entities\PharmDispensingItem;
use Modules\Pharmaceutical\Entities\PharmFacilityStock;
use Modules\Pharmaceutical\Entities\PharmMedicine;
use Modules\Pharmaceutical\Traits\PharmScope;

class PharmDispensingController extends Controller
{
    use PharmScope;

    public function index(Request $request)
    {
        $deptIds = $this->pharmAccessibleDepartmentIds();
        $search = trim((string) $request->query('search', ''));

        $dispensings = PharmDispensing::query()
            ->with(['department', 'dispenser', 'items.medicine'])
            ->when($deptIds !== null, fn ($q) => $q->whereIn('department_id', $deptIds))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('reference_no', 'like', "%{$search}%")
                       ->orWhere('patient_name', 'like', "%{$search}%")
                       ->orWhere('patient_id_no', 'like', "%{$search}%");
                });
            })
            ->latest('dispensing_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $level = $this->pharmLevel();
        return view('pharmaceutical::dispensings.index', compact('dispensings', 'search', 'level'));
    }

    public function create()
    {
        if (! $this->pharmCanDispense()) {
            return redirect()->route('pharmaceutical.dispensings.index')
                ->with('error', localize('no_dispensing_permission', 'Only hospitals and health centers can dispense medicines.'));
        }

        $dept = $this->pharmUserDepartment();
        $medicines = PharmMedicine::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'name_kh', 'unit']);

        // Available stock at this facility
        $stocks = PharmFacilityStock::where('department_id', $dept->id)
            ->where('quantity', '>', 0)
            ->with('medicine')
            ->get()
            ->groupBy('medicine_id');

        return view('pharmaceutical::dispensings.create', compact('medicines', 'dept', 'stocks'));
    }

    public function store(Request $request)
    {
        if (! $this->pharmCanDispense()) {
            abort(403);
        }

        $dept = $this->pharmUserDepartment();

        $validated = $request->validate([
            'dispensing_date'  => ['required', 'date'],
            'patient_name'    => ['required', 'string', 'max:255'],
            'patient_id_no'   => ['nullable', 'string', 'max:100'],
            'patient_gender'  => ['nullable', 'string', 'in:M,F'],
            'patient_age'     => ['nullable', 'integer', 'min:0', 'max:200'],
            'diagnosis'       => ['nullable', 'string', 'max:500'],
            'note'            => ['nullable', 'string', 'max:2000'],
            'items'           => ['required', 'array', 'min:1'],
            'items.*.medicine_id'        => ['required', 'integer', 'exists:pharm_medicines,id'],
            'items.*.quantity'           => ['required', 'numeric', 'min:0.01'],
            'items.*.batch_no'           => ['nullable', 'string', 'max:100'],
            'items.*.dosage_instruction' => ['nullable', 'string', 'max:255'],
            'items.*.duration_days'      => ['nullable', 'integer', 'min:1'],
        ]);

        $dispensing = DB::transaction(function () use ($validated, $dept) {
            $disp = PharmDispensing::create([
                'reference_no'    => $this->generateReferenceNo(),
                'department_id'   => $dept->id,
                'dispensing_date' => $validated['dispensing_date'],
                'patient_name'    => $validated['patient_name'],
                'patient_id_no'   => $validated['patient_id_no'] ?? null,
                'patient_gender'  => $validated['patient_gender'] ?? null,
                'patient_age'     => $validated['patient_age'] ?? null,
                'diagnosis'       => $validated['diagnosis'] ?? null,
                'note'            => $validated['note'] ?? null,
                'dispensed_by'    => Auth::id(),
                'created_by'      => Auth::id(),
            ]);

            foreach ($validated['items'] as $item) {
                PharmDispensingItem::create([
                    'dispensing_id'      => $disp->id,
                    'medicine_id'        => $item['medicine_id'],
                    'quantity'           => $item['quantity'],
                    'batch_no'           => $item['batch_no'] ?? null,
                    'dosage_instruction' => $item['dosage_instruction'] ?? null,
                    'duration_days'      => $item['duration_days'] ?? null,
                ]);

                // Deduct from facility stock
                $stock = PharmFacilityStock::where('department_id', $dept->id)
                    ->where('medicine_id', $item['medicine_id'])
                    ->when(!empty($item['batch_no']), fn ($q) => $q->where('batch_no', $item['batch_no']))
                    ->where('quantity', '>', 0)
                    ->first();

                if ($stock) {
                    $stock->update([
                        'quantity'   => max(0, (float) $stock->quantity - abs((float) $item['quantity'])),
                        'updated_by' => Auth::id(),
                    ]);
                }
            }

            return $disp;
        });

        return redirect()->route('pharmaceutical.dispensings.show', $dispensing->id)
            ->with('success', localize('data_save', 'Saved successfully.'));
    }

    public function show(PharmDispensing $dispensing)
    {
        $deptIds = $this->pharmAccessibleDepartmentIds();
        if ($deptIds !== null && ! in_array((int) $dispensing->department_id, $deptIds, true)) {
            abort(403);
        }

        $dispensing->load(['department', 'items.medicine', 'dispenser']);
        return view('pharmaceutical::dispensings.show', compact('dispensing'));
    }

    public function print(PharmDispensing $dispensing)
    {
        $deptIds = $this->pharmAccessibleDepartmentIds();
        if ($deptIds !== null && ! in_array((int) $dispensing->department_id, $deptIds, true)) {
            abort(403);
        }

        $dispensing->load(['department', 'items.medicine', 'dispenser']);
        return view('pharmaceutical::dispensings.print', compact('dispensing'));
    }

    private function generateReferenceNo(): string
    {
        $prefix = 'DISP-' . now()->format('Ymd') . '-';
        $last = PharmDispensing::where('reference_no', 'like', $prefix . '%')->max('reference_no');
        $seq = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;
        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
