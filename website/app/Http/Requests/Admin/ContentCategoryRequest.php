<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContentCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('content_category');

        return $category
            ? $this->user()->can('update', $category)
            : $this->user()->can('create', \App\Models\ContentCategory::class);
    }

    public function rules(): array
    {
        $categoryId = $this->route('content_category')?->id;

        return [
            'parent_id' => ['nullable', 'exists:content_categories,id', Rule::notIn([$categoryId])],
            'name_km' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('content_categories', 'slug')->ignore($categoryId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
