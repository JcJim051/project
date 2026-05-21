<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_bank_activity_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('producto_mga', 255)->nullable();
            $table->string('actividad', 500);
            $table->decimal('valor_actividad', 16, 2)->nullable();
            $table->boolean('ene')->default(false);
            $table->boolean('feb')->default(false);
            $table->boolean('mar')->default(false);
            $table->boolean('abr')->default(false);
            $table->boolean('may')->default(false);
            $table->boolean('jun')->default(false);
            $table->boolean('jul')->default(false);
            $table->boolean('ago')->default(false);
            $table->boolean('sep')->default(false);
            $table->boolean('oct')->default(false);
            $table->boolean('nov')->default(false);
            $table->boolean('dic')->default(false);
            $table->timestamps();

            $table->index(['project_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_bank_activity_rows');
    }
};
