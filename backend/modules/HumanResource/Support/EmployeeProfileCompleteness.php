<?php

namespace Modules\HumanResource\Support;

use Modules\HumanResource\Entities\Employee;

class EmployeeProfileCompleteness
{
    protected const CORE_FIELDS = [
        'first_name' => 'នាមខ្លួន',
        'last_name' => 'នាមត្រកូល',
        'gender_id' => 'ភេទ',
        'date_of_birth' => 'ថ្ងៃខែឆ្នាំកំណើត',
        'marital_status_id' => 'ស្ថានភាពគ្រួសារ',
        'phone' => 'លេខទូរស័ព្ទ',
        'birth_place_state_id' => 'ទីកន្លែងកំណើត - រាជធានី/ខេត្ត',
        'birth_place_city_id' => 'ទីកន្លែងកំណើត - ស្រុក/ខណ្ឌ',
        'birth_place_commune_id' => 'ទីកន្លែងកំណើត - ឃុំ/សង្កាត់',
        'birth_place_village_id' => 'ទីកន្លែងកំណើត - ភូមិ',
        'present_address_state_id' => 'អាសយដ្ឋានបច្ចុប្បន្ន - រាជធានី/ខេត្ត',
        'present_address_city_id' => 'អាសយដ្ឋានបច្ចុប្បន្ន - ស្រុក/ខណ្ឌ',
        'present_address_commune_id' => 'អាសយដ្ឋានបច្ចុប្បន្ន - ឃុំ/សង្កាត់',
        'present_address_village_id' => 'អាសយដ្ឋានបច្ចុប្បន្ន - ភូមិ',
        'emergency_contact_person' => 'អ្នកទំនាក់ទំនងបន្ទាន់',
        'emergency_contact_relationship' => 'ត្រូវជាអ្វីនឹងអ្នកទំនាក់ទំនងបន្ទាន់',
        'emergency_contact' => 'លេខទូរស័ព្ទទំនាក់ទំនងបន្ទាន់',
    ];

    public static function summary(Employee $employee): array
    {
        $total = count(self::CORE_FIELDS);
        $completed = 0;
        $missingLabels = [];

        foreach (self::CORE_FIELDS as $field => $label) {
            if (self::isFilled(data_get($employee, $field))) {
                $completed++;
                continue;
            }

            $missingLabels[] = $label;
        }

        $missingCount = count($missingLabels);

        return [
            'total' => $total,
            'completed' => $completed,
            'missing_count' => $missingCount,
            'missing_labels' => $missingLabels,
            'is_complete' => $missingCount === 0,
            'percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
        ];
    }

    protected static function isFilled($value): bool
    {
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return true;
        }

        if (is_array($value)) {
            return !empty($value);
        }

        if ($value instanceof \Countable) {
            return count($value) > 0;
        }

        return trim((string) $value) !== '';
    }
}
