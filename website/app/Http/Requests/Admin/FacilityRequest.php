<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FacilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $facility = $this->route('facility');

        return $facility ? $this->user()->can('update', $facility) : $this->user()->can('create', \App\Models\Facility::class);
    }

    public function rules(): array
    {
        $facilityId = $this->route('facility')?->id;

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('facilities', 'code')->ignore($facilityId)],
            'name_km' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('facilities', 'slug')->ignore($facilityId)],
            'facility_type' => ['required', Rule::in(['referral_hospital', 'health_center', 'health_post', 'operational_district_office'])],
            'district' => ['required', 'string', 'max:255'],
            'commune' => ['required', 'string', 'max:255'],
            'village' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'opening_hours' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'history' => ['nullable', 'string'],
            'vision' => ['nullable', 'string'],
            'mission' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'cover_image' => ['nullable', 'image', 'max:4096'],
            'patients_count' => ['nullable', 'integer', 'min:0'],
            'staff_count' => ['nullable', 'integer', 'min:0'],
            'services_count' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
