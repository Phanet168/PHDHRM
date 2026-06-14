<?php

namespace Modules\Planning\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class UpdateDailyActivityPlanRequest extends FormRequest
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
            'activities.*.goal_text' => ['nullable', 'string'],
            'activities.*.dates' => ['nullable', 'array'],
            'activities.*.dates.*' => ['nullable', 'date'],
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

            foreach ((array) $this->input('activities', []) as $index => $activity) {
                $itemId = (int) ($activity['item_id'] ?? 0);
                $item = $activityItems->get($itemId);

                if (!$item) {
                    $validator->errors()->add("activities.$index.item_id", 'សកម្មភាពនេះមិនស្ថិតក្នុងផែនការនេះទេ។');
                    continue;
                }

                $allowedMonths = $item->schedules
                    ->where('period_type', 'monthly')
                    ->pluck('month')
                    ->filter()
                    ->map(fn ($month) => (int) $month)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                foreach ((array) ($activity['dates'] ?? []) as $dateIndex => $date) {
                    if (blank($date)) {
                        continue;
                    }

                    try {
                        $parsedDate = Carbon::parse($date);
                    } catch (\Throwable $exception) {
                        $validator->errors()->add("activities.$index.dates.$dateIndex", 'កាលបរិច្ឆេទមិនត្រឹមត្រូវ។');
                        continue;
                    }

                    if ((int) $parsedDate->year !== (int) $plan->year) {
                        $validator->errors()->add("activities.$index.dates.$dateIndex", 'សូមជ្រើសថ្ងៃក្នុងឆ្នាំផែនការដូចគ្នា។');
                    }

                    if (!in_array((int) $parsedDate->month, $allowedMonths, true)) {
                        $validator->errors()->add(
                            "activities.$index.dates.$dateIndex",
                            'ថ្ងៃដែលបានជ្រើស មិនស្ថិតក្នុងខែដែលបានកំណត់នៅជំហានទី៤ទេ។'
                        );
                    }
                }
            }
        });
    }
}
