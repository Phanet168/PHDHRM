<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $content = $this->route('content');

        return $content
            ? $this->user()->can('update', $content)
            : $this->user()->can('create', \App\Models\Content::class);
    }

    public function rules(): array
    {
        $contentId = $this->route('content')?->id;

        return [
            'category_id' => ['nullable', 'exists:content_categories,id'],
            'title_km' => ['required', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('contents', 'slug')->ignore($contentId)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'string', 'max:500'],
            'featured_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'is_featured' => ['nullable', 'boolean'],
        ];
    }
}
