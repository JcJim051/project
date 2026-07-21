<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="grid gap-6 lg:grid-cols-[320px_minmax(0,1fr)]">
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                    <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">QR de asistencia</div>
                    <div class="mt-4 overflow-hidden rounded-2xl bg-white p-4 shadow-sm">
                        <img src="{{ $qrDataUri }}" alt="QR asistencia" class="mx-auto h-64 w-64">
                    </div>
                    <div class="mt-4 text-xs text-gray-600 break-all">Vista pública: {{ $publicUrl }}</div>
                    <div class="mt-1 text-xs text-gray-600 break-all">Registro: {{ $registerUrl }}</div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700"
                            onclick="window.open(@js($publicUrl), '_blank')"
                        >
                            Abrir enlace
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center rounded-lg bg-white px-3 py-2 text-xs font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50"
                            onclick="copyAttendancePublicUrl(this)"
                        >
                            Copiar enlace
                        </button>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Resumen</div>
                        <div class="mt-2 text-2xl font-semibold text-gray-900">{{ $session->title ?: 'Registro de asistencia' }}</div>
                        <div class="mt-2 text-sm text-gray-600">{{ $session->objetivo }}</div>
                        <div class="mt-4 grid gap-3 md:grid-cols-4 text-sm">
                            <div><span class="font-semibold">Fecha:</span> {{ optional($session->fecha)->format('d/m/Y') ?: '-' }}</div>
                            <div><span class="font-semibold">Lugar:</span> {{ $session->lugar ?: '-' }}</div>
                            <div><span class="font-semibold">Estado:</span> {{ $session->registration_status }}</div>
                            <div><span class="font-semibold">Registrados:</span> <span data-attendance-count>{{ $summary['count'] }}</span></div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-5">
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-semibold text-gray-800">Asistentes</div>
                            <div class="text-xs text-gray-500">Actualización automática</div>
                        </div>
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="py-2 pr-3">#</th>
                                    <th class="py-2 pr-3">Nombre</th>
                                    <th class="py-2 pr-3">Documento</th>
                                    <th class="py-2 pr-3">Entidad / área</th>
                                    <th class="py-2">Registro</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100" data-attendance-table>
                                @foreach($session->entries as $entry)
                                    <tr>
                                        <td class="py-2 pr-3">{{ $entry->sequence_number }}</td>
                                        <td class="py-2 pr-3 font-medium text-gray-900">{{ $entry->full_name }}</td>
                                        <td class="py-2 pr-3">{{ $entry->document_number }}</td>
                                        <td class="py-2 pr-3">{{ $entry->organization_area ?: '-' }}</td>
                                        <td class="py-2">{{ optional($entry->registered_at)->format('Y-m-d H:i') }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        async function copyAttendancePublicUrl(button) {
            const url = @js($publicUrl);

            try {
                await navigator.clipboard.writeText(url);
                const original = button.textContent;
                button.textContent = 'Enlace copiado';
                setTimeout(() => button.textContent = original, 1800);
            } catch (error) {
                console.debug(error);
                window.prompt('Copia este enlace:', url);
            }
        }

        setInterval(async () => {
            try {
                const response = await fetch(@js(route('attendance.sessions.summary', $session)), { headers: { 'Accept': 'application/json' } });
                if (!response.ok) return;
                const data = await response.json();
                const countNode = document.querySelector('[data-attendance-count]');
                if (countNode) countNode.textContent = String(data.count ?? 0);
                const tbody = document.querySelector('[data-attendance-table]');
                if (!tbody) return;
                tbody.innerHTML = (data.entries || []).map((entry) => `
                    <tr>
                        <td class="py-2 pr-3">${entry.sequence_number ?? ''}</td>
                        <td class="py-2 pr-3 font-medium text-gray-900">${entry.full_name ?? ''}</td>
                        <td class="py-2 pr-3">${entry.document_number ?? ''}</td>
                        <td class="py-2 pr-3">${entry.organization_area ?? '-'}</td>
                        <td class="py-2">${entry.registered_at ?? ''}</td>
                    </tr>
                `).join('');
            } catch (error) {
                console.debug(error);
            }
        }, 10000);
    </script>
</x-filament-panels::page>
