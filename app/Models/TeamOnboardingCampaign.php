<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class TeamOnboardingCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'public_token',
        'is_active',
        'opens_at',
        'expires_at',
        'closed_at',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'opens_at' => 'datetime',
        'expires_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected $appends = [
        'registration_status',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function requests()
    {
        return $this->hasMany(TeamOnboardingRequest::class, 'campaign_id')->latest('submitted_at');
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
