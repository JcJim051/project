<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Gestionar proyecto') }}
                </h2>
                <p class="text-sm text-gray-500">Proyecto: {{ $project->nombre }}</p>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <a href="{{ route('projects.checklist', $project) }}" class="text-indigo-600 hover:text-indigo-800">Ir a Requisitos</a>
                <a href="{{ route('projects.documents', $project) }}" class="text-indigo-600 hover:text-indigo-800">Generar certificaciones</a>
                <a href="{{ route('projects.index') }}" class="text-gray-500 hover:text-gray-700">Volver</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6"
            x-data="{
                q: '',
                onlyMissing: false,
                openAll: false,
                matches(name, doc, num, hasEvidence) {
                    const text = `${name} ${doc} ${num}`.toLowerCase();
                    const q = this.q.trim().toLowerCase();
                    if (this.onlyMissing && hasEvidence) return false;
                    if (!q) return true;
                    return text.includes(q);
                }
            }"
        >
            @if (session('status'))
                <div class="rounded-md bg-emerald-50 p-4 text-emerald-700 text-sm">
                    {{ session('status') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-rose-50 p-4 text-rose-700 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            @if (!$driveConnected)
                <div class="rounded-md bg-amber-50 p-4 text-amber-700 text-sm flex items-center justify-between">
                    <span>Conecta Google Drive para sincronizar evidencias automáticamente.</span>
                    <a href="{{ route('drive.auth', ['return' => route('projects.manage', $project)]) }}" class="text-amber-700 font-semibold hover:text-amber-800">
                        Conectar Drive
                    </a>
                </div>
            @elseif (!$project->drive_folder_id)
                <div class="rounded-md bg-amber-50 p-4 text-amber-700 text-sm">
                    Este proyecto no tiene carpeta de Drive configurada. Agrega la ruta en “Editar proyecto”.
                </div>
            @elseif ($driveConnected && $driveReady)
                <div class="rounded-md bg-sky-50 px-4 py-2 text-sky-700 text-sm flex items-center justify-between">
                    <span>Drive conectado.</span>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('projects.manage', $project) }}?sync=1" class="px-3 py-1.5 rounded-md bg-white/70 text-sky-700 text-xs font-semibold hover:bg-white">
                            Sincronizar ahora
                        </a>
                        <a href="{{ route('projects.manage', $project) }}?sync=1&debug=1" class="px-3 py-1.5 rounded-md bg-white/70 text-sky-700 text-xs font-semibold hover:bg-white">
                            Sincronizar + reporte
                        </a>
                    </div>
                </div>
            @endif

            @if ($syncReport)
                <div class="rounded-md border border-sky-100 bg-sky-50/40 p-4 text-sm text-sky-800 space-y-3">
                    <div class="font-semibold">Reporte de sincronización</div>
                    @php
                        $folders = $syncReport['folders'] ?? [];
                        $matchedByFolder = collect($syncReport['matched'] ?? [])->groupBy('folder');
                        $unmatchedByFolder = collect($syncReport['unmatched'] ?? [])->groupBy('folder');
                    @endphp
                    <div>Total archivos detectados: {{ $syncReport['total'] }}</div>
                    <div>Coincidencias: {{ count($syncReport['matched']) }} | Sin coincidencia: {{ count($syncReport['unmatched']) }}</div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($folders as $folderName => $folderId)
                            <div class="rounded-md border border-sky-100 bg-white/60 p-3 text-xs text-sky-700 space-y-2">
                                <div class="font-semibold">{{ $folderName }}</div>
                                @if (!$folderId)
                                    <div>No se encontró la carpeta en Drive.</div>
                                    @continue
                                @endif
                                @if ($matchedByFolder->get($folderName, collect())->isNotEmpty())
                                    <div>
                                        <div class="font-semibold mb-1">Coincidencias</div>
                                        <div class="space-y-1">
                                            @foreach ($matchedByFolder->get($folderName, collect()) as $item)
                                                <div>Archivo: {{ $item['file'] }} → {{ $item['requirement'] }}</div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                @if ($unmatchedByFolder->get($folderName, collect())->isNotEmpty())
                                    <div>
                                        <div class="font-semibold mb-1">Sin coincidencia</div>
                                        <div class="space-y-1">
                                            @foreach ($unmatchedByFolder->get($folderName, collect()) as $item)
                                                <div>Archivo: {{ $item['name'] }}</div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 space-y-6">
                    <div class="rounded-lg border border-gray-200 p-4">
                        <div class="flex flex-nowrap items-center justify-between">
                            <div class="w-1/3 flex flex-col items-center text-center">
                                <h3 class="text-lg font-semibold text-gray-800">Requisitos marcados</h3>
                                <p class="text-sm text-gray-500">Solo se muestran los requisitos en estado “aplica”.</p>
                            </div>
                            <div class="w-1/3 flex flex-col items-center justify-center text-center">
                                <div class="text-xs font-semibold text-gray-700">Avance general</div>
                                <div class="text-[11px] text-gray-500 mb-2">{{ $overallPercent }}% ({{ $folderProgress ? array_sum(array_column($folderProgress, 'done')) : 0 }} de {{ $folderProgress ? array_sum(array_column($folderProgress, 'total')) : 0 }})</div>
                                <div class="w-full max-w-xs h-2 rounded-full bg-gray-100">
                                    <div class="h-2 rounded-full bg-emerald-500" style="width: {{ $overallPercent }}%"></div>
                                </div>
                            </div>
                            <div class="w-1/3 flex flex-col gap-2 items-center text-center">
                                <div>
                                    <label class="text-xs font-medium text-gray-600">Buscar requisito o documento</label>
                                    <input type="text" x-model="q" placeholder="Ej: Hoja de Control, MGA..."
                                        class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                </div>
                                <div class="flex items-center justify-between gap-2 text-xs">
                                    <label class="inline-flex items-center gap-2 text-gray-600">
                                        <input type="checkbox" class="rounded border-gray-300" x-model="onlyMissing">
                                        Solo sin evidencia
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <button type="button" class="px-2.5 py-1.5 rounded-md border border-gray-200 text-gray-700 hover:bg-gray-50"
                                            @click="openAll = true">
                                            Expandir
                                        </button>
                                        <button type="button" class="px-2.5 py-1.5 rounded-md border border-gray-200 text-gray-700 hover:bg-gray-50"
                                            @click="openAll = false">
                                            Contraer
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @if ($requirements->isEmpty())
                        <div class="rounded-md border border-dashed border-gray-300 p-6 text-center text-gray-500">
                            No hay requisitos marcados. Usa la vista de Requisitos para seleccionarlos.
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach ($manageSections as $section)
                                    @php
                                        $folderName = $section['name'];
                                        $items = $section['items'];
                                        $progress = $folderProgress[$folderName] ?? ['total' => $items->count(), 'done' => 0, 'percent' => 0];
                                    @endphp
                                    <details class="rounded-lg border border-gray-200" :open="openAll">
                                        <summary class="cursor-pointer list-none px-4 py-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    @if (($section['group_code'] ?? '') !== '999')
                                                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700">
                                                            {{ $section['group_code'] ?? '00' }}
                                                        </span>
                                                    @endif
                                                    <div class="text-sm font-semibold text-gray-800">{{ $folderName }}</div>
                                                </div>
                                                <div class="text-xs text-gray-500">{{ $progress['done'] }} de {{ $progress['total'] }} con evidencia</div>
                                            </div>
                                            <div class="w-full sm:w-56 h-2 rounded-full bg-gray-100">
                                                <div class="h-2 rounded-full bg-emerald-500" style="width: {{ $progress['percent'] }}%"></div>
                                            </div>
                                        </summary>
                                        <div class="px-4 pb-4">
                                            @php
                                                $isEstudios = ($section['group_code'] ?? '') === '05';
                                                $studyGroups = $isEstudios
                                                    ? $items->groupBy(function ($req) use ($folderName) {
                                                        return trim((string) ($req->requisito ?: $req->texto ?: $folderName));
                                                    })
                                                    : collect(['__single__' => $items]);
                                            @endphp

                                            <div class="space-y-4">
                                                @foreach ($studyGroups as $studyName => $studyItems)
                                                    @if ($isEstudios)
                                                        <div class="rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-2">
                                                            <div class="text-xs font-semibold text-slate-500">Estudio</div>
                                                            <div class="text-sm font-semibold text-slate-800">{{ $studyName }}</div>
                                                        </div>
                                                    @endif

                                                    <div class="grid gap-4 lg:grid-cols-3">
                                                        @foreach ($studyItems as $req)
                                                            @php
                                                                $reqEvidences = $evidences[$req->id] ?? collect();
                                                                $visibleEvidences = $reqEvidences->filter(function ($item) use ($req) {
                                                                    if (($item->drive_folder_name ?? null) !== ($req->carpeta ?? null)) {
                                                                        return false;
                                                                    }
                                                                    $name = strtolower($item->drive_file_name ?? '');
                                                                    $isPdf = $item->drive_mime_type === 'application/pdf' || str_ends_with($name, '.pdf');
                                                                    $isEditable = in_array($item->drive_mime_type, [
                                                                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                                                        'application/msword',
                                                                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                                                        'application/vnd.ms-excel',
                                                                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                                                                        'application/vnd.ms-powerpoint',
                                                                        'application/vnd.ms-project',
                                                                        'application/x-msproject',
                                                                    ], true) || preg_match('/\.(docx?|xlsx?|pptx?|mpp)$/', $name);
                                                                    return $isPdf || $isEditable;
                                                                });
                                                                $calcNumeracion = $renumerated[$req->id] ?? $req->codigo_interno ?? $req->numeracion;
                                                                $validEvidenceCount = $reqEvidences
                                                                    ->where('in_drive', true)
                                                                    ->where('drive_folder_name', $req->carpeta)
                                                                    ->count();
                                                                $hasEvidence = $validEvidenceCount > 0;
                                                            @endphp
                                                            <div class="rounded-lg border border-gray-100 p-4"
                                                                x-show="matches('{{ addslashes($req->requisito ?? '') }}','{{ addslashes($req->nombre_documento ?? '') }}','{{ addslashes($calcNumeracion ?? '') }}', {{ $hasEvidence ? 'true' : 'false' }})"
                                                            >
                                                                <div class="grid gap-3 lg:grid-cols-3">
                                                                    <div class="lg:col-span-2 space-y-2">
                                                                        <div class="text-sm font-semibold text-gray-800">
                                                                            {{ $req->nombre_documento ?: $req->requisito }}
                                                                        </div>
                                                                        <div class="text-xs text-gray-500">
                                                                            {{ $calcNumeracion ? 'Numeración: ' . $calcNumeracion : 'Sin numeración' }}
                                                                        </div>
                                                                    </div>
                                                                    <div class="flex lg:justify-end">
                                                                        <span class="inline-flex items-center h-6 rounded-full px-2.5 text-xs font-medium {{ $hasEvidence ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                                                                            {{ $hasEvidence ? $validEvidenceCount . ' evidencia(s) válida(s)' : 'Sin evidencia' }}
                                                                        </span>
                                                                    </div>
                                                                </div>

                                                                @if ($visibleEvidences->isNotEmpty())
                                                                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                                                        @foreach ($visibleEvidences as $evidence)
                                                                            @php
                                                                                $isValid = (bool) $evidence->in_drive;
                                                                            @endphp
                                                                            <div class="rounded-md border border-gray-100 p-2 text-xs">
                                                                                <div class="flex items-start justify-between gap-2">
                                                                                    <div class="font-medium text-gray-700 truncate">{{ $evidence->drive_file_name }}</div>
                                                                                    @if ($evidence->drive_file_id)
                                                                                        <a href="https://drive.google.com/file/d/{{ $evidence->drive_file_id }}/view" target="_blank" rel="noopener"
                                                                                            class="text-[11px] font-semibold text-indigo-600 hover:text-indigo-700">
                                                                                            Ver
                                                                                        </a>
                                                                                    @endif
                                                                                </div>
                                                                                <div class="text-[11px] text-gray-500">
                                                                                    {{ $isValid ? 'Formato válido' : 'Editable (no cuenta como evidencia)' }}
                                                                                </div>
                                                                            </div>
                                                                        @endforeach
                                                                    </div>
                                                                @endif

                                                                <form method="POST" action="{{ route('projects.manage.upload', [$project, $req]) }}" enctype="multipart/form-data" class="mt-3 grid gap-2 sm:grid-cols-3 sm:items-end">
                                                                    @csrf
                                                                    <div class="sm:col-span-2">
                                                                        <label class="text-xs font-medium text-gray-600">Cargar evidencias</label>
                                                                        <input type="file" name="archivos[]" multiple class="mt-1 block w-full text-xs text-gray-700">
                                                                    </div>
                                                                    <button type="submit" class="h-9 px-3 text-xs font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">
                                                                        Subir
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </details>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
