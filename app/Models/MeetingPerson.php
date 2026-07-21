<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeetingPerson extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_number',
        'document_number_normalized',
        'full_name',
        'organization_area',
        'phone',
        'email_or_address',
        'person_kind',
        'internal_source_type',
        'internal_source_id',
    ];

    public function attendanceEntries()
    {
        return $this->hasMany(MeetingAttendanceEntry::class, 'person_id')->latest('registered_at');
    }

    public function internalLabel(): string
    {
        return match ($this->internal_source_type) {
            'user' => 'Usuario',
            'specialist' => 'Especialista',
            'profesional_ambiental' => 'Profesional ambiental',
            default => 'Externo',
        };
    }
}
