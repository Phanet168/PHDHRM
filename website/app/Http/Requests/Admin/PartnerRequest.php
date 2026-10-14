<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('partners.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'website' => ['nullable', 'url', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
