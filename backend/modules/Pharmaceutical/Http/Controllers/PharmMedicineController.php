<?php

namespace Modules\Pharmaceutical\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Pharmaceutical\Entities\PharmCategory;
use Modules\Pharmaceutical\Entities\PharmMedicine;
use Modules\Pharmaceutical\Traits\PharmScope;

class PharmMedicineController extends Controller
{
    use PharmScope;

    /**
     * Only PHD-level users (and Super Admin) may modify the master medicine/category list.
     */
    private function authorizeWrite(): void
    {
        $user = Auth::user();
        if ($user && (int) $user->user_type_id === 1) {
            return;
        }
        abort_unless($this->pharmLevel() === 'phd', 403);
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $categoryId = (int) $request->query('category_id', 0);

        $medicines = PharmMedicine::query()
            ->with('category')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('name_kh', 'like', "%{$search}%");
                });
            })
            ->when($categoryId > 0, fn ($q) => $q->where('category_id', $categoryId))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $categories = PharmCategory::where('is_active', true)->orderBy('name')->get();

        $level = $this->pharmLevel();
        $isSuperAdmin = Auth::user() && (int) Auth::user()->user_type_id === 1;
        $canWrite = $isSuperAdmin || $level === 'phd';

        return view('pharmaceutical::medicines.index', compact('medicines', 'categories', 'search', 'categoryId', 'canWrite'));
    }

    public function create()
    {
        $this->authorizeWrite();
        $categories = PharmCategory::where('is_active', true)->orderBy('name')->get();
        return view('pharmaceutical::medicines.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $this->authorizeWrite();

        $validated = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:pharm_categories,id'],
            'code' => ['required', 'string', 'max:50', 'unique:pharm_medicines,code'],
            'name' => ['required', 'string', 'max:255'],
            'name_kh' => ['nullable', 'string', 'max:255'],
            'dosage_form' => ['nullable', 'string', 'max:100'],
            'strength' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:50'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $validated['is_active'] = true;
        $validated['created_by'] = Auth::id();

        PharmMedicine::create($validated);

        return redirect()->route('pharmaceutical.medicines.index')
            ->with('success', localize('data_save', 'Saved successfully.'));
    }

    public function edit(PharmMedicine $medicine)
    {
        $this->authorizeWrite();
        $categories = PharmCategory::where('is_active', true)->orderBy('name')->get();
        return view('pharmaceutical::medicines.edit', compact('medicine', 'categories'));
    }

    public function update(Request $request, PharmMedicine $medicine)
    {
        $this->authorizeWrite();

        $validated = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:pharm_categories,id'],
            'code' => ['required', 'string', 'max:50', 'unique:pharm_medicines,code,' . $medicine->id],
            'name' => ['required', 'string', 'max:255'],
            'name_kh' => ['nullable', 'string', 'max:255'],
            'dosage_form' => ['nullable', 'string', 'max:100'],
            'strength' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:50'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $validated['updated_by'] = Auth::id();

        $medicine->update($validated);

        return redirect()->route('pharmaceutical.medicines.index')
            ->with('success', localize('data_update', 'Updated successfully.'));
    }

    public function destroy(PharmMedicine $medicine)
    {
        $this->authorizeWrite();

        $medicine->update(['updated_by' => Auth::id()]);
        $medicine->delete();

        return redirect()->route('pharmaceutical.medicines.index')
            ->with('success', localize('data_delete', 'Deleted successfully.'));
    }

    // ─── Categories ───
    public function categories(Request $request)
    {
        $categories = PharmCategory::withCount('medicines')->latest('id')->paginate(20);
        $isSuperAdmin = Auth::user() && (int) Auth::user()->user_type_id === 1;
        $canWrite = $isSuperAdmin || $this->pharmLevel() === 'phd';
        return view('pharmaceutical::medicines.categories', compact('categories', 'canWrite'));
    }

    public function storeCategory(Request $request)
    {
        $this->authorizeWrite();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_kh' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['created_by'] = Auth::id();
        PharmCategory::create($validated);

        return redirect()->route('pharmaceutical.categories.index')
            ->with('success', localize('data_save', 'Saved successfully.'));
    }

    public function updateCategory(Request $request, PharmCategory $category)
    {
        $this->authorizeWrite();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_kh' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['updated_by'] = Auth::id();
        $category->update($validated);

        return redirect()->route('pharmaceutical.categories.index')
            ->with('success', localize('data_update', 'Updated successfully.'));
    }

    public function destroyCategory(PharmCategory $category)
    {
        $this->authorizeWrite();

        if ($category->medicines()->exists()) {
            return back()->with('error', localize('category_has_medicines', 'Cannot delete category that has medicines.'));
        }

        $category->update(['updated_by' => Auth::id()]);
        $category->delete();

        return redirect()->route('pharmaceutical.categories.index')
            ->with('success', localize('data_delete', 'Deleted successfully.'));
    }
}
