<?php

namespace Modules\Planning\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Planning\Entities\PlanItem;

class UpdateActivityPlanRequest extends FormRequest
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
            'activities.*.quarters' => ['nullable', 'array'],
            'activities.*.quarters.*' => ['integer', 'in:1,2,3,4'],
            'activities.*.planned_quantity' => ['nullable', 'numeric', 'min:0'],
            'activities.*.period_label' => ['nullable', 'string', 'max:255'],
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

            $allowedItemIds = $plan->items()
                ->where('item_type', 'activity')
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();

            foreach ((array) $this->input('activities', []) as $index => $activity) {
                $itemId = (string) ($activity['item_id'] ?? '');
                if ($itemId === '' || in_array($itemId, $allowedItemIds, true)) {
                    continue;
                }

                $validator->errors()->add(
                    "activities.$index.item_id",
                    'សកម្មភាពនេះមិនមែនជាសកម្មភាពដែលបានកំណត់ក្នុងជំហានទី២ ទេ។'
                );
            }
        });
    }
}
