<?php

namespace Modules\HumanResource\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\HumanResource\Entities\UserAssignment;

class UpdateUserAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && (
            auth()->user()->can('update_org_governance')
            || auth()->user()->can('update_department')
        );
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'position_id' => ['nullable', 'integer', Rule::exists('positions', 'id')],
            'responsibility_template_id' => ['nullable', 'integer', Rule::exists('responsibility_templates', 'id')->where('is_active', 1)],
            'responsibility_id' => [
                'nullable',
                'integer',
                Rule::exists('system_roles', 'id')->where('is_active', 1),
                'required_without:responsibility_template_id',
            ],
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
            'responsibility_template_id' => !empty($this->input('responsibility_template_id'))
                ? (int) $this->input('responsibility_template_id')
                : null,
            'responsibility_id' => !empty($this->input('responsibility_id'))
                ? (int) $this->input('responsibility_id')
                : null,
            'position_id' => !empty($this->input('position_id'))
                ? (int) $this->input('position_id')
                : null,
            'is_primary' => filter_var($this->input('is_primary'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
        ]);
    }

    public function messages(): array
    {
        return [
            'responsibility_id.required_without' => localize(
                'responsibility_or_template_required',
                'សូមជ្រើស "គំរូតួនាទី" ឬ "តួនាទី" យ៉ាងហោចណាស់មួយ។'
            ),
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => localize('user', 'អ្នកប្រើប្រាស់'),
            'department_id' => localize('org_unit', 'អង្គភាព'),
            'responsibility_template_id' => localize('responsibility_template', 'គំរូតួនាទី'),
            'responsibility_id' => localize('responsibility_simple', 'តួនាទី'),
            'scope_type' => localize('scope', 'វិសាលភាព'),
            'is_active' => localize('status', 'ស្ថានភាព'),
        ];
    }
}
