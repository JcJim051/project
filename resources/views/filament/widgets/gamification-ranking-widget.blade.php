<x-filament::section>
    <x-slot name="heading">
        Ranking anual {{ $year }}
    </x-slot>

    <div class="mb-4 rounded-xl border border-lime-200 bg-gradient-to-r from-lime-50 to-white px-4 py-3">
        <div class="text-xs font-semibold uppercase tracking-wide text-lime-700">Mi progreso</div>
        <div class="mt-1 text-base font-bold text-gray-900">{{ $myProgress['level'] }} · {{ $myProgress['role_label'] }}</div>
        <div class="text-sm text-gray-600">
            {{ $myProgress['points'] }} puntos · Siguiente: {{ $myProgress['next'] }}
        </div>
    </div>

    <div class="space-y-2">
        @forelse($rows as $row)
            <div class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-lime-100 px-2 text-xs font-bold text-lime-800">
                        {{ $row['position'] }}
                    </span>
                    <div>
                        <div class="text-sm font-semibold text-gray-800">{{ $row['user_name'] }}</div>
                        <div class="text-xs text-gray-500">{{ $row['level'] }}</div>
                    </div>
                </div>
                <div class="text-sm font-bold text-gray-800">{{ $row['points'] }} pts</div>
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-gray-300 px-3 py-5 text-sm text-gray-500">
                Aún no hay puntajes registrados este año.
            </div>
        @endforelse
    </div>
</x-filament::section>
