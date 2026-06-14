<?php

namespace Modules\Planning\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MasterDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('planning.manage_master_data');
    }

    public function rules(): array
    {
        $resource = (string) $this->route('resource');
        $recordId = $this->route('recordId');

        return match ($resource) {
            'org_units' => [
                'source_department_id' => ['nullable', 'integer', 'exists:departments,id'],
                'parent_id' => ['nullable', 'integer', 'exists:org_units,id', 'different:recordId'],
                'code' => ['nullable', 'string', 'max:255'],
                'name' => ['required', 'string', 'max:255'],
                'name_km' => ['nullable', 'string', 'max:255'],
                'unit_type' => ['required', Rule::in([
                    'provincial_health_department',
                    'phd_office',
                    'operational_district',
                    'od_office',
                    'provincial_hospital',
                    'referral_hospital',
                    'health_center',
                    'other',
                ])],
                'level' => ['required', 'integer', 'min:1'],
                'org_path_code' => ['nullable', 'string', 'max:255'],
                'manager_name' => ['nullable', 'string', 'max:255'],
                'is_active' => ['nullable', 'boolean'],
            ],
            'chapters' => [
                'code' => ['required', 'string', 'max:20', Rule::unique('chapters', 'code')->ignore($recordId)],
                'name' => ['required', 'string', 'max:255'],
                'name_km' => ['nullable', 'string', 'max:255'],
                'sort_order' => ['required', 'integer', 'min:1'],
                'is_active' => ['nullable', 'boolean'],
            ],
            'accounts' => [
                'chapter_id' => ['required', 'integer', 'exists:chapters,id'],
                'code' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::unique('accounts', 'code')
                        ->where(fn ($query) => $query->where('chapter_id', $this->input('chapter_id')))
                        ->ignore($recordId),
                ],
                'name' => ['required', 'string', 'max:255'],
                'name_km' => ['nullable', 'string', 'max:255'],
                'sort_order' => ['required', 'integer', 'min:1'],
                'is_active' => ['nullable', 'boolean'],
            ],
            'sub_accounts' => [
                'account_id' => ['required', 'integer', 'exists:accounts,id'],
                'code' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::unique('sub_accounts', 'code')
                        ->where(fn ($query) => $query->where('account_id', $this->input('account_id')))
                        ->ignore($recordId),
                ],
                'name' => ['required', 'string', 'max:255'],
                'name_km' => ['nullable', 'string', 'max:255'],
                'sort_order' => ['required', 'integer', 'min:1'],
                'is_active' => ['nullable', 'boolean'],
            ],
            'programs' => [
                'code' => ['required', 'string', 'max:100', Rule::unique('programs', 'code')->ignore($recordId)],
                'name' => ['required', 'string', 'max:255'],
                'name_km' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'sort_order' => ['required', 'integer', 'min:1'],
                'is_active' => ['nullable', 'boolean'],
            ],
            'sub_programs' => [
                'program_id' => ['required', 'integer', 'exists:programs,id'],
                'code' => ['required', 'string', 'max:100', Rule::unique('sub_programs', 'code')->ignore($recordId)],
                'name' => ['required', 'string', 'max:255'],
                'name_km' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'sort_order' => ['required', 'integer', 'min:1'],
                'is_active' => ['nullable', 'boolean'],
            ],
            'activity_clusters' => [
                'sub_program_id' => ['required', 'integer', 'exists:sub_programs,id'],
                'code' => ['required', 'string', 'max:100', Rule::unique('activity_clusters', 'code')->ignore($recordId)],
                'name' => ['required', 'string', 'max:255'],
                'name_km' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'sort_order' => ['required', 'integer', 'min:1'],
                'is_active' => ['nullable', 'boolean'],
            ],
            'indicators' => [
                'program_id' => ['required', 'integer', 'exists:programs,id'],
                'sub_program_id' => ['required', 'integer', 'exists:sub_programs,id'],
                'activity_cluster_id' => ['required', 'integer', 'exists:activity_clusters,id'],
                'code' => ['required', 'string', 'max:100', Rule::unique('indicators', 'code')->ignore($recordId)],
                'name' => ['required', 'string', 'max:255'],
                'name_km' => ['nullable', 'string', 'max:255'],
                'unit_of_measure' => ['nullable', 'string', 'max:100'],
                'value_type' => ['required', Rule::in(['number', 'percentage', 'currency', 'text'])],
                'description' => ['nullable', 'string'],
                'sort_order' => ['required', 'integer', 'min:1'],
                'is_active' => ['nullable', 'boolean'],
            ],
            'funding_sources' => [
                'code' => ['required', 'string', 'max:255', Rule::unique('funding_sources', 'code')->ignore($recordId)],
                'name' => ['required', 'string', 'max:255'],
                'name_km' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'is_active' => ['nullable', 'boolean'],
            ],
            default => [],
        };
    }
}
