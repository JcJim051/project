<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $roles = [
            ['name' => 'Administrador', 'slug' => 'admin', 'description' => 'Acceso total al sistema'],
            ['name' => 'Director', 'slug' => 'director', 'description' => 'Gestiona usuarios y operación del proceso'],
            ['name' => 'Formulador', 'slug' => 'formulador', 'description' => 'Gestión operativa de formulación'],
            ['name' => 'Estructurador', 'slug' => 'estructurador', 'description' => 'Gestión operativa de estructuración'],
            ['name' => 'Consulta', 'slug' => 'consulta', 'description' => 'Acceso solo lectura'],
            ['name' => 'Formulador Maestro', 'slug' => 'formulador_maestro', 'description' => 'Gestiona la parametrización funcional'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        $editorRoleId = DB::table('roles')->where('slug', 'editor')->value('id');
        $formuladorRoleId = DB::table('roles')->where('slug', 'formulador')->value('id');

        if ($editorRoleId && $formuladorRoleId) {
            $editorUsers = DB::table('role_user')
                ->where('role_id', $editorRoleId)
                ->pluck('user_id');

            foreach ($editorUsers as $userId) {
                DB::table('role_user')->where('user_id', $userId)->delete();
                DB::table('role_user')->insertOrIgnore([
                    'role_id' => $formuladorRoleId,
                    'user_id' => $userId,
                ]);
            }

            DB::table('permission_role')->where('role_id', $editorRoleId)->delete();
            DB::table('roles')->where('id', $editorRoleId)->delete();
        }

        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
        if ($adminRoleId) {
            $adminUserIds = DB::table('role_user')->where('role_id', $adminRoleId)->pluck('user_id');
            if ($adminUserIds->isNotEmpty()) {
                DB::table('users')->whereIn('id', $adminUserIds)->update(['is_admin' => true]);
            }
        }

        $nonAdminUserIds = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.slug', '!=', 'admin')
            ->pluck('role_user.user_id')
            ->unique();

        if ($nonAdminUserIds->isNotEmpty()) {
            DB::table('users')->whereIn('id', $nonAdminUserIds)->update(['is_admin' => false]);
        }
    }

    public function down(): void
    {
        // Sin reversa automática para no perder decisiones de asignación de roles en producción.
    }
};
