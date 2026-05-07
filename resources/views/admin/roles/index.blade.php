<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Roles y Permisos</h2>
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

            <div class="bg-white shadow rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-800">Crear rol</h3>
                <form method="POST" action="{{ route('admin.roles.store') }}" class="mt-3 grid gap-3 md:grid-cols-3">
                    @csrf
                    <input name="name" placeholder="Nombre" class="rounded-md border-gray-300" required>
                    <input name="slug" placeholder="Slug (opcional)" class="rounded-md border-gray-300">
                    <input name="description" placeholder="Descripcion (opcional)" class="rounded-md border-gray-300">
                    <div class="md:col-span-3">
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-700">Crear rol</button>
                    </div>
                </form>
            </div>

            <div class="space-y-4">
                <form method="GET" action="{{ route('admin.roles.index') }}" class="bg-white shadow rounded-lg p-4 grid gap-3 md:grid-cols-4">
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar rol por nombre, slug o descripcion" class="md:col-span-2 rounded-md border-gray-300 text-sm">
                    <select name="per_page" class="rounded-md border-gray-300 text-sm">
                        @foreach ([10, 25, 50] as $size)
                            <option value="{{ $size }}" @selected((int) request('per_page', 10) === $size)>{{ $size }}/pag</option>
                        @endforeach
                    </select>
                    <div class="flex items-center gap-2">
                        <button class="rounded-md bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700">Aplicar</button>
                        <a href="{{ route('admin.roles.index') }}" class="rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50">Limpiar</a>
                    </div>
                </form>

                @forelse ($roles as $role)
                    <div class="bg-white shadow rounded-lg p-4 space-y-3">
                        <div class="grid gap-3 md:grid-cols-4">
                            <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="md:col-span-3 grid gap-3 md:grid-cols-3">
                                @csrf
                                @method('PUT')
                                <input name="name" value="{{ $role->name }}" class="rounded-md border-gray-300" required>
                                <input name="slug" value="{{ $role->slug }}" class="rounded-md border-gray-300">
                                <input name="description" value="{{ $role->description }}" class="rounded-md border-gray-300">
                                <div class="md:col-span-3">
                                    <button class="rounded-md bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-700">Actualizar</button>
                                </div>
                            </form>
                            <div class="md:col-span-1 flex items-end">
                                @if ($role->slug !== 'admin')
                                    <form method="POST" action="{{ route('admin.roles.destroy', $role) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded-md bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Eliminar</button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        <form method="POST" action="{{ route('admin.roles.permissions.sync', $role) }}" class="space-y-2">
                            @csrf
                            @method('PUT')
                            <div class="text-xs font-semibold text-gray-500 uppercase">Permisos del rol</div>
                            <div class="flex flex-wrap gap-3">
                                @foreach ($permissions as $permission)
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($role->permissions->contains('id', $permission->id))>
                                        <span>{{ $permission->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <button class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">Guardar permisos</button>
                        </form>
                    </div>
                @empty
                    <div class="bg-white shadow rounded-lg p-6 text-center text-sm text-gray-500">
                        No se encontraron roles.
                    </div>
                @endforelse
                <div>
                    {{ $roles->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
