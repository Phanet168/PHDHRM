<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('menus.manage');
    }

    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'exists:menu_items,id'],
            'label_km' => ['required', 'string', 'max:255'],
            'label_en' => ['nullable', 'string', 'max:255'],
            'link_type' => ['required', Rule::in(['route', 'url', 'page', 'facility', 'news_category', 'content_category', 'content'])],
            'route_name' => ['nullable', 'required_if:link_type,route', 'string', 'max:255'],
            'url' => ['nullable', 'required_if:link_type,url', 'string', 'max:255'],
            'linkable_id' => ['nullable', 'required_if:link_type,page,facility,news_category,content_category,content', 'integer'],
            'icon' => ['nullable', 'string', 'max:10'],
            'target' => ['required', Rule::in(['_self', '_blank'])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('link_type') === 'route' && $this->filled('route_name') && ! \Illuminate\Support\Facades\Route::has($this->input('route_name'))) {
                $validator->errors()->add('route_name', 'ផ្លូវ (route) នេះមិនមានទេ។');
            }
        });
    }
}
