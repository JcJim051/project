@php
    $profile = $project->bankProfile;
    $primarySector = $project->sectores->firstWhere('pivot.is_primary', true);
    $secondarySectors = $project->sectores->filter(fn ($s) => ! (bool) ($s->pivot->is_primary ?? false));
    $mgaUrl = $project->id_proyecto
        ? ('https://mgaweb.dnp.gov.co/Identification/Id01?ProjectId=' . urlencode((string) $project->id_proyecto))
        : null;
@endphp

<div class="space-y-4">
    <div class="rounded-xl border border-gray-200 bg-white p-4">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:nowrap;">
            <div class="min-w-0" style="flex:1 1 auto;min-width:0;">
                <h3 class="text-base font-semibold text-gray-900">
                    <a
                        href="{{ \App\Filament\Resources\ProjectResource::getUrl('review', ['record' => $project]) }}"
                        class="inline-flex max-w-full items-center rounded-md px-2.5 py-1 text-sm font-semibold shadow-sm transition"
                        style="border:1px solid #93c5fd;background:#dbeafe;color:#1e3a8a;"
                        title="{{ ($project->nombre_clave ?: $project->nombre) . ' - ' . ($project->objeto_proyecto ?: '-') }}"
                    >
                        <span class="block max-w-full truncate">
                            {{ ($project->nombre_clave ?: $project->nombre) . ' - ' . ($project->objeto_proyecto ?: '-') }}
                        </span>
                    </a>
                </h3>
                <p class="text-xs text-gray-500 mt-1">
                    @if ($mgaUrl)
                        <a href="{{ $mgaUrl }}" target="_blank" rel="noopener" class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold shadow-sm transition" style="border:1px solid #6ee7b7;background:#ecfdf5;color:#065f46;">
                            ID {{ $project->id_proyecto ?: '-' }}
                        </a>
                        <a href="{{ $mgaUrl }}" target="_blank" rel="noopener" class="ml-1 inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold shadow-sm transition" style="border:1px solid #6ee7b7;background:#ecfdf5;color:#065f46;">
                            BPIN {{ $project->bipin ?: '-' }}
                        </a>
                    @else
                        ID {{ $project->id_proyecto ?: '-' }} · BPIN {{ $project->bipin ?: '-' }}
                    @endif
                </p>
            </div>
            <div class="text-right" style="flex:0 0 auto;white-space:nowrap;">
                <div class="text-xs text-gray-500">Avance general</div>
                <div class="text-2xl font-bold text-primary-700">{{ $progress['overall_percent'] }}%</div>
                <div class="text-xs text-gray-500">{{ $progress['overall_done'] }} / {{ $progress['overall_total'] }} requisitos</div>
            </div>
        </div>

        <div class="mt-3 grid gap-2 md:grid-cols-2">
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3 text-xs">
                <div><span class="font-semibold">Sector principal:</span> {{ $primarySector ? (($primarySector->codigo ?: '-') . ' - ' . ($primarySector->nombre ?: '-')) : '-' }}</div>
                <div class="mt-1"><span class="font-semibold">Sectores secundarios:</span> {{ $secondarySectors->isNotEmpty() ? $secondarySectors->map(fn ($s) => trim(($s->codigo ?: '-') . ' - ' . ($s->nombre ?: '-')))->implode(', ') : '-' }}</div>
                <div class="mt-1"><span class="font-semibold">Producto MGA:</span> {{ $project->producto ? ($project->producto->codigo . ' - ' . $project->producto->nombre) : '-' }}</div>
                <div class="mt-1"><span class="font-semibold">Municipios:</span> {{ $project->municipios_display ?: '-' }}</div>
                <div class="mt-1"><span class="font-semibold">Secretaría:</span> {{ $project->secretaria ?: '-' }}</div>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3 text-xs">
                <div><span class="font-semibold">Formulador:</span> {{ optional($project->formulador)->name ?: '-' }}</div>
                <div class="mt-1"><span class="font-semibold">Estructurador:</span> {{ optional($project->estructurador)->name ?: '-' }}</div>
                <div class="mt-1"><span class="font-semibold">Etapa / Estado:</span> {{ optional($project->stage)->nombre ?: '-' }} / {{ optional($project->status)->nombre ?: '-' }}</div>
                <div class="mt-1"><span class="font-semibold">Valor:</span> $ {{ number_format((float) $project->valor, 2, ',', '.') }}</div>
                <div class="mt-1"><span class="font-semibold">Fuente:</span> {{ strtoupper((string) ($project->funding_source ?: '-')) }}</div>
                <div class="mt-1"><span class="font-semibold">Años ejecución:</span> {{ $project->executionYears->pluck('anio')->implode(', ') ?: '-' }}</div>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4">
        <h4 class="text-sm font-semibold text-gray-900">Datos principales</h4>
        <div class="mt-3 grid gap-2 md:grid-cols-2">
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3 text-xs">
                <div><span class="font-semibold">Nombre del proyecto:</span> {{ $project->nombre ?: '-' }}</div>
                <div class="mt-1"><span class="font-semibold">Nombre clave:</span> {{ $project->nombre_clave ?: '-' }}</div>
                <div class="mt-1"><span class="font-semibold">Objeto:</span> {{ $project->objeto_proyecto ?: '-' }}</div>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3 text-xs">
                <div><span class="font-semibold">ID proyecto:</span> {{ $project->id_proyecto ?: '-' }}</div>
                <div class="mt-1"><span class="font-semibold">BPIN:</span> {{ $project->bipin ?: '-' }}</div>
                <div class="mt-1"><span class="font-semibold">Duración (meses):</span> {{ $project->duracion_meses ?: '-' }}</div>
                <div class="mt-1"><span class="font-semibold">Población objetivo:</span> {{ $project->poblacion_objetivo ?: '-' }}</div>
                <div class="mt-1"><span class="font-semibold">Fecha creación:</span> {{ optional($project->fecha_creacion)->format('Y-m-d') ?: '-' }}</div>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4">
        <h4 class="text-sm font-semibold text-gray-900">Perfil Banco</h4>
        <div class="mt-3 grid gap-2 md:grid-cols-2">
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3 text-xs">
                <div><span class="font-semibold">Pilar:</span> {{ $profile?->pilar ?: '-' }}</div>
                <div class="mt-1"><span class="font-semibold">Eje:</span> {{ $profile?->eje ?: '-' }}</div>
                <div class="mt-1"><span class="font-semibold">Línea:</span> {{ $profile?->linea ?: '-' }}</div>
                <div class="mt-1"><span class="font-semibold">Programa:</span> {{ $profile?->programa ?: '-' }}</div>
                <div class="mt-1"><span class="font-semibold">Subprograma:</span> {{ $profile?->subprograma ?: '-' }}</div>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3 text-xs">
                <div><span class="font-semibold">Cod meta:</span> {{ $profile?->meta_plan_codigo ?: '-' }}</div>
                <div class="mt-1"><span class="font-semibold">Meta:</span> {{ $profile?->meta_plan_nombre ?: '-' }}</div>
                <div class="mt-1"><span class="font-semibold">Cod fuente:</span> {{ $profile?->codigo_fuente ?: '-' }}</div>
                <div class="mt-1"><span class="font-semibold">Fuente:</span> {{ $profile?->nombre_fuente ?: '-' }}</div>
                <div class="mt-1"><span class="font-semibold">Beneficiarios:</span> {{ isset($profile?->beneficiarios) ? number_format((int) $profile->beneficiarios, 0, ',', '.') : '-' }}</div>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4">
        <h4 class="text-sm font-semibold text-gray-900">Avance por subgrupo</h4>
        <div class="mt-3 space-y-2">
            @foreach ($progress['groups'] as $group)
                <div>
                    <div class="mb-1 flex items-center justify-between text-xs">
                        <span class="font-medium text-gray-700">{{ $group['label'] }}</span>
                        <span class="text-gray-500">{{ $group['done'] }} / {{ $group['total'] }} ({{ $group['percent'] }}%)</span>
                    </div>
                    <div class="h-2 w-full rounded bg-gray-100">
                        <div class="h-2 rounded bg-primary-600" style="width: {{ $group['percent'] }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
