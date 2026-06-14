<?php

namespace Modules\Planning\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Planning\Entities\PlanItem;

class UpdateMonthlyActivityPlanRequest extends FormRequest
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
            'activities' => ['required', 'array', 'min:1'],
            'activities.*.item_id' => ['required', 'integer', 'exists:plan_items,id'],
            'activities.*.months' => ['nullable', 'array'],
            'activities.*.months.*' => ['integer', 'between:1,12'],
            'activities.*.note' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $plan = $this->route('plan');
            if ($plan === null) {
                return;
            }

            $activityItems = $plan->items()
                ->where('item_type', 'activity')
                ->with('schedules')
                ->get()
                ->keyBy('id');

            $quarterMonthMap = [
                1 => [1, 2, 3],
                2 => [4, 5, 6],
                3 => [7, 8, 9],
                4 => [10, 11, 12],
            ];

            foreach ((array) $this->input('activities', []) as $index => $activity) {
                $itemId = (int) ($activity['item_id'] ?? 0);
                $item = $activityItems->get($itemId);

                if (!$item) {
                    $validator->errors()->add("activities.$index.item_id", 'សកម្មភាពនេះមិនមែនជាសកម្មភាពក្នុងផែនការនេះទេ។');
                    continue;
                }

                $allowedMonths = $item->schedules
                    ->where('period_type', 'quarterly')
                    ->pluck('quarter')
                    ->filter()
                    ->flatMap(fn ($quarter) => $quarterMonthMap[(int) $quarter] ?? [])
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                foreach ((array) ($activity['months'] ?? []) as $month) {
                    if (!in_array((int) $month, $allowedMonths, true)) {
                        $validator->errors()->add(
                            "activities.$index.months",
                            'ខែដែលបានជ្រើសមិនស្ថិតក្នុងត្រីមាសដែលបានកំណត់នៅជំហានទី៣ ទេ។'
                        );
                        break;
                    }
                }
            }
        });
    }
}
