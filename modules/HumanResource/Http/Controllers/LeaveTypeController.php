<?php

namespace Modules\HumanResource\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Validation\Rule;
use Modules\HumanResource\Entities\LeaveType;

class LeaveTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:read_leave_type')->only('index', 'show');
        $this->middleware('permission:create_leave_type')->only(['create', 'store']);
        $this->middleware('permission:update_leave_type')->only(['edit', 'update']);
        $this->middleware('permission:delete_leave_type')->only('destroy');
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        return view('humanresource::index');
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        return view('humanresource::create');
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {     
        $validated = $request->validate([
            'leave_type' => ['required', 'string', 'max:255'],
            'leave_type_km' => ['nullable', 'string', 'max:255'],
            'leave_code' => ['required', 'string', 'max:100'],
            'leave_days' => ['required', 'numeric', 'min:0'],
            'policy_key' => ['nullable', Rule::in(LeaveType::policyKeyOptions())],
            'entitlement_scope' => ['required', Rule::in(LeaveType::entitlementScopeOptions())],
            'entitlement_unit' => ['required', Rule::in(LeaveType::entitlementUnitOptions())],
            'entitlement_value' => ['nullable', 'numeric', 'min:0'],
            'max_per_request' => ['nullable', 'numeric', 'min:0'],
            'is_paid' => ['required', 'boolean'],
            'requires_attachment' => ['required', 'boolean'],
            'requires_medical_certificate' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['leave_days'] = (int) round((float) $validated['leave_days']);
        $validated['entitlement_value'] = array_key_exists('entitlement_value', $validated) && $validated['entitlement_value'] !== null
            ? (float) $validated['entitlement_value']
            : null;
        $validated['max_per_request'] = array_key_exists('max_per_request', $validated) && $validated['max_per_request'] !== null
            ? (float) $validated['max_per_request']
            : null;

        LeaveType::create($validated);
        Toastr::success('leave Type added successfully :)','Success');
        return redirect()->route('leave.leaveTypeindex');  
    }

    public function update(Request $request, $uuid)
    {
        $validated = $request->validate([
            'leave_type' => ['required', 'string', 'max:255'],
            'leave_type_km' => ['nullable', 'string', 'max:255'],
            'leave_code' => ['required', 'string', 'max:100'],
            'leave_days' => ['required', 'numeric', 'min:0'],
            'policy_key' => ['nullable', Rule::in(LeaveType::policyKeyOptions())],
            'entitlement_scope' => ['required', Rule::in(LeaveType::entitlementScopeOptions())],
            'entitlement_unit' => ['required', Rule::in(LeaveType::entitlementUnitOptions())],
            'entitlement_value' => ['nullable', 'numeric', 'min:0'],
            'max_per_request' => ['nullable', 'numeric', 'min:0'],
            'is_paid' => ['required', 'boolean'],
            'requires_attachment' => ['required', 'boolean'],
            'requires_medical_certificate' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['leave_days'] = (int) round((float) $validated['leave_days']);
        $validated['entitlement_value'] = array_key_exists('entitlement_value', $validated) && $validated['entitlement_value'] !== null
            ? (float) $validated['entitlement_value']
            : null;
        $validated['max_per_request'] = array_key_exists('max_per_request', $validated) && $validated['max_per_request'] !== null
            ? (float) $validated['max_per_request']
            : null;

        $leave_type = LeaveType::where('uuid', $uuid)->firstOrFail();
        $leave_type->update($validated);
        Toastr::success('leave Type updated successfully :)','Success');
        return redirect()->route('leave.leaveTypeindex'); 
    }

 
    public function destroy($uuid)
    {
        LeaveType::where('uuid' , $uuid)->delete();
        Toastr::success('Leave Type deleted successfully :)','Success');
        return response()->json(['success' => 'success']);
    }
}
