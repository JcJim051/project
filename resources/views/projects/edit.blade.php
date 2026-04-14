<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Editar proyecto') }}
            </h2>
            <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('projects.update', $project) }}" class="bg-white shadow-sm sm:rounded-lg">
                @csrf
                @method('PUT')

                <div class="p-6 space-y-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Identificación del proyecto</h3>
                        <p class="text-sm text-gray-500">Actualiza la información del proyecto.</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nombre del proyecto</label>
                            <input name="nombre" value="{{ old('nombre', $project->nombre) }}" class="mt-1 w-full rounded-md border-gray-300" required />
                            @error('nombre')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ID Proyecto</label>
                            <input name="id_proyecto" value="{{ old('id_proyecto', $project->id_proyecto) }}" class="mt-1 w-full rounded-md border-gray-300" required />
                            @error('id_proyecto')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">BIPIN</label>
                            <input name="bipin" value="{{ old('bipin', $project->bipin) }}" class="mt-1 w-full rounded-md border-gray-300" />
                            @error('bipin')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Objeto del proyecto</label>
                            <textarea name="objeto_proyecto" rows="3" class="mt-1 w-full rounded-md border-gray-300" required>{{ old('objeto_proyecto', $project->objeto_proyecto) }}</textarea>
                            @error('objeto_proyecto')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nombre clave</label>
                            <input name="nombre_clave" value="{{ old('nombre_clave', $project->nombre_clave) }}" class="mt-1 w-full rounded-md border-gray-300" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Municipio</label>
                            <input name="municipio" value="{{ old('municipio', $project->municipio) }}" class="mt-1 w-full rounded-md border-gray-300" required />
                            @error('municipio')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Secretaría</label>
                            <input name="secretaria" value="{{ old('secretaria', $project->secretaria) }}" class="mt-1 w-full rounded-md border-gray-300" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Fecha de creación</label>
                            <input type="date" name="fecha_creacion" value="{{ old('fecha_creacion', optional($project->fecha_creacion)->format('Y-m-d')) }}" class="mt-1 w-full rounded-md border-gray-300" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Ruta Drive</label>
                            <input name="ruta_drive" value="{{ old('ruta_drive', $project->ruta_drive) }}" class="mt-1 w-full rounded-md border-gray-300" />
                            <p class="mt-1 text-xs text-gray-500">Pega el enlace de la carpeta o solo el ID.</p>
                            @error('ruta_drive')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Sectores</h3>
                        <p class="text-sm text-gray-500">Selecciona uno o más sectores del proyecto.</p>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            @foreach ($sectors as $sector)
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="sectores[]" value="{{ $sector->id }}" class="rounded border-gray-300"
                                        {{ in_array($sector->id, old('sectores', $project->sectores->pluck('id')->all())) ? 'checked' : '' }}>
                                    {{ $sector->nombre }}
                                </label>
                            @endforeach
                        </div>
                        @error('sectores')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Asignación</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Formulador</label>
                                <select name="formulador_id" class="mt-1 w-full rounded-md border-gray-300">
                                    <option value="">Sin asignar</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" {{ old('formulador_id', $project->formulador_id) == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Estructurador</label>
                                <select name="estructurador_id" class="mt-1 w-full rounded-md border-gray-300">
                                    <option value="">Sin asignar</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" {{ old('estructurador_id', $project->estructurador_id) == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-end gap-3">
                    <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancelar</a>
                    <button class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700" type="submit">
                        Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
