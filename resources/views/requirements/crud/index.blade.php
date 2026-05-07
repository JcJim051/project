<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Requisitos') }}
                </h2>
                <p class="text-sm text-gray-500">Edición manual de la tabla unificada.</p>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <a href="{{ route('requirements.crud.create') }}" class="text-indigo-600 hover:text-indigo-800">Nuevo requisito</a>
                <a href="{{ route('requirements.import') }}" class="text-gray-500 hover:text-gray-700">Importar</a>
                <a href="{{ route('requirements.export') }}" class="text-gray-500 hover:text-gray-700">Exportar</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-emerald-50 p-4 text-emerald-700 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="GET" action="{{ route('requirements.crud.index') }}" class="mb-4 grid gap-3 md:grid-cols-5">
                    <input
                        type="text"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Buscar texto, documento, carpeta o ID"
                        class="md:col-span-2 rounded-md border-gray-300 text-sm"
                    />
                    <select name="visible" class="rounded-md border-gray-300 text-sm">
                        <option value="">Visible: todos</option>
                        <option value="1" @selected(request('visible') === '1')>Visible: SI</option>
                        <option value="0" @selected(request('visible') === '0')>Visible: NO</option>
                    </select>
                    <select name="requiere_check" class="rounded-md border-gray-300 text-sm">
                        <option value="">Requisito: todos</option>
                        <option value="SI" @selected(strtoupper((string) request('requiere_check')) === 'SI')>Requisito: SI</option>
                        <option value="NO" @selected(strtoupper((string) request('requiere_check')) === 'NO')>Requisito: NO</option>
                    </select>
                    <div class="flex items-center gap-2">
                        <select name="per_page" class="rounded-md border-gray-300 text-sm">
                            @foreach ([10, 25, 50] as $size)
                                <option value="{{ $size }}" @selected((int) request('per_page', 10) === $size)>{{ $size }}/pag</option>
                            @endforeach
                        </select>
                        <button class="rounded-md bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700">Aplicar</button>
                        <a href="{{ route('requirements.crud.index') }}" class="rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50">Limpiar</a>
                    </div>
                </form>

                <div class="overflow-x-auto">
                    <table id="requirements-table" class="min-w-full text-sm">
                        <thead>
                            <tr class="text-xs text-gray-500 uppercase border-b">
                                @php
                                    $currentSort = request('sort', 'id');
                                    $currentDirection = request('direction', 'asc');
                                    $sortLink = function (string $field) use ($currentSort, $currentDirection) {
                                        $nextDir = ($currentSort === $field && $currentDirection === 'asc') ? 'desc' : 'asc';
                                        return route('requirements.crud.index', array_merge(request()->query(), ['sort' => $field, 'direction' => $nextDir]));
                                    };
                                @endphp
                                <th class="text-left py-2 px-3"><a href="{{ $sortLink('source_id') }}" class="hover:text-gray-700">ID</a></th>
                                <th class="text-left py-2 px-3">Texto</th>
                                <th class="text-left py-2 px-3"><a href="{{ $sortLink('nombre_documento') }}" class="hover:text-gray-700">Documento</a></th>
                                <th class="text-left py-2 px-3"><a href="{{ $sortLink('carpeta') }}" class="hover:text-gray-700">Carpeta</a></th>
                                <th class="text-left py-2 px-3"><a href="{{ $sortLink('visible') }}" class="hover:text-gray-700">Visible</a></th>
                                <th class="text-left py-2 px-3"><a href="{{ $sortLink('requiere_check') }}" class="hover:text-gray-700">Requisito</a></th>
                                <th class="text-right py-2 px-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse ($requirements as $req)
                                <tr>
                                    <td class="py-2 px-3 text-gray-500">{{ $req->source_id }}</td>
                                    <td class="py-2 px-3 text-gray-700">{{ $req->texto ?: $req->requisito }}</td>
                                    <td class="py-2 px-3 text-gray-700">{{ $req->nombre_documento }}</td>
                                    <td class="py-2 px-3 text-gray-500">{{ $req->carpeta }}</td>
                                    <td class="py-2 px-3">
                                        <form method="POST" action="{{ route('requirements.crud.toggle_visible', $req) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="visible" value="{{ $req->visible ? '0' : '1' }}">
                                            <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                                                <input type="checkbox" class="rounded border-gray-300" onchange="this.form.submit()"
                                                    {{ $req->visible ? 'checked' : '' }}>
                                                <span>{{ $req->visible ? 'SI' : 'NO' }}</span>
                                            </label>
                                        </form>
                                    </td>
                                    <td class="py-2 px-3">
                                        <form method="POST" action="{{ route('requirements.crud.toggle_check', $req) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="requiere_check" value="{{ strtoupper((string) $req->requiere_check) === 'SI' ? 'NO' : 'SI' }}">
                                            <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                                                <input type="checkbox" class="rounded border-gray-300" onchange="this.form.submit()"
                                                    {{ strtoupper((string) $req->requiere_check) === 'SI' ? 'checked' : '' }}>
                                                <span>{{ strtoupper((string) $req->requiere_check) === 'SI' ? 'SI' : 'NO' }}</span>
                                            </label>
                                        </form>
                                    </td>
                                    <td class="py-2 px-3 text-right">
                                        <a href="{{ route('requirements.crud.edit', $req) }}" class="text-indigo-600 hover:text-indigo-700">Editar</a>
                                        <form method="POST" action="{{ route('requirements.crud.destroy', $req) }}" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-rose-600 hover:text-rose-700 ms-3">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-6 px-3 text-center text-sm text-gray-500">No se encontraron requisitos con esos filtros.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    {{ $requirements->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
