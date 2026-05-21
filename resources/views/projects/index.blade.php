<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Avances de Proyectos') }}
            </h2>
            <div class="flex items-center gap-3">
                @if (auth()->user()?->is_admin)
                    <a href="{{ route('requirements.crud.index') }}" class="px-3 py-2 text-sm font-medium text-indigo-600 border border-indigo-200 rounded-md hover:bg-indigo-50">
                        Gestionar requisitos
                    </a>
                @endif
                <a href="{{ route('projects.create') }}" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">
                    Crear proyecto
                </a>
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

            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 border-b border-gray-100">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Resumen general</h3>
                        <p class="text-sm text-gray-500">Avance calculado con PDFs definitivos detectados.</p>
                    </div>
                </div>

                <div class="p-6" x-data="{ openId: null }">
                    @if ($projects->isEmpty())
                        <div class="rounded-md border border-dashed border-gray-300 p-6 text-center text-gray-500">
                            Aún no hay proyectos registrados. Usa “Crear proyecto” para comenzar.
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        <th class="py-3 pr-4">Proyecto</th>
                                        <th class="py-3 pr-4">Sector(es)</th>
                                        <th class="py-3 pr-4">Formulador</th>
                                        <th class="py-3 pr-4">Estructurador</th>
                                        <th class="py-3 pr-4">Avance</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($projects as $project)
                                        @php
                                            $avance = $project->avance ?? 0;
                                            $sectores = $project->sectores->pluck('nombre')->implode(', ');
                                            $summary = $summaries[$project->id]['subcarpetas'] ?? [];
                                        @endphp
                                        <tr>
                                            <td class="py-4 pr-4">
                                                <button type="button" class="font-medium text-indigo-700 hover:text-indigo-900 text-left"
                                                    @click="openId = {{ $project->id }}">
                                                    {{ $project->nombre }}
                                                </button>
                                                <div class="text-xs text-gray-500">{{ $project->municipios_display }}</div>
                                            </td>
                                            <td class="py-4 pr-4 text-sm text-gray-600">{{ $sectores ?: 'Sin sector' }}</td>
                                            <td class="py-4 pr-4 text-sm text-gray-600">{{ $project->formulador->name ?? 'Sin asignar' }}</td>
                                            <td class="py-4 pr-4 text-sm text-gray-600">{{ $project->estructurador->name ?? 'Sin asignar' }}</td>
                                            <td class="py-4 pr-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-40 h-2 rounded-full bg-gray-100">
                                                        <div class="h-2 rounded-full bg-indigo-500" style="width: {{ $avance }}%"></div>
                                                    </div>
                                                    <span class="text-sm font-semibold text-gray-700">{{ $avance }}%</span>
                                                </div>
                                                <div class="mt-2 flex items-center gap-3 text-xs">
                                                    <a href="{{ route('projects.edit', $project) }}" class="text-indigo-600 hover:text-indigo-800">Editar</a>
                                                    <a href="{{ route('projects.checklist', $project) }}" class="text-emerald-600 hover:text-emerald-800">Requisitos</a>
                                                    <a href="{{ route('projects.manage', $project) }}" class="text-sky-600 hover:text-sky-800">Gestionar</a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @foreach ($projects as $project)
                            @php
                                $sectores = $project->sectores->pluck('nombre')->implode(', ');
                                $summary = $summaries[$project->id]['subcarpetas'] ?? [];
                            @endphp
                            <div
                                x-show="openId === {{ $project->id }}"
                                x-cloak
                                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 px-4 py-10"
                                @click.self="openId = null"
                            >
                                <div class="w-full max-w-4xl rounded-lg bg-white shadow-xl">
                                    <div class="flex items-start justify-between border-b border-gray-100 p-6">
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-800">Ficha del proyecto</h3>
                                            <p class="text-sm text-gray-500">{{ $project->nombre }}</p>
                                        </div>
                                        <button type="button" class="text-sm text-gray-500 hover:text-gray-700" @click="openId = null">
                                            Cerrar
                                        </button>
                                    </div>
                                    <div class="p-6 space-y-6">
                                        <div class="rounded-md border border-gray-100 p-4">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <div class="text-xs uppercase text-gray-400">Avance general</div>
                                                    <div class="text-sm font-semibold text-gray-700">{{ $project->avance ?? 0 }}%</div>
                                                </div>
                                                <div class="w-40 h-2 rounded-full bg-gray-100">
                                                    <div class="h-2 rounded-full bg-indigo-500" style="width: {{ $project->avance ?? 0 }}%"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div class="rounded-md border border-gray-100 p-4">
                                                <div class="text-xs uppercase text-gray-400">ID Proyecto</div>
                                                <div class="text-sm font-semibold text-gray-700">{{ $project->id_proyecto }}</div>
                                            </div>
                                            <div class="rounded-md border border-gray-100 p-4">
                                                <div class="text-xs uppercase text-gray-400">Municipio</div>
                                                <div class="text-sm font-semibold text-gray-700">{{ $project->municipios_display }}</div>
                                            </div>
                                            <div class="rounded-md border border-gray-100 p-4">
                                                <div class="text-xs uppercase text-gray-400">Secretaría</div>
                                                <div class="text-sm font-semibold text-gray-700">{{ $project->secretaria ?: 'No definida' }}</div>
                                            </div>
                                            <div class="rounded-md border border-gray-100 p-4">
                                                <div class="text-xs uppercase text-gray-400">Fecha de creación</div>
                                                <div class="text-sm font-semibold text-gray-700">{{ optional($project->fecha_creacion)->format('Y-m-d') ?: 'Sin fecha' }}</div>
                                            </div>
                                            <div class="rounded-md border border-gray-100 p-4">
                                                <div class="text-xs uppercase text-gray-400">Formulador</div>
                                                <div class="text-sm font-semibold text-gray-700">{{ $project->formulador->name ?? 'Sin asignar' }}</div>
                                            </div>
                                            <div class="rounded-md border border-gray-100 p-4">
                                                <div class="text-xs uppercase text-gray-400">Estructurador</div>
                                                <div class="text-sm font-semibold text-gray-700">{{ $project->estructurador->name ?? 'Sin asignar' }}</div>
                                            </div>
                                            <div class="rounded-md border border-gray-100 p-4 sm:col-span-2">
                                                <div class="text-xs uppercase text-gray-400">Sectores</div>
                                                <div class="text-sm font-semibold text-gray-700">{{ $sectores ?: 'Sin sector' }}</div>
                                            </div>
                                            <div class="rounded-md border border-gray-100 p-4 sm:col-span-2">
                                                <div class="text-xs uppercase text-gray-400">Objeto del proyecto</div>
                                                <div class="text-sm text-gray-700">{{ $project->objeto_proyecto }}</div>
                                            </div>
                                            <div class="rounded-md border border-gray-100 p-4 sm:col-span-2">
                                                <div class="text-xs uppercase text-gray-400">Carpeta Drive</div>
                                                @if ($project->ruta_drive)
                                                    <a class="text-sm text-indigo-600 hover:text-indigo-800 break-all" href="{{ $project->ruta_drive }}" target="_blank" rel="noopener">
                                                        {{ $project->ruta_drive }}
                                                    </a>
                                                @else
                                                    <div class="text-sm text-gray-500">Sin enlace</div>
                                                @endif
                                            </div>
                                        </div>

                                        <div>
                                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Avances por subcarpeta</h4>
                                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                                @foreach ($summary as $name => $info)
                                                    <div class="rounded-lg border border-gray-200 p-4">
                                                        <div class="flex items-center justify-between">
                                                            <span class="text-sm font-semibold text-gray-700">{{ $name }}</span>
                                                            <span class="text-sm text-gray-500">{{ $info['percent'] }}%</span>
                                                        </div>
                                                        <div class="mt-2 text-xs text-gray-400">
                                                            {{ $info['done'] }} de {{ $info['total'] }} requisitos marcados
                                                        </div>
                                                        <div class="mt-3 h-2 rounded-full bg-gray-100">
                                                            <div class="h-2 rounded-full bg-emerald-500" style="width: {{ $info['percent'] }}%"></div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
