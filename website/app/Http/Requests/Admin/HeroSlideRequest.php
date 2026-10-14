<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class HeroSlideRequest extends FormRequest
{
    public function authorize(): bool
    {
        $slide = $this->route('hero_slide');

        return $slide ? $this->user()->can('update', $slide) : $this->user()->can('create', \App\Models\HeroSlide::class);
    }

    public function rules(): array
    {
        return [
            'image' => [$this->route('hero_slide') ? 'nullable' : 'required', 'image', 'max:4096'],
            'title_km' => ['required', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'button_text' => ['nullable', 'string', 'max:100'],
            'button_link' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
