<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HealthContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $content = $this->route('health_content');

        return $content ? $this->user()->can('update', $content) : $this->user()->can('create', \App\Models\HealthContent::class);
    }

    public function rules(): array
    {
        $contentId = $this->route('health_content')?->id;

        return [
            'category_id' => ['nullable', 'exists:health_content_categories,id'],
            'title_km' => ['required', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('health_contents', 'slug')->ignore($contentId)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'educational_message' => ['nullable', 'string', 'max:2000'],
            'is_featured' => ['nullable', 'boolean'],
            'thumbnail_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'video_type' => ['nullable', 'in:youtube,uploaded'],
            'video_url' => ['required_if:video_type,youtube', 'nullable', 'string', 'max:2048'],
            'video_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'video_file' => ['nullable', 'file', 'mimes:mp4,webm', 'max:51200'],
            'duration' => ['nullable', 'regex:/^\d{1,3}:\d{2}$/'],
        ];
    }
}
