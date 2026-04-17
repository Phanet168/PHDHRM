<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class NoticeDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'notice_id',
        'user_id',
        'channel',
        'status',
        'error_message',
        'payload',
        'sent_at',
        'read_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function notice()
    {
        return $this->belongsTo(Notice::class, 'notice_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}

