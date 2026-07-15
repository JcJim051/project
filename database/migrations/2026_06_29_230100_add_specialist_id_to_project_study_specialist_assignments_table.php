<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_study_specialist_assignments')) {
            return;
        }

        Schema::table('project_study_specialist_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('project_study_specialist_assignments', 'specialist_id')) {
                $table->foreignId('specialist_id')->nullable()->after('study_folder')->constrained('specialists')->nullOnDelete();
            }
        });

        if (Schema::hasTable('users') && Schema::hasTable('specialists')) {
            $rows = DB::table('project_study_specialist_assignments')
                ->whereNull('specialist_id')
                ->whereNotNull('user_id')
                ->get();

            foreach ($rows as $row) {
                $user = DB::table('users')->where('id', $row->user_id)->first();
                if (! $user || empty($user->email)) {
                    continue;
                }

                $specialistId = DB::table('specialists')->where('correo', $user->email)->value('id');
                if (! $specialistId) {
                    $specialistId = DB::table('specialists')->insertGetId([
                        'nombre' => $user->name ?? $user->email,
                        'correo' => $user->email,
                        'activo' => true,
                        'plane_sync_status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('project_study_specialist_assignments')
                    ->where('id', $row->id)
                    ->update([
                        'specialist_id' => $specialistId,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('project_study_specialist_assignments')) {
            return;
        }

        Schema::table('project_study_specialist_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('project_study_specialist_assignments', 'specialist_id')) {
                $table->dropConstrainedForeignId('specialist_id');
            }
        });
    }
};
