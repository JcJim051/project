<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamOnboardingRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'requested_role',
        'document_number',
        'document_number_normalized',
        'full_name',
        'phone',
        'email',
        'municipio',
        'organization_area',
        'specialty',
        'notes',
        'status',
        'review_notes',
        'approved_user_id',
        'approved_at',
        'rejected_user_id',
        'rejected_at',
        'created_user_id',
        'created_specialist_id',
        'submitted_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(TeamOnboardingCampaign::class, 'campaign_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_user_id');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_user_id');
    }

    public function createdUser()
    {
        return $this->belongsTo(User::class, 'created_user_id');
    }

    public function createdSpecialist()
    {
        return $this->belongsTo(Specialist::class, 'created_specialist_id');
    }

    public function requestedRoleLabel(): string
    {
        return match ($this->requested_role) {
            'formulador' => 'Formulador',
            'estructurador' => 'Estructurador',
            'especialista' => 'Especialista',
            default => ucfirst((string) $this->requested_role),
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Pendiente',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
            default => ucfirst((string) $this->status),
        };
    }
}
