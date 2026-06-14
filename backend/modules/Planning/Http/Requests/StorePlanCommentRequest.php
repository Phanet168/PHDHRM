<?php

namespace Modules\Planning\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePlanCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plan = $this->route('plan');

        return $this->user() !== null
            && $plan !== null
            && $this->user()->can('comment', $plan);
    }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string'],
        ];
    }
}
