<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailSetting extends Model
{
    protected $table = 'mail_settings';

    protected $fillable = [
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'from_address',
        'from_name',
        'ehlo_domain',
        'updated_by',
    ];

    protected $casts = [
        'username' => 'encrypted',
        'password' => 'encrypted',
        'from_address' => 'encrypted',
    ];
}

