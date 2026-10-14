<?php

namespace App\Http\Requests\Admin;

use App\Models\HealthMessage;
use App\Support\HealthMessageIcon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HealthMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $message = $this->route('health_message');

        return $message ? $this->user()->can('update', $message) : $this->user()->can('create', HealthMessage::class);
    }

    public function rules(): array
    {
        return [
            'message_km' => ['required', 'string', 'max:255'],
            'icon' => ['required', Rule::in(HealthMessageIcon::WHITELIST)],
            'accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
