<?php

namespace Modules\Planning\Http\Requests;

class UpdatePlanRequest extends StorePlanRequest
{
    public function authorize(): bool
    {
        $plan = $this->route('plan');

        return $this->user() !== null
            && $plan !== null
            && $this->user()->can('update', $plan);
    }
}
