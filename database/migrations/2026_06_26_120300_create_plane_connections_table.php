<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plane_connections', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->default('Plane principal');
            $table->string('entorno', 30)->default('pruebas');
            $table->string('url_base');
            $table->string('workspace_id')->nullable();
            $table->string('auth_type', 50)->default('bearer_token');
            $table->string('oauth_token_url')->nullable();
            $table->string('healthcheck_path')->default('/api/health');
            $table->string('projects_path')->default('/api/projects');
            $table->string('modules_path_template')->default('/api/projects/{project_id}/modules');
            $table->string('states_path_template')->default('/api/projects/{project_id}/states');
            $table->string('project_url_template')->default('/projects/{project_id}');
            $table->string('api_key_header')->default('X-API-Key');
            $table->string('api_secret_header')->default('X-API-Secret');
            $table->text('api_key')->nullable();
            $table->text('api_secret')->nullable();
            $table->text('access_token')->nullable();
            $table->text('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->boolean('activo')->default(false);
            $table->unsignedInteger('timeout_segundos')->default(15);
            $table->timestamp('ultima_prueba_at')->nullable();
            $table->string('ultimo_estado_prueba', 50)->nullable();
            $table->text('ultimo_mensaje_prueba')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plane_connections');
    }
};
