<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlaneConnection extends Model
{
    protected $fillable = [
        'nombre',
        'entorno',
        'url_base',
        'workspace_id',
        'auth_type',
        'oauth_token_url',
        'healthcheck_path',
        'projects_path',
        'modules_path_template',
        'states_path_template',
        'labels_path_template',
        'cycles_path_template',
        'cycle_issues_path_template',
        'issues_path_template',
        'issue_detail_path_template',
        'project_url_template',
        'api_key_header',
        'api_secret_header',
        'api_key',
        'api_secret',
        'access_token',
        'client_id',
        'client_secret',
        'activo',
        'timeout_segundos',
        'ultima_prueba_at',
        'ultimo_estado_prueba',
        'ultimo_mensaje_prueba',
        'updated_by',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'api_secret' => 'encrypted',
        'access_token' => 'encrypted',
        'client_id' => 'encrypted',
        'client_secret' => 'encrypted',
        'activo' => 'boolean',
        'timeout_segundos' => 'integer',
        'ultima_prueba_at' => 'datetime',
    ];
}
