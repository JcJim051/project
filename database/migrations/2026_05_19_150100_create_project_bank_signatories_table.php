<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_bank_signatories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('role', 80);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('nombre', 255)->nullable();
            $table->string('cargo', 255)->nullable();
            $table->string('correo', 255)->nullable();
            $table->string('telefono', 60)->nullable();
            $table->timestamps();

            $table->index(['project_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_bank_signatories');
    }
};
