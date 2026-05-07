<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Gestion de Usuarios</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="bg-white shadow rounded-lg p-4 overflow-x-auto">
                <div class="mb-3 text-sm text-gray-500">Asigna roles por usuario.</div>
                <form method="GET" action="{{ route('admin.users.index') }}" class="mb-4 grid gap-3 md:grid-cols-5">
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar por nombre o email" class="md:col-span-2 rounded-md border-gray-300 text-sm">
                    <select name="role_id" class="rounded-md border-gray-300 text-sm">
                        <option value="">Rol: todos</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected((int) request('role_id') === $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <select name="per_page" class="rounded-md border-gray-300 text-sm">
                        @foreach ([10, 25, 50] as $size)
                            <option value="{{ $size }}" @selected((int) request('per_page', 10) === $size)>{{ $size }}/pag</option>
                        @endforeach
                    </select>
                    <div class="flex items-center gap-2">
                        <button class="rounded-md bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700">Aplicar</button>
                        <a href="{{ route('admin.users.index') }}" class="rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50">Limpiar</a>
                    </div>
                </form>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="text-left text-xs font-semibold text-gray-500 uppercase">
                            <th class="py-2 pr-3">Nombre</th>
                            <th class="py-2 pr-3">Email</th>
                            <th class="py-2 pr-3">Roles</th>
                            <th class="py-2 pr-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($users as $user)
                            <tr>
                                <td class="py-3 pr-3 text-sm font-semibold text-gray-800">{{ $user->name }}</td>
                                <td class="py-3 pr-3 text-sm text-gray-600">{{ $user->email }}</td>
                                <td class="py-3 pr-3">
                                    <form method="POST" action="{{ route('admin.users.roles.update', $user) }}" class="space-y-2">
                                        @csrf
                                        @method('PUT')
                                        <div class="flex flex-wrap gap-3">
                                            @foreach ($roles as $role)
                                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                                    <input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked($user->roles->contains('id', $role->id))>
                                                    <span>{{ $role->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">
                                            Guardar
                                        </button>
                                    </form>
                                </td>
                                <td class="py-3 pr-3 text-xs text-gray-500">{{ $user->is_admin ? 'Admin' : '' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-sm text-gray-500">No se encontraron usuarios.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">
                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
