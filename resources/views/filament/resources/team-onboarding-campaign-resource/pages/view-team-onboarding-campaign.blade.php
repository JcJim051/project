<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="grid gap-6 lg:grid-cols-[320px_minmax(0,1fr)]">
                <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5">
                    <div class="text-xs font-semibold uppercase tracking-wide text-sky-700">QR de caracterización</div>
                    <div class="mt-4 overflow-hidden rounded-2xl bg-white p-4 shadow-sm">
                        <img src="{{ $qrDataUri }}" alt="QR caracterización" class="mx-auto h-64 w-64">
                    </div>
                    <div class="mt-4 text-xs text-gray-600 break-all">Vista pública: {{ $publicUrl }}</div>
                    <div class="mt-1 text-xs text-gray-600 break-all">Registro: {{ $registerUrl }}</div>
                </div>
                <div class="space-y-4">
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Resumen</div>
                        <div class="mt-2 text-2xl font-semibold text-gray-900">{{ $campaign->title }}</div>
                        <div class="mt-2 text-sm text-gray-600">{{ $campaign->description ?: 'Sin descripción.' }}</div>
                        <div class="mt-4 grid gap-3 md:grid-cols-4 text-sm">
                            <div><span class="font-semibold">Estado:</span> {{ $campaign->registration_status }}</div>
                            <div><span class="font-semibold">Pendientes:</span> {{ $summary['pending'] }}</div>
                            <div><span class="font-semibold">Aprobadas:</span> {{ $summary['approved'] }}</div>
                            <div><span class="font-semibold">Rechazadas:</span> {{ $summary['rejected'] }}</div>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-white p-5">
                        <div class="text-sm font-semibold text-gray-800">Solicitudes recientes</div>
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="py-2 pr-3">Nombre</th>
                                    <th class="py-2 pr-3">Rol</th>
                                    <th class="py-2 pr-3">Estado</th>
                                    <th class="py-2">Enviada</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                @foreach($campaign->requests->take(10) as $request)
                                    <tr>
                                        <td class="py-2 pr-3 font-medium text-gray-900">{{ $request->full_name }}</td>
                                        <td class="py-2 pr-3">{{ $request->requestedRoleLabel() }}</td>
                                        <td class="py-2 pr-3">{{ $request->statusLabel() }}</td>
                                        <td class="py-2">{{ optional($request->submitted_at)->format('Y-m-d H:i') ?: '-' }}</td>
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
</x-filament-panels::page>
