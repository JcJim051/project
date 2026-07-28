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

    @php
        $panelFolders = [];
        $panelRequirements = [];

        foreach ($manageSections as $sectionIndex => $section) {
            $folderName = $section['name'];
            $folderKey = 'f_' . $sectionIndex;
            $items = $section['items'];
            $sectionGroupCode = (string) ($section['group_code'] ?? '00');
            if ($sectionGroupCode === '999' && $items->isNotEmpty()) {
                $probe = $items->first();
                $probeCode = (string) ($probe->codigo_interno ?? $probe->numeracion ?? '');
                if (preg_match('/^(\d+)(?:[.\-]|$)/', $probeCode, $m)) {
                    $n = (int) $m[1];
                    if ($n >= 1 && $n <= 5) {
                        $sectionGroupCode = str_pad((string) $n, 2, '0', STR_PAD_LEFT);
                    }
                }
            }
            $progress = $folderProgress[$folderName] ?? ['total' => $items->count(), 'done' => 0, 'percent' => 0];

            $folderReqIds = [];

            foreach ($items as $req) {
                $reqEvidences = $evidences[$req->id] ?? collect();
                $visibleEvidences = $reqEvidences->filter(function ($item) use ($req) {
                    if (($item->drive_folder_name ?? null) !== ($req->carpeta ?? null)) {
                        return false;
                    }
                    return (bool) ($item->in_drive ?? false);
                })->values();

                $calcNumeracion = $renumerated[$req->id] ?? $req->codigo_interno ?? $req->numeracion;
                $requirementStatus = ($progressAnalysis['requirements'] ?? [])[$req->id] ?? [];
                $validEvidenceCount = (int) ($requirementStatus['valid_evidence_count'] ?? 0);
                $hasEvidence = (bool) ($requirementStatus['has_evidence'] ?? false);

                $studyName = null;
                if ($sectionGroupCode === '05') {
                    $studyName = trim((string) ($req->requisito ?: $req->texto ?: $folderName));
                }

                $folderReqIds[] = $req->id;
                $panelRequirements[$req->id] = [
                    'id' => $req->id,
                    'folder_key' => $folderKey,
                    'folder_name' => $folderName,
                    'group_code' => $sectionGroupCode,
                    'title' => $req->nombre_documento ?: $req->requisito,
                    'number' => $calcNumeracion,
                    'study_name' => $studyName,
                    'has_evidence' => $hasEvidence,
                    'valid_evidence_count' => $validEvidenceCount,
                    'evidence_format_rule' => $req->evidence_format_rule,
                    'evidence_format_label' => \App\Models\Requirement::evidenceFormatRuleLabel($req->evidence_format_rule),
                    'requires_license_permit_classification' => $req->requiresLicensePermitClassification(),
                    'upload_accept' => match ($req->evidence_format_rule) {
                        \App\Models\Requirement::EVIDENCE_RULE_EXCEL => '.xls,.xlsx,.xlsm,.csv',
                        \App\Models\Requirement::EVIDENCE_RULE_POWERPOINT => '.ppt,.pptx',
                        \App\Models\Requirement::EVIDENCE_RULE_KML => '.kml,.kmz,.klm',
                        \App\Models\Requirement::EVIDENCE_RULE_PROJECT => '.mpp',
                        \App\Models\Requirement::EVIDENCE_RULE_PDF => '.pdf',
                        default => '',
                    },
                    'upload_url' => route('projects.manage.upload', [$project, $req]),
                    'evidences' => $visibleEvidences->map(function ($evidence) use ($project, $req) {
                        return [
                            'name' => $evidence->drive_file_name,
                            'display_name' => $evidence->drive_file_name,
                            'file_id' => $evidence->drive_file_id,
                            'can_preview' => $evidence->canPreviewInPortal(),
                            'preview_url' => route('requirement-evidences.preview', ['evidence' => $evidence]),
                            'download_url' => route('requirement-evidences.download', ['evidence' => $evidence]),
                            'is_valid' => (bool) $evidence->in_drive,
                            'license_permit_status' => $evidence->license_permit_status,
                            'license_permit_status_label' => $evidence->licensePermitStatusLabel(),
                            'classify_url' => route('projects.requirements.classify_evidence', [$project, $req, $evidence]),
                        ];
                    })->all(),
                ];
            }

            $panelFolders[] = [
                'key' => $folderKey,
                'name' => $folderName,
                'group_code' => $sectionGroupCode,
                'done' => $progress['done'],
                'total' => $progress['total'],
                'percent' => $progress['percent'],
                'requirement_ids' => $folderReqIds,
            ];
        }

        $groupLabels = [
            '01' => 'Formulacion',
            '02' => 'Presupuesto',
            '03' => 'Certificaciones',
            '04' => 'Licencias y Permisos',
            '05' => 'Estudios y Disenos',
        ];

        $panelGroups = [];
        foreach ($groupLabels as $code => $label) {
            $subgroups = [];
            $groupReqIds = [];

            foreach ($panelFolders as $folder) {
                if (($folder['group_code'] ?? '') !== $code) {
                    continue;
                }

                $subReqIds = array_values(array_unique($folder['requirement_ids'] ?? []));
                $subDone = 0;
                foreach ($subReqIds as $reqId) {
                    if (($panelRequirements[$reqId]['has_evidence'] ?? false) === true) {
                        $subDone++;
                    }
                }
                $subTotal = count($subReqIds);
                $subPercent = $subTotal > 0 ? (int) round(($subDone / $subTotal) * 100) : 0;

                $subgroups[] = [
                    'key' => $folder['key'],
                    'name' => $folder['name'],
                    'requirement_ids' => $subReqIds,
                    'done' => $subDone,
                    'total' => $subTotal,
                    'percent' => $subPercent,
                ];

                $groupReqIds = array_merge($groupReqIds, $subReqIds);
            }

            $groupReqIds = array_values(array_unique($groupReqIds));
            $done = 0;
            foreach ($groupReqIds as $reqId) {
                if (($panelRequirements[$reqId]['has_evidence'] ?? false) === true) {
                    $done++;
                }
            }
            $total = count($groupReqIds);
            $percent = $total > 0 ? (int) round(($done / $total) * 100) : 0;

            $panelGroups[] = [
                'code' => $code,
                'label' => $label,
                'subgroups' => $subgroups,
                'done' => $done,
                'total' => $total,
                'percent' => $percent,
            ];
        }
    @endphp

    <style>
        .overall-progress-fill--danger {
            background: linear-gradient(90deg, #f87171 0%, #dc2626 100%);
        }
        .overall-progress-fill--warning {
            background: linear-gradient(90deg, #fbbf24 0%, #d97706 100%);
        }
        .overall-progress-fill--success {
            background: linear-gradient(90deg, #4ade80 0%, #16a34a 100%);
        }
        .manage-shell {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 0.85rem;
        }
        .pane {
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            background: #fff;
        }
        .pane-split {
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        .pane-head {
            flex: 0 0 auto;
        }
        .pane-body {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            padding-right: 0.125rem;
        }
        .pane-inner {
            border: 1px solid #eef2f7;
            border-radius: 0.65rem;
            background: #fbfdff;
            padding: 0.75rem;
        }
        .tone-01 {
            background: #f4f9ff;
            border-color: #dbeafe;
        }
        .tone-02 {
            background: #f8fbf4;
            border-color: #dcfce7;
        }
        .tone-03 {
            background: #fffaf3;
            border-color: #fde68a;
        }
        .tone-04 {
            background: #f7f7ff;
            border-color: #e0e7ff;
        }
        .tone-05 {
            background: #fff7fb;
            border-color: #fbcfe8;
        }
        .group-tone-01 {
            background: #eff6ff;
        }
        .group-tone-02 {
            background: #f0fdf4;
        }
        .group-tone-03 {
            background: #fffbeb;
        }
        .group-tone-04 {
            background: #eef2ff;
        }
        .group-tone-05 {
            background: #fdf2f8;
        }
        .group-card {
            padding: 0.2rem;
        }
        .active-01 {
            border-color: #93c5fd !important;
            background: #dbeafe !important;
        }
        .active-02 {
            border-color: #86efac !important;
            background: #dcfce7 !important;
        }
        .active-03 {
            border-color: #fcd34d !important;
            background: #fef3c7 !important;
        }
        .active-04 {
            border-color: #a5b4fc !important;
            background: #e0e7ff !important;
        }
        .active-05 {
            border-color: #f9a8d4 !important;
            background: #fce7f3 !important;
        }
        .manage-main {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 1rem;
        }
        .manage-left {
            grid-column: span 6 / span 6;
        }
        .manage-right {
            grid-column: span 6 / span 6;
            display: grid;
            gap: 0.75rem;
            grid-template-rows: minmax(0, 1fr) minmax(0, 1fr);
            height: 74vh;
        }
        .group-btn {
            border-bottom: 1px solid #eef2f7;
        }
        @media (max-width: 900px) {
            .manage-main {
                grid-template-columns: 1fr;
            }
            .manage-left,
            .manage-right {
                grid-column: span 1 / span 1;
            }
            .manage-right {
                height: auto;
                grid-template-rows: auto auto;
            }
        }
    </style>

    <div class="py-8">
        <div
            class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6"
            x-data='{
                groups: @json($panelGroups),
                requirements: @json($panelRequirements),
                selectedGroupCode: null,
                selectedSubgroupKey: null,
                selectedRequirementId: null,
                showEvidence: {},
                onlyPendingGlobal: false,
                init() {
                    this.selectInitial();
                },
                groupByCode(code) {
                    return this.groups.find(g => g.code === code) || null;
                },
                subgroupByKey(group, key) {
                    if (!group) return null;
                    return (group.subgroups || []).find(s => s.key === key) || null;
                },
                requirementById(id) {
                    return this.requirements[id] || null;
                },
                requirementVisible(req) {
                    if (!req) return false;
                    if (!this.onlyPendingGlobal) return true;
                    return !req.has_evidence;
                },
                currentGroup() {
                    return this.selectedGroupCode ? this.groupByCode(this.selectedGroupCode) : null;
                },
                toneClass() {
                    const code = this.selectedGroupCode || "01";
                    return `tone-${code}`;
                },
                groupToneClass(code) {
                    return `group-tone-${code}`;
                },
                activeClassFor(code) {
                    return `active-${code}`;
                },
                groupSelectedClass(code) {
                    return `ring-1 ring-inset ${this.activeClassFor(code)}`;
                },
                currentSubgroup() {
                    const group = this.currentGroup();
                    if (!group) return null;
                    return this.subgroupByKey(group, this.selectedSubgroupKey);
                },
                selectInitial() {
                    const firstGroup = this.groups.find(g => (g.subgroups || []).length > 0);
                    if (!firstGroup) {
                        this.selectedGroupCode = null;
                        this.selectedSubgroupKey = null;
                        this.selectedRequirementId = null;
                        return;
                    }
                    this.selectedGroupCode = firstGroup.code;
                    const firstSubgroup = (firstGroup.subgroups || []).find(s => (s.requirement_ids || []).length > 0) || (firstGroup.subgroups || [])[0] || null;
                    this.selectedSubgroupKey = firstSubgroup ? firstSubgroup.key : null;
                    this.selectedRequirementId = firstSubgroup ? ((firstSubgroup.requirement_ids || [])[0] || null) : null;
                },
                selectGroup(code) {
                    const group = this.groupByCode(code);
                    if (!group) {
                        return;
                    }
                    this.selectedGroupCode = code;
                    const subgroup = (group.subgroups || []).find(s => s.key === this.selectedSubgroupKey && this.subgroupHasVisibleRequirements(s))
                        || (group.subgroups || []).find(s => this.subgroupHasVisibleRequirements(s))
                        || null;
                    this.selectedSubgroupKey = subgroup ? subgroup.key : null;
                    if (!subgroup) {
                        this.selectedRequirementId = null;
                        return;
                    }
                    const hasCurrentVisible = (subgroup.requirement_ids || [])
                        .map(id => this.requirementById(id))
                        .some(r => r && r.id === this.selectedRequirementId && this.requirementVisible(r));
                    if (!hasCurrentVisible) {
                        const firstVisible = (subgroup.requirement_ids || [])
                            .map(id => this.requirementById(id))
                            .find(r => this.requirementVisible(r));
                        this.selectedRequirementId = firstVisible ? firstVisible.id : null;
                    }
                },
                isGroupOpen(code) {
                    return this.selectedGroupCode === code;
                },
                selectSubgroup(groupCode, subgroupKey) {
                    const group = this.groupByCode(groupCode);
                    if (!group) return;
                    this.selectedGroupCode = groupCode;
                    this.selectedSubgroupKey = subgroupKey;
                    const subgroup = this.subgroupByKey(group, subgroupKey);
                    if (!subgroup) {
                        this.selectedRequirementId = null;
                        return;
                    }
                    const hasCurrentVisible = (subgroup.requirement_ids || [])
                        .map(id => this.requirementById(id))
                        .some(r => r && r.id === this.selectedRequirementId && this.requirementVisible(r));
                    if (!hasCurrentVisible) {
                        const firstVisible = (subgroup.requirement_ids || [])
                            .map(id => this.requirementById(id))
                            .find(r => this.requirementVisible(r));
                        this.selectedRequirementId = firstVisible ? firstVisible.id : null;
                    }
                },
                requirementsInSelectedSubgroup() {
                    const subgroup = this.currentSubgroup();
                    if (!subgroup) return [];
                    return (subgroup.requirement_ids || [])
                        .map(id => this.requirementById(id))
                        .filter(Boolean);
                },
                visibleRequirementsInSelectedSubgroup() {
                    const list = this.requirementsInSelectedSubgroup();
                    return list.filter(req => this.requirementVisible(req));
                },
                subgroupHasVisibleRequirements(subgroup) {
                    if (!subgroup) return false;
                    const list = (subgroup.requirement_ids || [])
                        .map(id => this.requirementById(id))
                        .filter(Boolean);
                    return list.some(req => this.requirementVisible(req));
                },
                groupHasVisibleRequirements(group) {
                    if (!group) return false;
                    return (group.subgroups || []).some(sub => this.subgroupHasVisibleRequirements(sub));
                },
                firstVisibleSelection() {
                    for (const group of this.groups) {
                        if (!this.groupHasVisibleRequirements(group)) continue;
                        for (const sub of (group.subgroups || [])) {
                            if (!this.subgroupHasVisibleRequirements(sub)) continue;
                            const req = (sub.requirement_ids || [])
                                .map(id => this.requirementById(id))
                                .find(r => this.requirementVisible(r));
                            if (req) {
                                return {
                                    groupCode: group.code,
                                    subgroupKey: sub.key,
                                    requirementId: req.id
                                };
                            }
                        }
                    }
                    return null;
                },
                toggleOnlyPendingGlobal() {
                    this.onlyPendingGlobal = !this.onlyPendingGlobal;
                    const currentReq = this.currentRequirement();
                    if (currentReq && this.requirementVisible(currentReq)) {
                        return;
                    }
                    const next = this.firstVisibleSelection();
                    if (!next) {
                        this.selectedGroupCode = null;
                        this.selectedSubgroupKey = null;
                        this.selectedRequirementId = null;
                        return;
                    }
                    this.selectedGroupCode = next.groupCode;
                    this.selectedSubgroupKey = next.subgroupKey;
                    this.selectedRequirementId = next.requirementId;
                },
                selectRequirement(folderKey, reqId) {
                    this.selectedRequirementId = reqId;
                    const req = this.requirementById(reqId);
                    if (!req) return;
                    this.selectedSubgroupKey = req.folder_key;
                    this.selectedGroupCode = req.group_code;
                },
                currentRequirement() {
                    return this.requirementById(this.selectedRequirementId);
                },
                firstEvidenceLink(req) {
                    if (!req || !Array.isArray(req.evidences)) return null;
                    const found = req.evidences.find(e => !!e.preview_url);
                    return found ? found.preview_url : null;
                },
                firstEvidenceDownloadLink(req) {
                    if (!req || !Array.isArray(req.evidences)) return null;
                    const found = req.evidences.find(e => !!e.download_url);
                    return found ? found.download_url : null;
                },
                goNextMissing() {
                    const list = this.requirementsInSelectedSubgroup();
                    if (list.length === 0) return;

                    const missingIds = list.filter(r => !r.has_evidence).map(r => r.id);
                    if (missingIds.length === 0) return;

                    const idx = missingIds.indexOf(this.selectedRequirementId);
                    const nextId = idx >= 0 && idx < missingIds.length - 1 ? missingIds[idx + 1] : missingIds[0];
                    const req = this.requirementById(nextId);
                    if (!req) return;
                    this.selectedRequirementId = nextId;
                    this.selectedSubgroupKey = req.folder_key;
                    this.selectedGroupCode = req.group_code;
                }
            }'
            x-init="init()"
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

            @if (!$driveConnected && auth()->user()?->isAdminUser())
                <div class="rounded-md bg-amber-50 p-4 text-amber-700 text-sm flex items-center justify-between">
                    <span>Conecta Google Drive para sincronizar evidencias automaticamente.</span>
                    <a href="{{ route('drive.auth', ['return' => route('projects.manage', $project)]) }}" class="text-amber-700 font-semibold hover:text-amber-800">
                        Conectar Drive
                    </a>
                </div>
            @elseif (!$driveConnected)
                <div class="rounded-md bg-amber-50 p-4 text-amber-700 text-sm">
                    Google Drive no está conectado por administración. Solicita al administrador conectar Drive para habilitar carga y sincronización.
                </div>
            @elseif (!$project->drive_folder_id)
                <div class="rounded-md bg-amber-50 p-4 text-amber-700 text-sm">
                    Este proyecto no tiene carpeta de Drive configurada. Agrega la ruta en Editar proyecto.
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
                <details class="rounded-md border border-sky-100 bg-sky-50/40 p-4 text-sm text-sky-800">
                    <summary class="cursor-pointer font-semibold">Reporte de sincronizacion (debug)</summary>
                    @php
                        $folders = $syncReport['folders'] ?? [];
                        $matchedByFolder = collect($syncReport['matched'] ?? [])->groupBy('folder');
                        $unmatchedByFolder = collect($syncReport['unmatched'] ?? [])->groupBy('folder');
                    @endphp
                    <div class="mt-3 space-y-3">
                        <div>Total archivos detectados: {{ $syncReport['total'] }}</div>
                        <div>Coincidencias: {{ count($syncReport['matched']) }} | Sin coincidencia: {{ count($syncReport['unmatched']) }}</div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($folders as $folderName => $folderId)
                                <div class="rounded-md border border-sky-100 bg-white/60 p-3 text-xs text-sky-700 space-y-2">
                                    <div class="font-semibold">{{ $folderName }}</div>
                                    @if (!$folderId)
                                        <div>No se encontro la carpeta en Drive.</div>
                                        @continue
                                    @endif
                                    @if ($matchedByFolder->get($folderName, collect())->isNotEmpty())
                                        <div>
                                            <div class="font-semibold mb-1">Coincidencias</div>
                                            <div class="space-y-1">
                                                @foreach ($matchedByFolder->get($folderName, collect()) as $item)
                                                    <div>Archivo: {{ $item['file'] }} -> {{ $item['requirement'] }}</div>
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
                </details>
            @endif

            <div class="manage-shell shadow-sm p-4 sm:p-6">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Requisitos marcados</h3>
                        <p class="text-sm text-gray-500">Panel maestro-detalle para gestionar evidencias por grupo.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            @click="toggleOnlyPendingGlobal()"
                            :aria-pressed="onlyPendingGlobal ? 'true' : 'false'"
                            class="px-2 py-1 rounded border text-xs transition"
                            :class="onlyPendingGlobal ? 'border-amber-300 bg-amber-50 text-amber-700' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'">
                            Solo pendientes
                        </button>
                        <div class="text-sm text-gray-600">
                            <span class="font-semibold text-gray-800">Avance general:</span>
                            {{ $overallPercent }}% · {{ (int) ($overallProgress['done'] ?? 0) }} de {{ (int) ($overallProgress['total'] ?? 0) }} documentos
                        </div>
                    </div>
                </div>
                @php
                    $overallProgressTone = $overallPercent >= 80
                        ? 'success'
                        : ($overallPercent >= 40 ? 'warning' : 'danger');
                @endphp
                <div class="mb-4">
                    <div class="h-1.5 w-44 rounded-full bg-gray-200">
                        <div class="overall-progress-fill--{{ $overallProgressTone }} h-1.5 rounded-full" style="width: {{ $overallPercent }}%"></div>
                    </div>
                </div>

                @if ($requirements->isEmpty())
                    <div class="rounded-md border border-dashed border-gray-300 p-6 text-center text-gray-500">
                        No hay requisitos marcados. Usa la vista de Requisitos para seleccionarlos.
                    </div>
                @else
                    <div class="manage-main">
                        <aside class="pane pane-split manage-left p-3">
                            <div class="pane-head mb-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2">
                                <div class="text-[11px] uppercase tracking-wide text-gray-500">Seccion</div>
                                <div class="text-sm font-semibold text-gray-800">Grupos de requisitos</div>
                            </div>
                            <div class="pane-body px-1 py-1 space-y-3">
                                <template x-for="group in groups" :key="group.code">
                                    <div x-show="groupHasVisibleRequirements(group)" class="rounded-md border border-gray-200 bg-white overflow-hidden">
                                        <button
                                            type="button"
                                            class="w-full text-left group-card"
                                            :class="[groupToneClass(group.code), isGroupOpen(group.code) ? groupSelectedClass(group.code) : '']"
                                            @click="selectGroup(group.code)">
                                            <div class="flex items-center justify-between gap-3 px-3 py-2">
                                                <div>
                                                    <div class="text-xs text-gray-500" x-text="group.code"></div>
                                                    <div class="text-sm font-semibold text-gray-800" x-text="group.label"></div>
                                                </div>
                                                <div class="text-xs text-gray-600" x-text="`${group.done}/${group.total}`"></div>
                                            </div>
                                            <div class="mt-1 mb-1 mx-3 h-1.5 rounded-full bg-gray-100">
                                                <div class="h-1.5 rounded-full bg-emerald-500" :style="`width:${group.percent}%`"></div>
                                            </div>
                                        </button>
                                        <div x-show="isGroupOpen(group.code)" class="px-3 pb-3 space-y-1">
                                            <template x-if="(group.subgroups || []).length === 0">
                                                <div class="text-xs text-gray-500">Sin subgrupos</div>
                                            </template>
                                            <template x-for="sub in (group.subgroups || [])" :key="`sub-${sub.key}`">
                                                <button
                                                    x-show="subgroupHasVisibleRequirements(sub)"
                                                    type="button"
                                                    @click="selectSubgroup(group.code, sub.key)"
                                                    class="w-full rounded-md border px-2 py-2 text-left transition"
                                                    :class="selectedSubgroupKey === sub.key ? activeClassFor(group.code) : 'border-gray-200 bg-white hover:bg-gray-50'">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <div class="text-xs font-medium text-gray-700 truncate" x-text="sub.name"></div>
                                                        <div class="text-[11px] text-gray-500" x-text="`${sub.done}/${sub.total}`"></div>
                                                    </div>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </aside>

                        <section class="manage-right">
                            <div class="pane pane-split p-3">
                                <div class="pane-head mb-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2">
                                    <div class="text-[11px] uppercase tracking-wide text-gray-500">Seccion</div>
                                    <div class="text-sm font-semibold text-gray-800">Requisitos del subgrupo activo</div>
                                </div>
                                <div class="pane-body">
                                    <div class="pane-inner" :class="toneClass()">
                                    <template x-if="currentSubgroup()">
                                        <div class="text-xs text-gray-600 mb-2" x-text="currentSubgroup().name"></div>
                                    </template>
                                    <template x-if="visibleRequirementsInSelectedSubgroup().length === 0">
                                        <div class="text-sm text-gray-500">No hay requisitos en el subgrupo seleccionado.</div>
                                    </template>
                                    <div class="space-y-1.5">
                                        <template x-for="req in visibleRequirementsInSelectedSubgroup()" :key="`right-top-${req.id}`">
                                            <button
                                                type="button"
                                                @click="selectRequirement(req.folder_key, req.id)"
                                                    class="w-full rounded-md border px-2 py-2 text-left transition"
                                                    :class="selectedRequirementId === req.id ? activeClassFor(selectedGroupCode || &quot;01&quot;) : 'border-gray-200 bg-white hover:bg-gray-50'">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <div class="text-xs font-medium text-gray-700 truncate" x-text="req.title"></div>
                                                        <template x-if="req.has_evidence && firstEvidenceLink(req)">
                                                            <div class="flex items-center gap-2 shrink-0">
                                                                <a :href="firstEvidenceLink(req)" target="_blank" rel="noopener" class="text-[8px] font-medium leading-none text-indigo-600 hover:text-indigo-700">Ver</a>
                                                                <a x-show="firstEvidenceDownloadLink(req)" :href="firstEvidenceDownloadLink(req)" class="text-[8px] font-medium leading-none text-emerald-700 hover:text-emerald-800">Descargar</a>
                                                            </div>
                                                        </template>
                                                        <template x-if="!(req.has_evidence && firstEvidenceLink(req))">
                                                            <span class="text-[11px] text-gray-500">Pendiente</span>
                                                        </template>
                                                    </div>
                                                    <div class="text-[11px] text-gray-500 mt-1" x-text="req.number ? `N° ${req.number}` : 'Sin numeracion'"></div>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="pane pane-split p-3">
                                <div class="pane-head mb-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2">
                                    <div class="text-[11px] uppercase tracking-wide text-gray-500">Seccion</div>
                                    <div class="text-sm font-semibold text-gray-800">Detalle del requisito activo</div>
                                </div>
                                <div class="pane-body">
                                    <div class="pane-inner space-y-4" :class="toneClass()">
                                        <template x-if="!currentRequirement()">
                                            <div class="text-sm text-gray-500">Selecciona un requisito para ver el detalle.</div>
                                        </template>

                                        <template x-if="currentRequirement()">
                                            <div class="space-y-4">
                                                <div class="text-sm font-semibold text-gray-800" x-text="currentRequirement().study_name || currentRequirement().title"></div>
                                                <div class="text-xs text-gray-700" x-text="currentRequirement().title"></div>
                                                <div class="text-xs text-gray-500" x-text="currentRequirement().number ? `Numeracion: ${currentRequirement().number}` : 'Sin numeracion'"></div>
                                                <div>
                                                    <span
                                                        class="inline-flex items-center h-7 rounded-full px-2.5 text-xs font-medium"
                                                        :class="currentRequirement().has_evidence ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                                                        x-text="currentRequirement().has_evidence ? `${currentRequirement().valid_evidence_count} evidencia(s)` : 'Sin evidencia'"></span>
                                                </div>

                                                <div class="text-[11px] text-gray-500">
                                                    Cuenta como válido: <span class="font-semibold" x-text="currentRequirement().evidence_format_label || 'Sin regla'"></span>
                                                </div>

                                                <div class="space-y-2">
                                                    <template x-if="(currentRequirement().evidences || []).length === 0">
                                                        <div class="text-xs text-gray-500">No hay evidencias visibles para este requisito.</div>
                                                    </template>
                                                    <template x-for="(evidence, i) in (currentRequirement().evidences || [])" :key="`evi-${i}`">
                                                        <div class="rounded-md border border-gray-200 p-2 text-xs">
                                                            <div class="flex items-start justify-between gap-2">
                                                                <div class="font-medium text-gray-700 truncate" x-text="evidence.name"></div>
                                                            <div class="flex items-center gap-2 shrink-0">
                                                                <a
                                                                    x-show="evidence.preview_url"
                                                                    :href="evidence.preview_url"
                                                                    target="_blank"
                                                                    rel="noopener"
                                                                    class="text-[8px] font-medium leading-none text-indigo-600 hover:text-indigo-700">
                                                                    Ver
                                                                </a>
                                                                <a
                                                                    x-show="evidence.download_url"
                                                                    :href="evidence.download_url"
                                                                    class="text-[8px] font-medium leading-none text-emerald-700 hover:text-emerald-800">
                                                                    Descargar
                                                                </a>
                                                                <span x-show="!evidence.preview_url && !evidence.download_url" class="text-[11px] text-gray-500">Pendiente</span>
                                                            </div>
                                                            </div>
                                                            <div class="text-[11px]" :class="evidence.is_valid ? 'text-emerald-600' : 'text-gray-500'" x-text="evidence.is_valid ? 'Formato valido' : 'Editable (no cuenta como evidencia)'"></div>
                                                        </div>
                                                    </template>
                                                </div>

                                                <form method="POST" enctype="multipart/form-data" :action="currentRequirement().upload_url" class="space-y-3 pt-1">
                                                    @csrf
                                                    <div>
                                                        <label class="text-xs font-medium text-gray-600">Cargar evidencias</label>
                                                        <input type="file" name="archivos[]" :multiple="!currentRequirement().requires_license_permit_classification" :accept="currentRequirement().upload_accept || null" class="mt-1 block w-full text-xs text-gray-700">
                                                        <select x-show="currentRequirement().requires_license_permit_classification" name="license_permit_status" class="mt-2 block w-full rounded-md border-gray-300 text-xs">
                                                            <option value="">Clasificar documento...</option>
                                                            <option value="application">Solicitud o radicado</option>
                                                            <option value="issued">Licencia o permiso expedido</option>
                                                        </select>
                                                    </div>
                                                    <button type="submit" class="w-full h-9 rounded-md bg-indigo-600 text-white text-xs font-medium hover:bg-indigo-700">
                                                        Subir
                                                    </button>
                                                </form>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
