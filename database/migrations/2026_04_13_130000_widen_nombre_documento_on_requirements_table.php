<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE requirements MODIFY nombre_documento TEXT');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE requirements MODIFY nombre_documento VARCHAR(255)');
    }
};
