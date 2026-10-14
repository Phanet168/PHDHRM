<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $news = $this->route('news');

        return $news ? $this->user()->can('update', $news) : $this->user()->can('create', \App\Models\News::class);
    }

    public function rules(): array
    {
        $news = $this->route('news');
        $newsId = $news?->id;

        return [
            'category_id' => ['nullable', 'exists:news_categories,id'],
            'facility_id' => ['nullable', 'exists:facilities,id'],
            'title_km' => ['required', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('news', 'slug')->ignore($newsId)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'published_at' => ['nullable', 'date_format:Y-m-d'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            // Media Library is the source of truth for the featured image: featured_media_id (an existing
            // Media, picked or just-uploaded) wins; featured_image stays writable only as the legacy path.
            'featured_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'featured_image' => ['nullable', 'image', 'max:4096'],
            'remove_featured_image' => ['nullable', 'boolean'],
            // No cap on the gallery's total size: an admin can attach as many photos as needed,
            // in one save or by saving again later (each save appends the newly selected files).
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'gallery_media_ids' => ['nullable', 'array'],
            'gallery_media_ids.*' => ['integer', 'exists:media,id'],
            // Attachments (PDF/DOC/XLS…): kept separate from the image gallery. No count cap, same
            // reasoning as the gallery — an admin can attach as many documents as the article needs.
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,mp4,webm', 'max:20480'],
            'attachment_media_ids' => ['nullable', 'array'],
            'attachment_media_ids.*' => ['integer', 'exists:media,id'],
            'is_featured' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'featured_media_id.exists' => 'រូបភាពគម្របដែលបានជ្រើសរើសមិនមានទេ។',
            'images.*.image' => 'ឯកសារដែលបានជ្រើសត្រូវតែជារូបភាព។',
            'images.*.mimes' => 'រូបភាពត្រូវជា JPG, PNG ឬ WEBP។',
            'images.*.max' => 'រូបនីមួយៗមិនអាចលើសពី៤MBបានទេ។',
            'gallery_media_ids.*.exists' => 'រូបភាពដែលបានជ្រើសរើសមួយចំនួនមិនមានទេ។',
            'attachments.*.mimes' => 'ឯកសារភ្ជាប់ត្រូវជា JPG, PNG, WEBP, PDF, DOC, DOCX, XLS, XLSX, MP4 ឬ WEBM។',
            'attachments.*.max' => 'ឯកសារនីមួយៗមិនអាចលើសពី២០MBបានទេ។',
            'attachment_media_ids.*.exists' => 'ឯកសារដែលបានជ្រើសរើសមួយចំនួនមិនមានទេ។',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            if ($user->isFacilityScoped() && (int) $this->input('facility_id') !== $user->facility_id) {
                $validator->errors()->add('facility_id', 'អ្នកអាចបង្កើតព័ត៌មានសម្រាប់មូលដ្ឋានរបស់អ្នកប៉ុណ្ណោះ។');
            }

            // exists:media,id only proves the row exists — a tampered request could still name a
            // non-image (PDF, video…) media id, so that is checked separately here.
            $this->rejectNonImageMedia($validator, 'featured_media_id', $this->input('featured_media_id'));
            foreach ((array) $this->input('gallery_media_ids', []) as $id) {
                $this->rejectNonImageMedia($validator, 'gallery_media_ids', $id);
            }
        });
    }

    private function rejectNonImageMedia($validator, string $field, mixed $id): void
    {
        if (! $id || $validator->errors()->has($field) || $validator->errors()->has($field.'.*')) {
            return;
        }

        $media = \App\Models\Media::find($id);
        if ($media && ! $media->isImage()) {
            $validator->errors()->add($field, 'ឯកសារដែលបានជ្រើសរើសត្រូវតែជារូបភាព។');
        }
    }
}
