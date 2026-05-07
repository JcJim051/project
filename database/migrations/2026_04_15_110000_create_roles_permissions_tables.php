<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        $adminRoleId = DB::table('roles')->insertGetId([
            'name' => 'Administrador',
            'slug' => 'admin',
            'description' => 'Acceso total al sistema',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $editorRoleId = DB::table('roles')->insertGetId([
            'name' => 'Editor',
            'slug' => 'editor',
            'description' => 'Gestion operativa de proyectos',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $defaultPermissions = [
            ['name' => 'Ver proyectos', 'slug' => 'projects.view'],
            ['name' => 'Editar proyectos', 'slug' => 'projects.edit'],
            ['name' => 'Gestionar requisitos', 'slug' => 'requirements.manage'],
            ['name' => 'Gestionar Drive OAuth', 'slug' => 'drive.oauth.manage'],
            ['name' => 'Gestionar usuarios', 'slug' => 'users.manage'],
        ];

        foreach ($defaultPermissions as $permission) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => $permission['name'],
                'slug' => $permission['slug'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('permission_role')->insert([
                'permission_id' => $permissionId,
                'role_id' => $adminRoleId,
            ]);

            if (in_array($permission['slug'], ['projects.view', 'projects.edit', 'requirements.manage'], true)) {
                DB::table('permission_role')->insert([
                    'permission_id' => $permissionId,
                    'role_id' => $editorRoleId,
                ]);
            }
        }

        $adminUserIds = DB::table('users')->where('is_admin', true)->pluck('id');
        foreach ($adminUserIds as $userId) {
            DB::table('role_user')->insertOrIgnore([
                'role_id' => $adminRoleId,
                'user_id' => $userId,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};

