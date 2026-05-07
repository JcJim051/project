<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriveOAuthSetting extends Model
{
    protected $table = 'drive_oauth_settings';

    protected $fillable = [
        'client_id',
        'client_secret',
        'redirect_uri',
        'updated_by',
    ];

    protected $casts = [
        'client_id' => 'encrypted',
        'client_secret' => 'encrypted',
    ];
}

