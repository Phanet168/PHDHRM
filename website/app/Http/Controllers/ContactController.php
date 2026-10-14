<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\Facility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    private const DIRECTORY_PAGE_SIZE = 10;

    /** Display order of facility types (the order the Facility model already lists them in). */
    private const TYPE_ORDER = ['referral_hospital', 'health_center', 'health_post', 'operational_district_office'];

    public function index(Request $request)
    {
        $search = trim(mb_substr((string) $request->query('q', ''), 0, 100));
        $type = in_array($request->query('facility_type'), self::TYPE_ORDER, true) ? $request->query('facility_type') : null;
        $district = trim(mb_substr((string) $request->query('district', ''), 0, 100)) ?: null;

        // Reuses the existing Facility records: published ones that have a callable phone number.
        $directory = Facility::published()->withPublicPhone();

        $matching = (clone $directory)
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match
                ->where('name_km', 'like', "%{$search}%")
                ->orWhere('name_en', 'like', "%{$search}%")
                ->orWhere('district', 'like', "%{$search}%")
                ->orWhere('commune', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->when($type, fn ($query) => $query->where('facility_type', $type));

        // Left-panel counts follow the search + type filters, so they always predict the result size.
        $districtCounts = (clone $matching)->selectRaw('district, count(*) as total')->groupBy('district')->pluck('total', 'district');

        $facilities = (clone $matching)
            ->when($district, fn ($query) => $query->where('district', $district))
            ->orderByRaw("CASE facility_type WHEN 'referral_hospital' THEN 1 WHEN 'health_center' THEN 2 WHEN 'health_post' THEN 3 ELSE 4 END")
            ->orderBy('name_km')
            ->paginate(self::DIRECTORY_PAGE_SIZE)
            ->withQueryString();

        // Filter options come from the data that is actually listed, not from hardcoded lists.
        $typeOptions = (clone $directory)->distinct()->pluck('facility_type')
            ->sortBy(fn ($value) => array_search($value, self::TYPE_ORDER, true))
            ->mapWithKeys(fn ($value) => [$value => Facility::typeLabel($value)]);
        $districts = (clone $directory)->whereNotNull('district')->where('district', '!=', '')->distinct()->orderBy('district')->pluck('district');

        return view('contact', [
            'facilities' => $facilities,
            'typeOptions' => $typeOptions,
            'districts' => $districts,
            'districtCounts' => $districtCounts,
            'matchingTotal' => $districtCounts->sum(),
            'filters' => ['q' => $search, 'facility_type' => $type, 'district' => $district],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        ContactMessage::create($validated);

        return redirect()->route('contact')->with('status', 'សារបស់អ្នកត្រូវបានផ្ញើដោយជោគជ័យ។');
    }
}
