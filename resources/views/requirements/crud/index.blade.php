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
                <div class="overflow-x-auto">
                    <table id="requirements-table" class="min-w-full text-sm">
                        <thead>
                            <tr class="text-xs text-gray-500 uppercase border-b">
                                <th class="text-left py-2 px-3">ID</th>
                                <th class="text-left py-2 px-3">Texto</th>
                                <th class="text-left py-2 px-3">Documento</th>
                                <th class="text-left py-2 px-3">Carpeta</th>
                                <th class="text-left py-2 px-3">Visible</th>
                                <th class="text-left py-2 px-3">Requisito</th>
                                <th class="text-right py-2 px-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($requirements as $req)
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
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const table = document.getElementById('requirements-table');
            if (table && window.jQuery) {
                jQuery('#requirements-table').DataTable({
                    pageLength: 10,
                    order: [[0, 'asc']],
                    language: {
                        search: "Buscar:",
                        lengthMenu: "Mostrar _MENU_",
                        info: "Mostrando _START_ a _END_ de _TOTAL_",
                        infoEmpty: "Sin registros",
                        zeroRecords: "Sin resultados",
                        paginate: {
                            previous: "Anterior",
                            next: "Siguiente"
                        }
                    }
                });
            }
        });
    </script>
</x-app-layout>
