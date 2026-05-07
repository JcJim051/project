<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleManagementController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->integer('per_page', 10);
        if (!in_array($perPage, [10, 25, 50], true)) {
            $perPage = 10;
        }

        $roles = Role::query()
            ->with('permissions')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = trim((string) $request->input('q'));
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', '%' . $q . '%')
                        ->orWhere('slug', 'like', '%' . $q . '%')
                        ->orWhere('description', 'like', '%' . $q . '%');
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $permissions = Permission::query()->orderBy('name')->get();

        return view('admin.roles.index', compact('roles', 'permissions'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:roles,slug'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $slug = Str::slug($data['slug'] ?: $data['name']);
        Role::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
        ]);

        return back()->with('status', 'Rol creado correctamente.');
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('roles', 'slug')->ignore($role->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $role->update([
            'name' => $data['name'],
            'slug' => Str::slug($data['slug'] ?: $data['name']),
            'description' => $data['description'] ?? null,
        ]);

        return back()->with('status', 'Rol actualizado.');
    }

    public function destroy(Role $role)
    {
        if ($role->slug === 'admin') {
            return back()->withErrors(['role' => 'No se puede eliminar el rol admin.']);
        }

        $role->delete();

        return back()->with('status', 'Rol eliminado.');
    }

    public function syncPermissions(Request $request, Role $role)
    {
        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role->permissions()->sync($data['permissions'] ?? []);

        return back()->with('status', 'Permisos actualizados para el rol ' . $role->name . '.');
    }
}
