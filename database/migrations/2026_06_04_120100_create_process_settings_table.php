<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('require_planning_aim_approval')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('process_settings')->insert([
            'require_planning_aim_approval' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('roles')->updateOrInsert(
            ['slug' => 'planeacion_aim'],
            [
                'name' => 'Planeación AIM',
                'description' => 'Revisa y aprueba proyectos en la segunda llave interna de Planeación AIM.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('process_settings');
    }
};
