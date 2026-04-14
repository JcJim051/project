<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Importar requisitos') }}
            </h2>
            <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                @if (session('status'))
                    <div class="rounded-md bg-emerald-50 p-4 text-emerald-700 text-sm">
                        {{ session('status') }}
                    </div>
                @endif

                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Carga masiva de requisitos</h3>
                    <p class="text-sm text-gray-600">Importa por actualización segura o actualización con creación de nuevos.</p>
                    <p class="text-sm text-gray-500">Actualmente hay <span class="font-semibold">{{ $count }}</span> requisitos cargados.</p>
                </div>

                <form method="POST" action="{{ route('requirements.import.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Archivo de requisitos (xlsm/xlsx/csv)</label>
                        <input type="file" name="archivo" class="mt-1 w-full rounded-md border-gray-300" required>
                        @error('archivo')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Modo de importación</label>
                        <label class="flex items-start gap-2 text-sm text-gray-700">
                            <input type="radio" name="import_mode" value="strict_update" class="mt-1 border-gray-300"
                                {{ old('import_mode', 'strict_update') === 'strict_update' ? 'checked' : '' }}>
                            <span>
                                <span class="font-medium">Actualizar existentes (estricto)</span>
                                <span class="block text-xs text-gray-500">Requiere ID en todas las filas. No crea nuevos.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2 text-sm text-gray-700">
                            <input type="radio" name="import_mode" value="update_or_create" class="mt-1 border-gray-300"
                                {{ old('import_mode') === 'update_or_create' ? 'checked' : '' }}>
                            <span>
                                <span class="font-medium">Actualizar + crear nuevos</span>
                                <span class="block text-xs text-gray-500">Actualiza por ID y crea cuando no exista.</span>
                            </span>
                        </label>
                        @error('import_mode')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="replace_all" value="1" class="rounded border-gray-300" {{ old('replace_all') ? 'checked' : '' }}>
                        Borrar requisitos existentes y cargar desde cero
                    </label>
                    <div class="flex items-center justify-between gap-3">
                        <button class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700" type="submit">
                            Importar requisitos
                        </button>
                        <a href="{{ route('requirements.export') }}" class="px-4 py-2 text-sm font-medium text-indigo-600 border border-indigo-200 rounded-md hover:bg-indigo-50">
                            Descargar requisitos
                        </a>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6 mt-6">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-lg font-semibold text-gray-800">Documentos cargados</h3>
                    <span class="text-xs text-gray-500">{{ $documents->count() }} documentos</span>
                </div>
                @if ($documents->isEmpty())
                    <p class="text-sm text-gray-500">Aún no hay documentos cargados.</p>
                @else
                    <div class="max-h-80 overflow-y-auto border border-gray-100 rounded-md">
                        <ul class="divide-y divide-gray-100">
                            @foreach ($documents as $doc)
                                <li class="px-4 py-2 text-sm text-gray-700 flex items-center justify-between gap-4">
                                    <span class="font-medium">{{ $doc->nombre_documento }}</span>
                                    <span class="text-xs text-gray-500">{{ $doc->carpeta ?: 'Sin carpeta' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
