<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('specialists')) {
            return;
        }

        Schema::create('specialists', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('correo')->unique();
            $table->string('telefono')->nullable();
            $table->string('especialidad')->nullable();
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);
            $table->string('plane_user_id')->nullable();
            $table->string('plane_sync_status')->nullable();
            $table->timestamp('plane_last_synced_at')->nullable();
            $table->text('plane_last_error')->nullable();
            $table->timestamps();

            $table->index(['activo', 'especialidad']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialists');
    }
};
