<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4 text-sm">
                <div><span class="font-semibold">Nombre:</span> {{ $person->full_name }}</div>
                <div><span class="font-semibold">Documento:</span> {{ $person->document_number ?: '-' }}</div>
                <div><span class="font-semibold">Tipo:</span> {{ $person->internalLabel() }}</div>
                <div><span class="font-semibold">Participaciones:</span> {{ $person->attendanceEntries->count() }}</div>
                <div><span class="font-semibold">Entidad / área:</span> {{ $person->organization_area ?: '-' }}</div>
                <div><span class="font-semibold">Teléfono:</span> {{ $person->phone ?: '-' }}</div>
                <div class="md:col-span-2"><span class="font-semibold">Correo / dirección:</span> {{ $person->email_or_address ?: '-' }}</div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="text-sm font-semibold text-gray-800">Historial de asistencias del equipo</div>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="py-2 pr-3">Fecha</th>
                        <th class="py-2 pr-3">Objetivo</th>
                        <th class="py-2 pr-3">Lugar</th>
                        <th class="py-2 pr-3">Registro</th>
                        <th class="py-2">PDF</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @foreach($person->attendanceEntries as $entry)
                        <tr>
                            <td class="py-2 pr-3">{{ optional($entry->session?->fecha)->format('Y-m-d') ?: '-' }}</td>
                            <td class="py-2 pr-3">{{ $entry->session?->objetivo ?: '-' }}</td>
                            <td class="py-2 pr-3">{{ $entry->session?->lugar ?: '-' }}</td>
                            <td class="py-2 pr-3">{{ optional($entry->registered_at)->format('Y-m-d H:i') }}</td>
                            <td class="py-2">
                                @if($entry->session)
                                    <a href="{{ route('attendance.sessions.download.pdf', $entry->session) }}" class="text-emerald-700 hover:text-emerald-800">Descargar PDF</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
