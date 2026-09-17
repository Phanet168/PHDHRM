<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Correspondence\Entities\CorrespondenceLetter;

class MissionDocument extends Model
{
    protected $fillable = ['mission_id', 'name', 'path', 'mime_type', 'size', 'uploaded_by',
        'document_kind', 'mission_report_id', 'correspondence_letter_id', 'reference_number',
        'reference_date', 'reference_issuer', 'sort_order'];

    protected $hidden = ['path'];

    protected $casts = ['size' => 'integer', 'sort_order' => 'integer', 'reference_date' => 'date'];

    public function mission()
    {
        return $this->belongsTo(Mission::class);
    }

    public function report()
    {
        return $this->belongsTo(MissionReport::class, 'mission_report_id');
    }

    public function correspondenceLetter()
    {
        return $this->belongsTo(CorrespondenceLetter::class);
    }

    public function uploader()
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by');
    }
}
