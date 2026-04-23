<?php

namespace Modules\HumanResource\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\HumanResource\Entities\UserAssignment;

class StoreUserAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && (
            auth()->user()->can('create_org_governance')
            || auth()->user()->can('create_department')
        );
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'position_id' => ['nullable', 'integer', Rule::exists('positions', 'id')],
            'responsibility_id' => ['required', 'integer', Rule::exists('system_roles', 'id')->where('is_active', 1)],
            'scope_type' => ['required', Rule::in(UserAssignment::scopeOptions())],
            'is_primary' => ['required', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $scope = trim((string) $this->input('scope_type', ''));
        if ($scope === 'self') {
            $scope = UserAssignment::SCOPE_SELF_ONLY;
        }

        $this->merge([
            'scope_type' => $scope,
            'is_primary' => filter_var($this->input('is_primary'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
        ]);
    }
}
