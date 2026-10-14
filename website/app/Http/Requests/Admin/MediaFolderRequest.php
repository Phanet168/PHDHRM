<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MediaFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $folder = $this->route('folder');

        return $folder
            ? $this->user()->can('update', $folder)
            : $this->user()->can('create', \App\Models\MediaFolder::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'regex:/^[^\/\\\\<>:"|?*]+$/u'],
            'parent_id' => ['nullable', 'integer', 'exists:media_folders,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'សូមបញ្ចូលឈ្មោះថត។',
            'name.regex' => 'ឈ្មោះថតមិនអាចមានតួអក្សរ / \\ < > : " | ? * បានទេ។',
        ];
    }
}
