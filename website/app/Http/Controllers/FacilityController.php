<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use Illuminate\Support\Facades\Storage;

class FacilityController extends Controller
{
    public function index()
    {
        $facilities = Facility::published()->orderBy('name_km')->get();

        $districts = $facilities->pluck('district')->unique()->sort()->values();
        $communes = $facilities->pluck('commune')->unique()->sort()->values();

        $counts = [
            'referral_hospital' => $facilities->where('facility_type', 'referral_hospital')->count(),
            'health_center' => $facilities->where('facility_type', 'health_center')->count(),
            'health_post' => $facilities->where('facility_type', 'health_post')->count(),
            'operational_district_office' => $facilities->where('facility_type', 'operational_district_office')->count(),
            'total' => $facilities->count(),
        ];

        $mapFacilities = $facilities->map(fn (Facility $facility) => [
            'id' => $facility->id,
            'name' => $facility->name_km,
            'type' => $facility->facility_type,
            'typeLabel' => Facility::typeLabel($facility->facility_type),
            'color' => Facility::typeColor($facility->facility_type),
            'icon' => Facility::typeIcon($facility->facility_type),
            'district' => $facility->district,
            'commune' => $facility->commune,
            'address' => $facility->address,
            'phone' => $facility->phone,
            'openingHours' => $facility->opening_hours,
            'lat' => $facility->latitude ? (float) $facility->latitude : null,
            'lng' => $facility->longitude ? (float) $facility->longitude : null,
            'thumbnail' => $facility->cover_image
                ? (str_starts_with($facility->cover_image, 'http') ? $facility->cover_image : (str_starts_with($facility->cover_image, 'images/') ? asset($facility->cover_image) : Storage::disk('public')->url($facility->cover_image)))
                : asset('images/stock/stock-1.jpg'),
            'url' => route('facilities.show', $facility),
        ])->values();

        return view('facilities.index', compact('facilities', 'districts', 'communes', 'counts', 'mapFacilities'));
    }

    public function show(Facility $facility)
    {
        abort_unless($facility->status === 'published', 404);

        $leaders = $facility->leaders;
        $relatedNews = $facility->news()->published()->latest('published_at')->take(2)->get();

        return view('facilities.show', compact('facility', 'leaders', 'relatedNews'));
    }
}
