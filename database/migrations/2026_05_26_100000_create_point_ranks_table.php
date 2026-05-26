<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_ranks', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('level_order')->unique();
            $table->string('name');
            $table->unsignedInteger('min_points');
            $table->string('image_path')->nullable();
            $table->boolean('enabled')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('point_ranks')->insert([
            ['level_order' => 1, 'name' => 'Recluta UNSC', 'min_points' => 0, 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['level_order' => 2, 'name' => 'Cadete ODST', 'min_points' => 150, 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['level_order' => 3, 'name' => 'Oficial Orbital', 'min_points' => 350, 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['level_order' => 4, 'name' => 'Comandante Noble', 'min_points' => 650, 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['level_order' => 5, 'name' => 'Spartan Operativo', 'min_points' => 1000, 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('point_ranks');
    }
};

