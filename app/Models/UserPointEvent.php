<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPointEvent extends Model
{
    protected $fillable = [
        'user_id',
        'project_id',
        'requirement_id',
        'point_activity_id',
        'activity_code',
        'activity_name',
        'points',
        'season_year',
        'awarded_at',
        'uniqueness_scope',
        'event_key',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'awarded_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
