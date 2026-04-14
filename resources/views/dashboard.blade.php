<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Avances de Proyectos') }}
            </h2>
            <span class="text-sm text-gray-500">Vista preliminar</span>
        </div>
    </x-slot>

    @php
        $projects = [
            [
                'nombre' => 'Proyecto de Transporte Vial',
                'sector' => 'Transporte',
                'formulador' => 'Sin asignar',
                'estructurador' => 'Sin asignar',
                'avance' => 52,
            ],
            [
                'nombre' => 'Mejoramiento Ambiental Rural',
                'sector' => 'Ambiente',
                'formulador' => 'Sin asignar',
                'estructurador' => 'Sin asignar',
                'avance' => 18,
            ],
            [
                'nombre' => 'Infraestructura Educativa',
                'sector' => 'Educación',
                'formulador' => 'Sin asignar',
                'estructurador' => 'Sin asignar',
                'avance' => 80,
            ],
        ];
    @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">Resumen general</h3>
                            <p class="text-sm text-gray-500">Avance calculado con PDFs definitivos detectados.</p>
                        </div>
                        <button class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">
                            Crear proyecto
                        </button>
                    </div>
                </div>

                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    <th class="py-3 pr-4">Proyecto</th>
                                    <th class="py-3 pr-4">Sector</th>
                                    <th class="py-3 pr-4">Formulador</th>
                                    <th class="py-3 pr-4">Estructurador</th>
                                    <th class="py-3 pr-4">Avance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($projects as $project)
                                    <tr>
                                        <td class="py-4 pr-4">
                                            <div class="font-medium text-gray-800">{{ $project['nombre'] }}</div>
                                            <div class="text-xs text-gray-500">{{ $project['sector'] }}</div>
                                        </td>
                                        <td class="py-4 pr-4 text-sm text-gray-600">{{ $project['sector'] }}</td>
                                        <td class="py-4 pr-4 text-sm text-gray-600">{{ $project['formulador'] }}</td>
                                        <td class="py-4 pr-4 text-sm text-gray-600">{{ $project['estructurador'] }}</td>
                                        <td class="py-4 pr-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-40 h-2 rounded-full bg-gray-100">
                                                    <div class="h-2 rounded-full bg-indigo-500" style="width: {{ $project['avance'] }}%"></div>
                                                </div>
                                                <span class="text-sm font-semibold text-gray-700">{{ $project['avance'] }}%</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-800">Estado por carpeta</h3>
                    <p class="text-sm text-gray-500 mb-4">Vista demo por subcarpeta (Formulación, Presupuesto, Certificaciones, Licencias, Estudios).</p>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach (['Formulación' => 40, 'Presupuesto' => 60, 'Certificaciones' => 30, 'Licencias' => 20, 'Estudios' => 75] as $name => $progress)
                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold text-gray-700">{{ $name }}</span>
                                    <span class="text-sm text-gray-500">{{ $progress }}%</span>
                                </div>
                                <div class="mt-3 h-2 rounded-full bg-gray-100">
                                    <div class="h-2 rounded-full bg-emerald-500" style="width: {{ $progress }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
