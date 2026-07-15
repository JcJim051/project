<x-filament-panels::page>
    @php
        $planeStatus = $planeStatus ?? [];
        $planeTeamStatus = $planeTeamStatus ?? [];
        $planeSyncRuns = $planeSyncRuns ?? [];
    @endphp

    <div class="space-y-4">
        <div class="text-sm text-gray-500">Proyecto: {{ $project->nombre }}</div>

        @if (!empty($planeStatus))
            <div class="rounded-lg border px-4 py-4 text-sm {{ $planeStatus['status_class'] ?? 'border-gray-200 bg-gray-50 text-gray-700' }}">
                <div class="space-y-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-semibold">Estado de sincronización Plane</span>
                        <span class="inline-flex items-center rounded-full border border-current/20 px-2 py-0.5 text-[11px] font-semibold">
                            {{ $planeStatus['status_label'] ?? 'Sin configuración' }}
                        </span>
                    </div>
                    <div class="text-xs opacity-90">
                        Tareas Orbit mapeadas: {{ $planeStatus['tasks_active'] ?? 0 }} activas / {{ $planeStatus['tasks_total'] ?? 0 }} registradas
                    </div>
                    @if (!empty($planeStatus['last_provisioned_at']))
                        <div class="text-xs opacity-90">
                            Última sincronización exitosa: {{ $planeStatus['last_provisioned_at'] }}
                        </div>
                    @endif
                    @if (!empty($planeStatus['last_error']))
                        <div class="text-xs font-medium">
                            Último resultado: {{ $planeStatus['last_error'] }}
                        </div>
                    @endif
                    @if (!empty($planeStatus['project_url']))
                        <div>
                            <a
                                href="{{ $planeStatus['project_url'] }}"
                                target="_blank"
                                rel="noopener"
                                class="text-xs font-semibold underline underline-offset-2">
                                Abrir proyecto en Plane
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <div class="font-semibold text-gray-800">Equipo operativo en Plane</div>
                    <div class="text-xs text-gray-500">Valida formulador, estructurador, apoyo ambiental y especialistas contra el workspace y el proyecto de Plane.</div>
                </div>
                <div class="flex gap-2 flex-wrap text-[11px]">
                    <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-700">
                        Encontrados: {{ $planeTeamStatus['found_count'] ?? 0 }}
                    </span>
                    <span class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 font-semibold text-sky-700">
                        En el proyecto: {{ $planeTeamStatus['in_project_count'] ?? 0 }}
                    </span>
                    <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 font-semibold text-amber-700">
                        Faltantes: {{ $planeTeamStatus['missing_count'] ?? 0 }}
                    </span>
                </div>
            </div>

            @if (!empty($planeTeamStatus['message']) && empty($planeTeamStatus['members']))
                <div class="mt-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                    {{ $planeTeamStatus['message'] }}
                </div>
            @endif

            @if (!empty($planeTeamStatus['members']))
                <div class="mt-3 grid gap-2">
                    @foreach (($planeTeamStatus['members'] ?? []) as $member)
                        @php
                            $isFound = (bool) ($member['found_in_workspace'] ?? false);
                            $isInProject = (bool) ($member['in_project'] ?? false);
                            $memberBadge = $isFound
                                ? ($isInProject
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                    : 'border-sky-200 bg-sky-50 text-sky-700')
                                : 'border-amber-200 bg-amber-50 text-amber-700';
                            $memberLabel = $isFound
                                ? ($isInProject ? 'Listo' : 'Existe en workspace')
                                : 'No existe en Plane';
                        @endphp
                        <div class="rounded-md border border-gray-100 px-3 py-2">
                            <div class="flex items-start justify-between gap-3 flex-wrap">
                                <div>
                                    <div class="font-medium text-gray-800">
                                        {{ $member['role'] ?? 'Responsable' }}
                                        @if (!empty($member['source']))
                                            <span class="text-gray-400">·</span>
                                            <span class="text-gray-600">{{ $member['source'] }}</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-600">{{ $member['name'] ?? 'Sin nombre' }} · {{ $member['email'] ?? 'Sin correo' }}</div>
                                    <div class="mt-1 text-[11px] text-gray-500">{{ $member['note'] ?? '' }}</div>
                                </div>
                                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $memberBadge }}">
                                    {{ $memberLabel }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600">
            <div class="font-semibold text-gray-800">Siguiente evolución</div>
            <div class="mt-1 text-xs leading-5">
                Desde esta vista vamos a separar también las acciones de sincronización por bloques: equipo, tareas y sincronización completa, para reducir rate limit y dar más control operativo.
            </div>
        </div>

        @if (auth()->user()?->isAdminUser())
            <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <div class="font-semibold text-gray-800">Historial de sincronizaciones</div>
                        <div class="text-xs text-gray-500">Aquí quedan visibles las últimas corridas, incluyendo errores y reintentos, solo para administración.</div>
                    </div>
                </div>

                @if (empty($planeSyncRuns))
                    <div class="mt-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                        Aún no hay corridas registradas para este proyecto.
                    </div>
                @else
                    <div class="mt-3 space-y-3">
                        @foreach ($planeSyncRuns as $run)
                            <div class="rounded-md border border-gray-100 px-3 py-3">
                                <div class="flex items-start justify-between gap-3 flex-wrap">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-medium text-gray-800">#{{ $run['id'] }}</span>
                                            <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $run['status_class'] }}">
                                                {{ $run['status_label'] }}
                                            </span>
                                            <span class="text-xs text-gray-500">Modo: {{ $run['mode_label'] }}</span>
                                        </div>
                                        <div class="text-xs text-gray-600">
                                            Lanzada por: {{ $run['initiated_by'] }} · {{ $run['created_at'] ?: 'Sin fecha' }}
                                        </div>
                                        @if (!empty($run['started_at']) || !empty($run['finished_at']))
                                            <div class="text-xs text-gray-500">
                                                Inicio: {{ $run['started_at'] ?: '—' }} · Fin: {{ $run['finished_at'] ?: '—' }} · Intentos: {{ $run['attempt_count'] }}
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                @if (!empty($run['message']))
                                    <div class="mt-2 text-xs text-gray-700">
                                        {{ $run['message'] }}
                                    </div>
                                @endif

                                @if (!empty($run['error_details']))
                                    <div class="mt-2 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700 whitespace-pre-wrap break-words">
                                        {{ $run['error_details'] }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
