<?php

namespace Modules\Planning\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Planning\Entities\PlanItem;

class UpdateMicroPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plan = $this->route('plan');

        return $this->user() !== null
            && $plan !== null
            && $this->user()->can('update', $plan);
    }

    public function rules(): array
    {
        return [
            'activities' => ['nullable', 'array'],
            'activities.*.indicator_id' => ['required', 'integer', 'exists:indicators,id'],
            'activities.*.responsible_org_unit_id' => ['required', 'integer', 'exists:org_units,id'],
            'activities.*.item_code' => ['nullable', 'string', 'max:100'],
            'activities.*.title' => ['required', 'string', 'max:255'],
            'activities.*.description' => ['nullable', 'string'],
            'activities.*.costs' => ['required', 'array', 'min:1'],
            'activities.*.costs.*.chapter_id' => ['required', 'integer', 'exists:chapters,id'],
            'activities.*.costs.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'activities.*.costs.*.sub_account_id' => ['required', 'integer', 'exists:sub_accounts,id'],
            'activities.*.costs.*.funding_source_id' => ['nullable', 'integer', 'exists:funding_sources,id'],
            'activities.*.costs.*.cost_code' => ['nullable', 'string', 'max:100'],
            'activities.*.costs.*.cost_name' => ['nullable', 'string', 'max:255'],
            'activities.*.costs.*.qty' => ['required', 'numeric', 'min:0'],
            'activities.*.costs.*.implementer_count' => ['required', 'numeric', 'min:0'],
            'activities.*.costs.*.occurrence_count' => ['required', 'numeric', 'min:0'],
            'activities.*.costs.*.unit' => ['nullable', 'string', 'max:100'],
            'activities.*.costs.*.unit_price' => ['required', 'numeric', 'min:0'],
            'activities.*.costs.*.currency_code' => ['required', 'string', 'max:10'],
            'activities.*.costs.*.note' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $plan = $this->route('plan');
            if ($plan === null) {
                return;
            }

            $allowedIndicatorIds = $plan->items()
                ->where('item_type', 'indicator_result')
                ->with('indicators')
                ->get()
                ->map(fn (PlanItem $item) => optional($item->indicators->first())->indicator_id)
                ->filter()
                ->map(fn ($id) => (string) $id)
                ->values()
                ->all();

            foreach ((array) $this->input('activities', []) as $index => $activity) {
                $indicatorId = (string) ($activity['indicator_id'] ?? '');
                if ($indicatorId !== '' && !in_array($indicatorId, $allowedIndicatorIds, true)) {
                    $validator->errors()->add(
                        "activities.$index.indicator_id",
                        'សូចនាករនេះមិនមែនជាសូចនាករដែលបានកំណត់នៅជំហានទី១ ទេ។ សូមជ្រើសតែសូចនាករពីជំហានទី១ ប៉ុណ្ណោះ។'
                    );
                }

                $title = trim((string) ($activity['title'] ?? ''));
                if ($indicatorId === '' || $title === '') {
                    continue;
                }

                $duplicateCount = collect((array) $this->input('activities', []))
                    ->filter(function ($row) use ($indicatorId, $title) {
                        return (string) ($row['indicator_id'] ?? '') === $indicatorId
                            && trim((string) ($row['title'] ?? '')) === $title;
                    })
                    ->count();

                if ($duplicateCount > 1) {
                    $validator->errors()->add(
                        "activities.$index.title",
                        'មិនអាចបញ្ចូលសកម្មភាពស្ទួនគ្នាក្រោមសូចនាករដូចគ្នាបានទេ។'
                    );
                }
            }
        });
    }
}
