<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Crear proyecto') }}
            </h2>
            <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('projects.store') }}" class="bg-white shadow-sm sm:rounded-lg">
                @csrf

                <div class="p-6 space-y-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Identificación del proyecto</h3>
                        <p class="text-sm text-gray-500">Completa la información mínima para iniciar la ficha.</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nombre del proyecto</label>
                            <input name="nombre" value="{{ old('nombre') }}" class="mt-1 w-full rounded-md border-gray-300" required />
                            @error('nombre')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ID Proyecto</label>
                            <input name="id_proyecto" value="{{ old('id_proyecto') }}" class="mt-1 w-full rounded-md border-gray-300" required />
                            @error('id_proyecto')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">BIPIN</label>
                            <input name="bipin" value="{{ old('bipin') }}" class="mt-1 w-full rounded-md border-gray-300" />
                            @error('bipin')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Fuente de recursos</label>
                            <select name="funding_source" class="mt-1 w-full rounded-md border-gray-300" required>
                                <option value="sgr" {{ old('funding_source', 'sgr') === 'sgr' ? 'selected' : '' }}>SGR</option>
                                <option value="propios" {{ old('funding_source') === 'propios' ? 'selected' : '' }}>Recursos propios</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Objeto del proyecto</label>
                            <textarea name="objeto_proyecto" rows="3" class="mt-1 w-full rounded-md border-gray-300" required>{{ old('objeto_proyecto') }}</textarea>
                            @error('objeto_proyecto')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nombre clave</label>
                            <input name="nombre_clave" value="{{ old('nombre_clave') }}" class="mt-1 w-full rounded-md border-gray-300" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Municipio</label>
                            <input name="municipio" value="{{ old('municipio') }}" class="mt-1 w-full rounded-md border-gray-300" required />
                            @error('municipio')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Secretaría</label>
                            <input name="secretaria" value="{{ old('secretaria') }}" class="mt-1 w-full rounded-md border-gray-300" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Fecha de creación</label>
                            <input type="date" name="fecha_creacion" value="{{ old('fecha_creacion') }}" class="mt-1 w-full rounded-md border-gray-300" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Ruta Drive</label>
                            <input name="ruta_drive" value="{{ old('ruta_drive') }}" class="mt-1 w-full rounded-md border-gray-300" />
                            <p class="mt-1 text-xs text-gray-500">Pega el enlace de la carpeta o solo el ID.</p>
                            @error('ruta_drive')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Sectores</h3>
                        <p class="text-sm text-gray-500">Define un sector principal y, si aplica, sectores secundarios.</p>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Sector principal</label>
                                <select name="sector_principal_id" class="mt-1 w-full rounded-md border-gray-300" required>
                                    <option value="">Selecciona...</option>
                                    @foreach ($sectors as $sector)
                                        <option value="{{ $sector->id }}" {{ (int) old('sector_principal_id') === (int) $sector->id ? 'selected' : '' }}>{{ $sector->nombre }}</option>
                                    @endforeach
                                </select>
                                @error('sector_principal_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Sectores secundarios</label>
                                <select name="sectores_secundarios[]" multiple class="mt-1 w-full rounded-md border-gray-300 h-40">
                                    @foreach ($sectors as $sector)
                                        <option value="{{ $sector->id }}" {{ in_array($sector->id, old('sectores_secundarios', [])) ? 'selected' : '' }}>{{ $sector->nombre }}</option>
                                    @endforeach
                                </select>
                                @error('sectores_secundarios')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Asignación</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Formulador</label>
                                <select name="formulador_id" class="mt-1 w-full rounded-md border-gray-300">
                                    <option value="">Sin asignar</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" {{ old('formulador_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Estructurador</label>
                                <select name="estructurador_id" class="mt-1 w-full rounded-md border-gray-300">
                                    <option value="">Sin asignar</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" {{ old('estructurador_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-end gap-3">
                    <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancelar</a>
                    <button class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700" type="submit">
                        Guardar proyecto
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
