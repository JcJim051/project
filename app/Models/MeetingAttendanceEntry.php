<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeetingAttendanceEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'person_id',
        'document_number',
        'document_number_normalized',
        'full_name',
        'organization_area',
        'phone',
        'email_or_address',
        'signature_path',
        'sequence_number',
        'registered_at',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(MeetingAttendanceSession::class, 'session_id');
    }

    public function person()
    {
        return $this->belongsTo(MeetingPerson::class, 'person_id');
    }
}
