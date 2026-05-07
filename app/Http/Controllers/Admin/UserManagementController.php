<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->integer('per_page', 10);
        if (!in_array($perPage, [10, 25, 50], true)) {
            $perPage = 10;
        }

        $users = User::query()
            ->with('roles')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = trim((string) $request->input('q'));
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', '%' . $q . '%')
                        ->orWhere('email', 'like', '%' . $q . '%');
                });
            })
            ->when($request->filled('role_id'), function ($query) use ($request) {
                $roleId = (int) $request->input('role_id');
                if ($roleId > 0) {
                    $query->whereHas('roles', fn ($inner) => $inner->where('roles.id', $roleId));
                }
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $roles = Role::query()->orderBy('name')->get();

        return view('admin.users.index', compact('users', 'roles'));
    }

    public function updateRoles(Request $request, User $user)
    {
        $data = $request->validate([
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,id'],
        ]);

        $roleIds = $data['roles'] ?? [];
        $user->roles()->sync($roleIds);

        $isAdminRole = Role::query()->whereIn('id', $roleIds)->where('slug', 'admin')->exists();
        $user->is_admin = $isAdminRole;
        $user->save();

        return back()->with('status', 'Roles actualizados para ' . $user->name . '.');
    }
}
