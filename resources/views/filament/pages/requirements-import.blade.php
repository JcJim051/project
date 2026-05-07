<x-filament-panels::page>
    <div class="space-y-4">
        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <p class="text-sm text-gray-600 mb-4">
                Carga el archivo unificado en formato Excel/CSV. Puedes usar modo estricto o modo actualizar+crear.
            </p>

            <form method="POST" action="{{ route('requirements.import.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-700">Archivo</label>
                    <input
                        type="file"
                        name="archivo"
                        accept=".xlsx,.xls,.xlsm,.csv"
                        required
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm"
                    >
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Modo de importación</label>
                    <select name="import_mode" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                        <option value="update_or_create">Actualizar + crear</option>
                        <option value="strict_update">Modo estricto (solo actualizar IDs existentes)</option>
                    </select>
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="replace_all" value="1" class="rounded border-gray-300">
                    <span>Borrar y cargar desde cero</span>
                </label>

                <div class="flex items-center gap-2">
                    <x-filament::button type="submit">
                        Importar
                    </x-filament::button>
                    <x-filament::button tag="a" href="{{ route('filament.admin.resources.requirements.index') }}" color="gray">
                        Volver a Requisitos
                    </x-filament::button>
                </div>
            </form>
        </div>
    </div>
</x-filament-panels::page>

