<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $requirement->exists ? __('Editar requisito') : __('Nuevo requisito') }}
            </h2>
            <a href="{{ route('requirements.crud.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ $requirement->exists ? route('requirements.crud.update', $requirement) : route('requirements.crud.store') }}" class="bg-white shadow-sm sm:rounded-lg">
                @csrf
                @if ($requirement->exists)
                    @method('PUT')
                @endif

                <div class="p-6 space-y-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ID origen</label>
                            <input name="source_id" value="{{ old('source_id', $requirement->source_id) }}" class="mt-1 w-full rounded-md border-gray-300" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Código interno</label>
                            <input name="codigo_interno" value="{{ old('codigo_interno', $requirement->codigo_interno) }}" class="mt-1 w-full rounded-md border-gray-300" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Código norma</label>
                            <input name="codigo_norma" value="{{ old('codigo_norma', $requirement->codigo_norma) }}" class="mt-1 w-full rounded-md border-gray-300" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Parent ID</label>
                            <input name="parent_id" value="{{ old('parent_id', $requirement->parent_id) }}" class="mt-1 w-full rounded-md border-gray-300" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Orden</label>
                            <input name="orden" value="{{ old('orden', $requirement->orden) }}" class="mt-1 w-full rounded-md border-gray-300" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Sector</label>
                            <input name="sector" value="{{ old('sector', $requirement->sector) }}" class="mt-1 w-full rounded-md border-gray-300" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tipo</label>
                            <input name="tipo" value="{{ old('tipo', $requirement->tipo) }}" class="mt-1 w-full rounded-md border-gray-300" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Requiere check (SI/NO)</label>
                            <input name="requiere_check" value="{{ old('requiere_check', $requirement->requiere_check) }}" class="mt-1 w-full rounded-md border-gray-300" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Literal</label>
                            <input name="literal" value="{{ old('literal', $requirement->literal) }}" class="mt-1 w-full rounded-md border-gray-300" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Texto</label>
                            <textarea name="texto" rows="3" class="mt-1 w-full rounded-md border-gray-300">{{ old('texto', $requirement->texto) }}</textarea>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Nombre de documento</label>
                            <input name="nombre_documento" value="{{ old('nombre_documento', $requirement->nombre_documento) }}" class="mt-1 w-full rounded-md border-gray-300" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Carpeta</label>
                            <input name="carpeta" value="{{ old('carpeta', $requirement->carpeta) }}" class="mt-1 w-full rounded-md border-gray-300" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Origen</label>
                            <input name="origen" value="{{ old('origen', $requirement->origen) }}" class="mt-1 w-full rounded-md border-gray-300" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Visible</label>
                            <select name="visible" class="mt-1 w-full rounded-md border-gray-300">
                                <option value="1" {{ old('visible', $requirement->visible ?? 1) ? 'selected' : '' }}>SI</option>
                                <option value="0" {{ old('visible', $requirement->visible ?? 1) ? '' : 'selected' }}>NO</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-end gap-3">
                    <a href="{{ route('requirements.crud.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancelar</a>
                    <button class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700" type="submit">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
