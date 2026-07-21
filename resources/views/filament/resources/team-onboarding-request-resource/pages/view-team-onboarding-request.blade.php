<x-filament-panels::page>
    @php($requestItem = $this->record)
    <div class="space-y-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4 text-sm">
                <div><span class="font-semibold">Nombre:</span> {{ $requestItem->full_name }}</div>
                <div><span class="font-semibold">Documento:</span> {{ $requestItem->document_number }}</div>
                <div><span class="font-semibold">Rol solicitado:</span> {{ $requestItem->requestedRoleLabel() }}</div>
                <div><span class="font-semibold">Estado:</span> {{ $requestItem->statusLabel() }}</div>
                <div><span class="font-semibold">Celular:</span> {{ $requestItem->phone ?: '-' }}</div>
                <div><span class="font-semibold">Correo:</span> {{ $requestItem->email ?: '-' }}</div>
                <div><span class="font-semibold">Especialidad:</span> {{ $requestItem->specialty ?: '-' }}</div>
                <div class="md:col-span-2 xl:col-span-4"><span class="font-semibold">Campaña:</span> {{ $requestItem->campaign?->title ?: '-' }}</div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="text-sm font-semibold text-gray-800">Resultado de revisión</div>
            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4 text-sm">
                <div><span class="font-semibold">Aprobada por:</span> {{ $requestItem->approvedBy?->name ?: '-' }}</div>
                <div><span class="font-semibold">Fecha aprobación:</span> {{ optional($requestItem->approved_at)->format('Y-m-d H:i') ?: '-' }}</div>
                <div><span class="font-semibold">Rechazada por:</span> {{ $requestItem->rejectedBy?->name ?: '-' }}</div>
                <div><span class="font-semibold">Fecha rechazo:</span> {{ optional($requestItem->rejected_at)->format('Y-m-d H:i') ?: '-' }}</div>
                <div class="md:col-span-2 xl:col-span-4"><span class="font-semibold">Observación:</span> {{ $requestItem->review_notes ?: '-' }}</div>
                <div class="md:col-span-2 xl:col-span-4">
                    <span class="font-semibold">Registro creado:</span>
                    @if($requestItem->createdUser)
                        Usuario {{ $requestItem->createdUser->name }} ({{ $requestItem->createdUser->email }})
                        <div class="mt-2 text-sm text-gray-600">
                            <strong>Estado Plane:</strong> {{ match($requestItem->createdUser->plane_sync_status) {
                                'linked' => 'Vinculado correctamente',
                                'invited' => 'Invitación enviada',
                                'not_found' => 'No encontrado en Plane',
                                'error' => 'Con novedad de sincronización',
                                default => 'Pendiente de sincronizar',
                            } }}
                            @if($requestItem->createdUser->plane_user_id)
                                <span class="ml-3"><strong>Plane user id:</strong> {{ $requestItem->createdUser->plane_user_id }}</span>
                            @endif
                            @if($requestItem->createdUser->plane_last_error)
                                <div class="mt-1"><strong>Última novedad:</strong> {{ $requestItem->createdUser->plane_last_error }}</div>
                            @endif
                        </div>
                    @elseif($requestItem->createdSpecialist)
                        Especialista {{ $requestItem->createdSpecialist->nombre }} ({{ $requestItem->createdSpecialist->correo }})
                    @else
                        -
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
