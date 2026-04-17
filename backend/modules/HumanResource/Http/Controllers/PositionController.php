<?php

namespace Modules\HumanResource\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Str;
use Illuminate\Contracts\Support\Renderable;
use Modules\HumanResource\Entities\Position;

class PositionController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:read_positions', ['only' => ['index']]);
        $this->middleware('permission:create_positions', ['only' => ['create', 'store']]);
        $this->middleware('permission:update_positions', ['only' => ['edit', 'update']]);
        $this->middleware('permission:delete_positions', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        return view('humanresource::employee.position.index', [
            'positions' => Position::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'position_name' => 'required|string|max:255',
            'position_name_km' => 'nullable|string|max:255',
            'position_details' => 'nullable|string|max:255',
            'position_rank' => 'nullable|integer|min:1|max:20',
            'budget_amount' => 'nullable|numeric|min:0|max:999999999.99',
            'is_prov_level' => 'required|boolean',
            'is_active' => 'required|boolean',
        ]);

        $validated['budget_amount'] = (float) ($validated['budget_amount'] ?? 0);
        Position::create($validated);
        Toastr::success('Position added successfully :)','Success');
        return redirect()->route('positions.index');
    }


    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, $uuid)
    {
        $validated = $request->validate([
            'position_name' => 'required|string|max:255',
            'position_name_km' => 'nullable|string|max:255',
            'position_details' => 'nullable|string|max:255',
            'position_rank' => 'nullable|integer|min:1|max:20',
            'budget_amount' => 'nullable|numeric|min:0|max:999999999.99',
            'is_prov_level' => 'required|boolean',
            'is_active' => 'required|boolean',
        ]);

        $validated['budget_amount'] = (float) ($validated['budget_amount'] ?? 0);
        $position = $this->findPositionByUuidOrId($uuid);

        // Backfill legacy records that do not have a uuid yet.
        if (empty($position->uuid)) {
            $position->uuid = (string) Str::uuid();
        }

        $position->update($validated);
        Toastr::success('Position updated successfully :)','Success');
        return redirect()->route('positions.index');
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($uuid)
    {
        $position = $this->findPositionByUuidOrId($uuid);
        $position->delete();
        Toastr::success('Position deleted successfully :)','Success');
        return response()->json(['success' => 'success']);
    }

    protected function findPositionByUuidOrId($identifier): Position
    {
        return Position::query()
            ->where('uuid', (string) $identifier)
            ->orWhere(function ($query) use ($identifier) {
                if (is_numeric($identifier)) {
                    $query->where('id', (int) $identifier);
                }
            })
            ->firstOrFail();
    }
}
