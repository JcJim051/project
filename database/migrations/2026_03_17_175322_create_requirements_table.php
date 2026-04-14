<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('requirements', function (Blueprint $table) {
            $table->id();
            $table->string('nivel1');
            $table->string('nivel2')->nullable();
            $table->string('nivel3')->nullable();
            $table->string('archivo');
            $table->string('extension')->nullable();
            $table->boolean('aplica')->default(true);
            $table->string('coordinador')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requirements');
    }
};
