<?php

namespace Modules\HumanResource\Support;

use Carbon\Carbon;
use Modules\HumanResource\Entities\EmployeeServiceHistory;

class EmployeeServiceHistoryService
{
    public function log(
        int $employeeId,
        string $eventType,
        string $title,
        ?string $details = null,
        ?string $eventDate = null,
        ?string $fromValue = null,
        ?string $toValue = null,
        ?string $referenceType = null,
        $referenceId = null,
        ?array $metadata = null
    ): EmployeeServiceHistory {
        $history = new EmployeeServiceHistory();
        $history->employee_id = $employeeId;
        $history->event_type = $eventType;
        $history->event_date = $this->normalizeDate($eventDate);
        $history->title = $title;
        $history->details = $details;
        $history->from_value = $fromValue;
        $history->to_value = $toValue;
        $history->reference_type = $referenceType;
        $history->reference_id = $referenceId;
        $history->metadata = $metadata;
        $history->save();

        return $history;
    }

    protected function normalizeDate(?string $date): string
    {
        if (!$date) {
            return now()->toDateString();
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable $th) {
            return now()->toDateString();
        }
    }
}
