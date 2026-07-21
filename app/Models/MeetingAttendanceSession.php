<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class MeetingAttendanceSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'public_token',
        'is_active',
        'opens_at',
        'expires_at',
        'closed_at',
        'objetivo',
        'fecha',
        'lugar',
        'hora_inicio',
        'hora_terminacion',
        'template_version',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'opens_at' => 'datetime',
        'expires_at' => 'datetime',
        'closed_at' => 'datetime',
        'fecha' => 'date',
        'hora_inicio' => 'datetime:H:i',
        'hora_terminacion' => 'datetime:H:i',
    ];

    protected $appends = [
        'registration_status',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function entries()
    {
        return $this->hasMany(MeetingAttendanceEntry::class, 'session_id')->orderBy('sequence_number');
    }

    public function getRegistrationStatusAttribute(): string
    {
        return $this->registrationStatus();
    }

    public function registrationStatus(?Carbon $now = null): string
    {
        $now ??= now();

        if ($this->closed_at) {
            return 'closed';
        }

        if (!$this->is_active) {
            return 'inactive';
        }

        if ($this->opens_at && $now->lt($this->opens_at)) {
            return 'scheduled';
        }

        if ($this->expires_at && $now->gt($this->expires_at)) {
            return 'expired';
        }

        return 'open';
    }

    public function acceptsRegistrations(?Carbon $now = null): bool
    {
        return $this->registrationStatus($now) === 'open';
    }
}
