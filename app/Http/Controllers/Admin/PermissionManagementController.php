<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PermissionManagementController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->integer('per_page', 10);
        if (!in_array($perPage, [10, 25, 50], true)) {
            $perPage = 10;
        }

        $permissions = Permission::query()
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

        return view('admin.permissions.index', compact('permissions'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:permissions,slug'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        Permission::query()->create([
            'name' => $data['name'],
            'slug' => Str::slug($data['slug'] ?: $data['name'], '.'),
            'description' => $data['description'] ?? null,
        ]);

        return back()->with('status', 'Permiso creado.');
    }

    public function update(Request $request, Permission $permission)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('permissions', 'slug')->ignore($permission->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $permission->update([
            'name' => $data['name'],
            'slug' => Str::slug($data['slug'] ?: $data['name'], '.'),
            'description' => $data['description'] ?? null,
        ]);

        return back()->with('status', 'Permiso actualizado.');
    }

    public function destroy(Permission $permission)
    {
        $permission->delete();

        return back()->with('status', 'Permiso eliminado.');
    }
}
