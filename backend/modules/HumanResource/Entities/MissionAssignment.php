<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MissionAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'mission_id',
        'employee_id',
        'status',
        'assignment_note',
        'name_on_order',
        'position_on_order',
        'sort_order',
        'honorific_on_order',
        'department_on_order',
    ];

    protected $casts = ['sort_order' => 'integer'];

    /** Professional prefixes used on official letters, matched against the employee's degree. */
    private const DEGREE_PREFIXES = [
        'វេជ្ជបណ្ឌិត' => 'វេជ្ជ.',
        'ទន្តបណ្ឌិត' => 'ទន្ត.',
        'ឱសថការី' => 'ឱសថ.',
    ];

    /**
     * Suggested form of address on the order ("លោក", "លោកស្រី", "លោកវេជ្ជ." ...).
     * Gender unknown → null rather than a guess; the Letter Manager can edit it.
     */
    public static function honorificFor(?Employee $employee): ?string
    {
        $base = match ((int) $employee?->gender_id) {
            1 => 'លោក',
            2 => 'លោកស្រី',
            default => null,
        };
        if ($base === null) {
            return null;
        }
        foreach (self::DEGREE_PREFIXES as $degree => $prefix) {
            if (str_contains((string) $employee->degree_name, $degree)) {
                return $base.$prefix;
            }
        }

        return $base;
    }

    public function mission()
    {
        return $this->belongsTo(Mission::class, 'mission_id', 'id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }
}
