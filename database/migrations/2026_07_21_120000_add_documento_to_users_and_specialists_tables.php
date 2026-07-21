<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (!Schema::hasColumn('users', 'documento')) {
                $table->string('documento', 100)->nullable()->after('email');
                $table->index('documento');
            }
        });

        Schema::table('specialists', function (Blueprint $table): void {
            if (!Schema::hasColumn('specialists', 'documento')) {
                $table->string('documento', 100)->nullable()->after('correo');
                $table->index('documento');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'documento')) {
                $table->dropIndex(['documento']);
                $table->dropColumn('documento');
            }
        });

        Schema::table('specialists', function (Blueprint $table): void {
            if (Schema::hasColumn('specialists', 'documento')) {
                $table->dropIndex(['documento']);
                $table->dropColumn('documento');
            }
        });
    }
};
