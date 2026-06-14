<?php

namespace Modules\Planning\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('planning.create');
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'title' => ['nullable', 'string', 'max:255'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'objective' => ['nullable', 'string'],
            'summary' => ['nullable', 'string'],
            'background' => ['nullable', 'string'],
            'assumptions' => ['nullable', 'string'],
            'submission_note' => ['nullable', 'string'],
            'attachments.*' => ['nullable', 'file', 'max:10240'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.responsible_org_unit_id' => ['required', 'integer', 'exists:org_units,id'],
            'items.*.indicator_id' => ['required', 'integer', 'exists:indicators,id', 'distinct'],
            'items.*.item_code' => ['nullable', 'string', 'max:100'],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.baseline_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.target_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.achieved_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.target_unit' => ['nullable', 'string', 'max:100'],
            'items.*.indicator_note' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.indicator_id.distinct' => 'សូចនាករដែលបានជ្រើសមានស្ទួន។ សូចនាករមួយ អាចបញ្ចូលបានតែម្តងប៉ុណ្ណោះ។',
        ];
    }
}
