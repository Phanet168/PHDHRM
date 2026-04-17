<?php

namespace Modules\Correspondence\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CorrespondenceLetterAction extends Model
{
    use SoftDeletes;

    protected $table = 'correspondence_letter_actions';

    protected $fillable = [
        'letter_id',
        'step_key',
        'action_type',
        'acted_by',
        'target_user_id',
        'target_department_id',
        'note',
        'meta_json',
        'acted_at',
    ];

    protected $casts = [
        'meta_json' => 'array',
        'acted_at' => 'datetime',
    ];

    public function letter()
    {
        return $this->belongsTo(CorrespondenceLetter::class, 'letter_id');
    }
}

