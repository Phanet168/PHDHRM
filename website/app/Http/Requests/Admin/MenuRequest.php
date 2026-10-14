<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        $menu = $this->route('menu');

        return $menu ? $this->user()->can('update', $menu) : $this->user()->can('create', \App\Models\Menu::class);
    }

    public function rules(): array
    {
        $menuId = $this->route('menu')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('menus', 'slug')->ignore($menuId)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
