<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-gray-500">Mi progreso</p>
                    <div class="mt-1 flex items-center gap-2">
                        @if(!empty($this->myProgress['level_image_url']))
                            <img src="{{ $this->myProgress['level_image_url'] }}" alt="Rango" class="h-10 w-10 rounded-md object-cover border border-gray-200">
                        @endif
                        <p class="text-lg font-semibold text-gray-900">{{ $this->myProgress['level'] }}</p>
                    </div>
                    <p class="text-sm text-gray-600">{{ $this->myProgress['points'] }} puntos · Siguiente: {{ $this->myProgress['next'] }}</p>
                </div>
                <div class="w-40">
                    <label class="text-xs text-gray-500">Vigencia</label>
                    <select wire:model.live="seasonYear" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        @foreach($this->yearOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <h3 class="mb-3 text-sm font-semibold text-gray-700">Top general</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-gray-500">
                        <tr>
                            <th class="py-2 pr-3">#</th>
                            <th class="py-2 pr-3">Usuario</th>
                            <th class="py-2 pr-3">Rol</th>
                            <th class="py-2 pr-3">Nivel</th>
                            <th class="py-2 pr-3">Puntos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->ranking as $row)
                            <tr class="border-t border-gray-100">
                                <td class="py-2 pr-3 font-semibold text-gray-700">{{ $row['pos'] }}</td>
                                <td class="py-2 pr-3 text-gray-800">{{ $row['name'] }}</td>
                                <td class="py-2 pr-3 text-gray-600">{{ $row['role'] }}</td>
                                <td class="py-2 pr-3 text-gray-700">
                                    <div class="flex items-center gap-2">
                                        @if(!empty($row['level_image_url']))
                                            <img src="{{ $row['level_image_url'] }}" alt="Rango" class="h-7 w-7 rounded object-cover border border-gray-200">
                                        @endif
                                        <span>{{ $row['level'] }}</span>
                                    </div>
                                </td>
                                <td class="py-2 pr-3 font-semibold text-gray-900">{{ $row['points'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 text-gray-500">Sin datos para esta vigencia.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
