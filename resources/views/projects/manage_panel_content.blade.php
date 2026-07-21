    @php
        $panelFolders = [];
        $panelRequirements = [];
        $progressStatuses = $progressAnalysis['requirements'] ?? [];

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
                })
                ->sortByDesc('id')
                ->unique(function ($item) {
                    return $item->drive_file_id ?: mb_strtolower((string) ($item->drive_file_name ?? ''));
                })
                ->values();

                $calcNumeracion = $renumerated[$req->id] ?? $req->codigo_interno ?? $req->numeracion;
                $status = $progressStatuses[$req->id] ?? [];
                $isCompositeParent = (bool) ($status['is_composite_parent'] ?? false);
                $compositeFolder = $status['composite_folder'] ?? null;
                $compositeDone = (int) ($status['composite_done'] ?? 0);
                $compositeTotal = (int) ($status['composite_total'] ?? 0);
                $validEvidenceCount = array_key_exists('valid_evidence_count', $status)
                    ? (int) $status['valid_evidence_count']
                    : $reqEvidences->where('in_drive', true)->count();
                $hasEvidence = array_key_exists('has_evidence', $status)
                    ? (bool) $status['has_evidence']
                    : $validEvidenceCount > 0;
                $fulfillmentSource = (string) ($status['fulfillment_source'] ?? 'none');
                if (!$isCompositeParent && $fulfillmentSource === 'none') {
                    $validSources = $reqEvidences
                        ->where('in_drive', true)
                        ->pluck('source')
                        ->filter()
                        ->map(fn ($source) => strtolower((string) $source))
                        ->values();
                    if ($validSources->contains('manual_link')) {
                        $fulfillmentSource = 'manual';
                    } elseif ($validSources->contains('auto_match') || $validSources->contains('drive')) {
                        $fulfillmentSource = 'auto';
                    } elseif ($validSources->contains('upload')) {
                        $fulfillmentSource = 'upload';
                    }
                }

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
                    'fulfillment_source' => $fulfillmentSource,
                    'is_composite_parent' => $isCompositeParent,
                    'composite_folder' => $compositeFolder,
                    'composite_done' => $compositeDone,
                    'composite_total' => $compositeTotal,
                    'count_in_progress' => (bool) ($status['count_in_progress'] ?? true),
                    'evidence_format_rule' => $req->evidence_format_rule,
                    'evidence_format_label' => \App\Models\Requirement::evidenceFormatRuleLabel($req->evidence_format_rule),
                    'upload_accept' => match ($req->evidence_format_rule) {
                        \App\Models\Requirement::EVIDENCE_RULE_EXCEL => '.xls,.xlsx,.xlsm,.csv',
                        \App\Models\Requirement::EVIDENCE_RULE_POWERPOINT => '.ppt,.pptx',
                        \App\Models\Requirement::EVIDENCE_RULE_KML => '.kml,.kmz,.klm',
                        \App\Models\Requirement::EVIDENCE_RULE_PROJECT => '.mpp',
                        \App\Models\Requirement::EVIDENCE_RULE_PDF => '.pdf',
                        default => '',
                    },
                    'composite_message' => $isCompositeParent
                        ? 'Este requisito se cumple automáticamente con los documentos activos de la carpeta ' . $compositeFolder . '.'
                        : null,
                    'upload_url' => route('projects.manage.upload', [$project, $req]),
                    'large_upload_init_url' => route('projects.requirements.uploads.init', [$project, $req]),
                    'edit_url' => route('filament.admin.resources.requirements.edit', ['record' => $req]),
                    'drive_files_url' => route('projects.drive.files', $project),
                    'link_drive_url' => route('projects.requirements.link_drive_file', [$project, $req]),
                    'bulk_link_url' => route('projects.requirements.link_drive_files_bulk', $project),
                    'evidences' => $visibleEvidences->map(function ($evidence) use ($project, $req) {
                        return [
                            'id' => $evidence->id,
                            'name' => $evidence->drive_file_name,
                            'display_name' => $evidence->drive_file_name,
                            'file_id' => $evidence->drive_file_id,
                            'can_preview' => $evidence->canPreviewInPortal(),
                            'preview_url' => route('requirement-evidences.preview', ['evidence' => $evidence]),
                            'download_url' => route('requirement-evidences.download', ['evidence' => $evidence]),
                            'source' => $evidence->source,
                            'is_valid' => (bool) $evidence->in_drive,
                            'unlink_url' => route('projects.requirements.unlink_drive_file', [$project, $req, $evidence]),
                            'delete_drive_url' => route('projects.requirements.delete_drive_file', [$project, $req, $evidence]),
                        ];
                    })->all(),
                    'history' => $reqEvidences
                        ->sortByDesc('id')
                        ->values()
                        ->map(function ($evidence) {
                            return [
                                'id' => $evidence->id,
                                'name' => $evidence->drive_file_name,
                                'display_name' => $evidence->drive_file_name,
                                'file_id' => $evidence->drive_file_id,
                                'can_preview' => $evidence->canPreviewInPortal(),
                                'preview_url' => route('requirement-evidences.preview', ['evidence' => $evidence]),
                                'download_url' => route('requirement-evidences.download', ['evidence' => $evidence]),
                                'source' => $evidence->source,
                                'is_valid' => (bool) $evidence->in_drive,
                                'created_at' => optional($evidence->created_at)->format('Y-m-d H:i'),
                            ];
                        })
                        ->all(),
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
                'is_other_certifications' => \Illuminate\Support\Str::contains(
                    \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii((string) $folderName)),
                    'otras certificaciones'
                ),
            ];
        }

        $hasOtherCertificationsFolder = collect($panelFolders)->contains(function ($folder) {
            return (bool) ($folder['is_other_certifications'] ?? false);
        });
        if (!$hasOtherCertificationsFolder) {
            $panelFolders[] = [
                'key' => 'f_other_certifications_empty',
                'name' => '3.3 Otras Certificaciones',
                'group_code' => '03',
                'done' => 0,
                'total' => 0,
                'percent' => 0,
                'requirement_ids' => [],
                'is_other_certifications' => true,
            ];
        }

        $firstRequirementId = null;
        if (!empty($panelRequirements)) {
            $firstRequirementId = (int) array_key_first($panelRequirements);
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
                    'is_other_certifications' => (bool) ($folder['is_other_certifications'] ?? false),
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
        [x-cloak] { display: none !important; }
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
            grid-template-rows: minmax(0, 3fr) minmax(0, 2fr);
            height: 74vh;
        }
        .group-btn {
            border-bottom: 1px solid #eef2f7;
        }

        .gp-modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-y: auto;
            padding: 0.75rem;
            background: rgba(0, 0, 0, 0.4);
        }
        .gp-modal-card {
            width: min(48rem, calc(100vw - 1.5rem));
            max-height: 88vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            background: #fff;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,.1), 0 8px 10px -6px rgba(0,0,0,.1);
        }
        .gp-modal-card-lg {
            width: min(64rem, calc(100vw - 1.5rem));
            max-height: 88vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            background: #fff;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,.1), 0 8px 10px -6px rgba(0,0,0,.1);
        }
        .gp-modal-head,
        .gp-modal-foot {
            flex: 0 0 auto;
            background: #fff;
        }
        .gp-modal-body {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
        }
        .gp-modal-list {
            max-height: 38vh;
            overflow-y: auto;
        }
        .upload-modal-card {
            width: min(42rem, calc(100vw - 1.5rem));
        }
        .upload-modal-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.125rem;
            border-bottom: 1px solid #eef2f7;
            background: #ffffff;
        }
        .upload-modal-title-row {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: .5rem;
        }
        .upload-modal-kicker {
            display: inline-flex;
            align-items: center;
            border: 1px solid #bbf7d0;
            border-radius: 999px;
            background: #f0fdf4;
            color: #166534;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .04em;
            padding: .25rem .55rem;
            text-transform: uppercase;
        }
        .upload-modal-title {
            color: #111827;
            font-size: .95rem;
            font-weight: 700;
            line-height: 1.25rem;
        }
        .upload-modal-help {
            margin-top: .45rem;
            max-width: 36rem;
            color: #6b7280;
            font-size: .75rem;
            line-height: 1.15rem;
        }
        .upload-modal-close {
            width: 2rem;
            height: 2rem;
            border: 1px solid #e5e7eb;
            border-radius: 999px;
            background: #fff;
            color: #6b7280;
            font-size: 1.1rem;
            line-height: 1;
        }
        .upload-modal-close:hover {
            background: #f9fafb;
            color: #111827;
        }
        .upload-modal-body {
            padding: .85rem 1.125rem;
            display: grid;
            gap: .75rem;
        }
        .upload-card {
            border: 1px solid #e5e7eb;
            border-radius: .7rem;
            background: #f9fafb;
            padding: .85rem;
        }
        .upload-card-top {
            display: flex;
            justify-content: space-between;
            gap: .75rem;
            align-items: flex-start;
        }
        .upload-status-line {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .45rem;
        }
        .upload-status-badge {
            display: inline-flex;
            align-items: center;
            border: 1px solid;
            border-radius: 999px;
            padding: .25rem .6rem;
            font-size: .72rem;
            font-weight: 700;
        }
        .upload-size-text {
            color: #6b7280;
            font-size: .76rem;
            font-weight: 600;
        }
        .upload-file-name {
            margin-top: .55rem;
            color: #111827;
            font-size: .86rem;
            font-weight: 700;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .upload-target {
            margin-top: .45rem;
            border: 1px solid #e5e7eb;
            border-radius: .5rem;
            background: #fff;
            color: #4b5563;
            font-size: .75rem;
            line-height: 1.05rem;
            padding: .45rem .6rem;
        }
        .upload-error {
            margin-top: .45rem;
            border: 1px solid #fecdd3;
            border-radius: .5rem;
            background: #fff1f2;
            color: #be123c;
            font-size: .75rem;
            font-weight: 600;
            padding: .45rem .6rem;
        }
        .upload-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .4rem;
        }
        .upload-btn {
            border: 1px solid #d1d5db;
            border-radius: .5rem;
            background: #fff;
            color: #374151;
            font-size: .75rem;
            font-weight: 700;
            padding: .45rem .65rem;
        }
        .upload-btn-danger {
            border-color: #fecdd3;
            background: #fff1f2;
            color: #be123c;
        }
        .upload-btn-success {
            border-color: #bbf7d0;
            background: #f0fdf4;
            color: #166534;
        }
        .upload-progress-meta {
            display: flex;
            justify-content: space-between;
            margin-top: .75rem;
            margin-bottom: .25rem;
            color: #6b7280;
            font-size: .7rem;
            font-weight: 700;
        }
        .upload-progress-track {
            height: .55rem;
            overflow: hidden;
            border: 1px solid #d1fae5;
            border-radius: 999px;
            background: #ecfdf5;
        }
        .upload-progress-bar {
            height: 100%;
            border-radius: 999px;
            transition: width .25s ease;
        }
        .upload-modal-foot-note {
            color: #6b7280;
            font-size: .73rem;
        }
        @media (max-width: 640px) {
            .upload-card-top {
                flex-direction: column;
            }
            .upload-actions {
                justify-content: flex-start;
            }
        }
        @media (max-height: 760px) {
            .gp-modal-card,
            .gp-modal-card-lg {
                max-height: 94vh;
            }
            .gp-modal-list {
                max-height: 32vh;
            }
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

    <script>
        function projectManagePanelData() {
            return {
                groups: @json($panelGroups),
                requirements: @json($panelRequirements),
                selectedGroupCode: null,
                selectedSubgroupKey: null,
                selectedRequirementId: null,
                showEvidence: {},
                onlyPendingGlobal: false,
                csrfToken: @js(csrf_token()),
                drivePickerOpen: false,
                drivePickerLoading: false,
                drivePickerQuery: "",
                drivePickerExt: "",
                drivePickerFiles: [],
                drivePickerSelected: [],
                drivePickerMessage: "",
                bulkOpen: false,
                bulkLoading: false,
                bulkFiles: [],
                bulkRows: [],
                bulkReport: null,
                uploadBusy: false,
                uploadQueue: [],
                uploadActiveCount: 0,
                uploadMaxConcurrent: 1,
                uploadDefaultChunkSize: 8388608,
                uploadQueueSeq: 0,
                uploadModalOpen: false,
                uploadMessage: '',
                uploadMessageType: '',
                uploadTimer: null,
                historyOpen: false,
                deleteConfirmOpen: false,
                deleteConfirmEvidence: null,
                deleteConfirmText: '',
                deleteConfirmBusy: false,
                deleteConfirmError: '',
                customCertificationUrl: @js(route('projects.manage.custom_certifications.store', $project)),
                customCertificationOpen: false,
                customCertificationName: '',
                customCertificationBusy: false,
                customCertificationError: '',
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
                uploadButtonClass() {
                    const code = this.selectedGroupCode || "01";
                    if (code === "01") return "bg-blue-300 hover:bg-blue-400 ring-blue-200";
                    if (code === "02") return "bg-emerald-300 hover:bg-emerald-400 ring-emerald-200";
                    if (code === "03") return "bg-amber-300 hover:bg-amber-400 ring-amber-200";
                    if (code === "04") return "bg-indigo-300 hover:bg-indigo-400 ring-indigo-200";
                    return "bg-pink-300 hover:bg-pink-400 ring-pink-200";
                },
                groupSelectedClass(code) {
                    return `ring-1 ring-inset ${this.activeClassFor(code)}`;
                },
                currentSubgroup() {
                    const group = this.currentGroup();
                    if (!group) return null;
                    return this.subgroupByKey(group, this.selectedSubgroupKey);
                },
                isOtherCertificationsSubgroup() {
                    const subgroup = this.currentSubgroup();
                    if (!subgroup) return false;
                    if (subgroup.is_other_certifications) return true;
                    const name = this.normalizeText(subgroup.name || '');
                    return (this.selectedGroupCode === '03' || name.includes('certificaciones'))
                        && (name.includes('3 3 otras certificaciones') || name.includes('otras certificaciones'));
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
                    if (this.bulkOpen) {
                        this.prepareBulkRows();
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
                    if (subgroup.is_other_certifications) return true;
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
                    this.$nextTick(() => {
                        if (this.$refs.requirementUploadInput) {
                            this.$refs.requirementUploadInput.value = '';
                        }
                    });
                },
                currentRequirement() {
                    return this.requirementById(this.selectedRequirementId);
                },
                fulfillmentLabel(req) {
                    if (!req) return "Pendiente";
                    if (req.is_composite_parent) {
                        if (req.has_evidence) return "Sub-grupo completado";
                        return `${req.composite_done || 0}/${req.composite_total || 0} documentos requeridos cargados`;
                    }
                    if (!req.has_evidence) return "Pendiente";
                    return "Cargado";
                },
                fulfillmentClass(req) {
                    if (!req) return "bg-gray-100 text-gray-600";
                    if (req.is_composite_parent) {
                        return req.has_evidence ? "" : "bg-amber-100 text-amber-700";
                    }
                    if (!req.has_evidence) return "bg-gray-100 text-gray-600";
                    if (["manual", "auto", "upload"].includes(req.fulfillment_source)) return "";
                    return "bg-sky-100 text-sky-700";
                },
                fulfillmentStyle(req) {
                    if (!req) return "";
                    const shouldShowLoadedBadge = req.has_evidence && (
                        req.is_composite_parent || ["manual", "auto", "upload"].includes(req.fulfillment_source)
                    );
                    if (!shouldShowLoadedBadge) return "";
                    return "background:#dcfce7;color:#166534;border:1px solid #bbf7d0;font-weight:700;";
                },
                evidenceCountLabel(req) {
                    if (!req) return "Sin evidencia";
                    if (req.is_composite_parent) {
                        return `${req.composite_done || 0} de ${req.composite_total || 0} documento(s) requerido(s)`;
                    }
                    return req.has_evidence ? `${req.valid_evidence_count} evidencia(s)` : "Sin evidencia";
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
                currentEvidence(req) {
                    if (!req || !Array.isArray(req.evidences) || req.evidences.length === 0) return null;
                    return req.evidences[0] || null;
                },
                currentDisplayName(req) {
                    const e = this.currentEvidence(req);
                    return e?.name || req?.title || '';
                },
                openHistoryModal() {
                    const req = this.currentRequirement();
                    if (!req) return;
                    this.historyOpen = true;
                },
                closeHistoryModal() {
                    this.historyOpen = false;
                },
                async loadDriveFiles(req) {
                    if (!req) return;
                    this.drivePickerLoading = true;
                    this.drivePickerMessage = "";
                    const params = new URLSearchParams({
                        requirement_id: String(req.id),
                        per_page: "200",
                    });
                    if (this.drivePickerQuery.trim() !== "") params.set("q", this.drivePickerQuery.trim());
                    if (this.drivePickerExt.trim() !== "") params.set("ext", this.drivePickerExt.trim());
                    try {
                        const response = await fetch(`${req.drive_files_url}?${params.toString()}`, {
                            headers: {
                                "Accept": "application/json",
                                "X-Requested-With": "XMLHttpRequest",
                            },
                            credentials: "same-origin",
                        });
                        const data = await response.json();
                        if (!response.ok) throw new Error(data.message || "No se pudo listar archivos de Drive.");
                        this.drivePickerFiles = data.data || [];
                        this.drivePickerMessage = `Encontrados: ${data.total || 0} archivo(s).`;
                    } catch (error) {
                        this.drivePickerFiles = [];
                        this.drivePickerMessage = error.message || "Error listando archivos de Drive.";
                    } finally {
                        this.drivePickerLoading = false;
                    }
                },
                async openDrivePicker() {
                    const req = this.currentRequirement();
                    if (!req || req.is_composite_parent) return;
                    this.drivePickerOpen = true;
                    this.drivePickerSelected = [];
                    await this.loadDriveFiles(req);
                },
                closeDrivePicker() {
                    this.drivePickerOpen = false;
                    this.drivePickerSelected = [];
                    this.drivePickerMessage = "";
                },
                async submitManualLink() {
                    const req = this.currentRequirement();
                    if (!req || this.drivePickerSelected.length === 0) return;
                    this.drivePickerLoading = true;
                    this.drivePickerMessage = "";
                    try {
                        const response = await fetch(req.link_drive_url, {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/json",
                                "Accept": "application/json",
                                "X-CSRF-TOKEN": this.csrfToken,
                                "X-Requested-With": "XMLHttpRequest",
                            },
                            credentials: "same-origin",
                            body: JSON.stringify({
                                file_ids: this.drivePickerSelected,
                            }),
                        });
                        const data = await response.json();
                        if (!response.ok) throw new Error(data.message || "No se pudo vincular el archivo.");
                        window.location.reload();
                    } catch (error) {
                        this.drivePickerMessage = error.message || "Error vinculando archivos.";
                    } finally {
                        this.drivePickerLoading = false;
                    }
                },
                normalizeText(v) {
                    return (v || "")
                        .normalize("NFD")
                        .replace(/[\u0300-\u036f]/g, "")
                        .toLowerCase()
                        .replace(/[^a-z0-9 ]+/g, " ")
                        .replace(/\s+/g, " ")
                        .trim();
                },
                suggestedFileIdFor(req, files) {
                    const target = this.normalizeText(req.title);
                    if (!target) return "";
                    for (const file of files) {
                        const base = this.normalizeText((file.name || "").replace(/\.[^.]+$/, ""));
                        if (base === target || base.includes(target) || target.includes(base)) {
                            return file.id || "";
                        }
                    }
                    return "";
                },
                async openBulkLinker() {
                    const reqs = this.visibleRequirementsInSelectedSubgroup();
                    if (reqs.length === 0) return;
                    const sample = reqs[0];
                    this.bulkOpen = true;
                    this.bulkLoading = true;
                    this.bulkReport = null;
                    try {
                        const params = new URLSearchParams({
                            requirement_id: String(sample.id),
                            per_page: "200",
                        });
                        const response = await fetch(`${sample.drive_files_url}?${params.toString()}`, {
                            headers: {"Accept": "application/json", "X-Requested-With": "XMLHttpRequest"},
                            credentials: "same-origin",
                        });
                        const data = await response.json();
                        if (!response.ok) throw new Error(data.message || "No se pudo cargar archivos de Drive.");
                        this.bulkFiles = data.data || [];
                        this.prepareBulkRows();
                    } catch (error) {
                        this.bulkFiles = [];
                        this.bulkRows = [];
                        this.bulkReport = { error: error.message || "Error en vinculación masiva." };
                    } finally {
                        this.bulkLoading = false;
                    }
                },
                prepareBulkRows() {
                    const reqs = this.visibleRequirementsInSelectedSubgroup().filter(req => !req.is_composite_parent);
                    this.bulkRows = reqs.map(req => ({
                        requirement_id: req.id,
                        title: req.title,
                        selected_file_id: this.suggestedFileIdFor(req, this.bulkFiles),
                    }));
                },
                closeBulkLinker() {
                    this.bulkOpen = false;
                    this.bulkRows = [];
                    this.bulkFiles = [];
                    this.bulkReport = null;
                },
                async submitBulkLink() {
                    const req = this.currentRequirement();
                    if (!req) return;
                    const links = this.bulkRows
                        .filter(row => row.selected_file_id)
                        .map(row => ({
                            requirement_id: row.requirement_id,
                            file_id: row.selected_file_id,
                        }));
                    if (links.length === 0) return;

                    this.bulkLoading = true;
                    this.bulkReport = null;
                    try {
                        const response = await fetch(req.bulk_link_url, {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/json",
                                "Accept": "application/json",
                                "X-CSRF-TOKEN": this.csrfToken,
                                "X-Requested-With": "XMLHttpRequest",
                            },
                            credentials: "same-origin",
                            body: JSON.stringify({ links }),
                        });
                        const data = await response.json();
                        if (!response.ok) throw new Error(data.message || "No se pudo ejecutar la vinculación masiva.");
                        this.bulkReport = data;
                        setTimeout(() => window.location.reload(), 900);
                    } catch (error) {
                        this.bulkReport = { error: error.message || "Error en vinculación masiva." };
                    } finally {
                        this.bulkLoading = false;
                    }
                },
                async unlinkEvidence(evidence) {
                    if (!evidence || !evidence.unlink_url) return;
                    if (!confirm("¿Quitar esta asignación?")) return;
                    try {
                        const response = await fetch(evidence.unlink_url, {
                            method: "DELETE",
                            headers: {
                                "Accept": "application/json",
                                "X-CSRF-TOKEN": this.csrfToken,
                                "X-Requested-With": "XMLHttpRequest",
                            },
                            credentials: "same-origin",
                        });
                        const data = await response.json();
                        if (!response.ok) throw new Error(data.message || "No se pudo quitar la asignación.");
                        window.location.reload();
                    } catch (error) {
                        alert(error.message || "Error quitando la asignación.");
                    }
                },
                async deleteEvidenceFromDrive(evidence) {
                    if (!evidence || !evidence.delete_drive_url) return;
                    try {
                        const response = await fetch(evidence.delete_drive_url, {
                            method: "DELETE",
                            headers: {
                                "Content-Type": "application/json",
                                "Accept": "application/json",
                                "X-CSRF-TOKEN": this.csrfToken,
                                "X-Requested-With": "XMLHttpRequest",
                            },
                            credentials: "same-origin",
                            body: JSON.stringify({ confirmation: this.deleteConfirmText }),
                        });
                        const data = await response.json();
                        if (!response.ok || !data.ok) {
                            throw new Error(data.message || "No se pudo borrar el archivo en Drive.");
                        }
                        window.location.reload();
                    } catch (error) {
                        this.deleteConfirmError = error.message || "Error borrando archivo en Drive.";
                    } finally {
                        this.deleteConfirmBusy = false;
                    }
                },
                openDeleteConfirm(evidence) {
                    if (!evidence || !evidence.delete_drive_url) return;
                    this.deleteConfirmEvidence = evidence;
                    this.deleteConfirmText = '';
                    this.deleteConfirmError = '';
                    this.deleteConfirmBusy = false;
                    this.deleteConfirmOpen = true;
                },
                closeDeleteConfirm() {
                    if (this.deleteConfirmBusy) return;
                    this.deleteConfirmOpen = false;
                    this.deleteConfirmEvidence = null;
                    this.deleteConfirmText = '';
                    this.deleteConfirmError = '';
                },
                confirmDeleteEvidenceFromDrive() {
                    if (this.deleteConfirmText !== 'BORRAR' || !this.deleteConfirmEvidence || this.deleteConfirmBusy) return;
                    this.deleteConfirmBusy = true;
                    this.deleteEvidenceFromDrive(this.deleteConfirmEvidence);
                },
                setUploadMessage(type, text) {
                    this.uploadMessageType = type;
                    this.uploadMessage = text || '';
                    if (this.uploadTimer) {
                        clearTimeout(this.uploadTimer);
                    }
                    this.uploadTimer = setTimeout(() => {
                        this.uploadMessage = '';
                        this.uploadMessageType = '';
                    }, 6000);
                },
                requirementFulfillmentSource(req) {
                    if (!req || !Array.isArray(req.evidences)) return 'none';
                    const valid = req.evidences.filter(e => !!e.is_valid);
                    if (valid.some(e => String(e.source || '').toLowerCase() === 'manual_link')) return 'manual';
                    if (valid.some(e => ['auto_match','drive'].includes(String(e.source || '').toLowerCase()))) return 'auto';
                    if (valid.some(e => String(e.source || '').toLowerCase() === 'upload')) return 'upload';
                    return 'none';
                },
                applyRequirementUpdate(payload) {
                    if (!payload || !this.requirements[payload.id]) return;

                    const existing = this.requirements[payload.id];
                    existing.evidences = payload.evidences || [];
                    existing.history = payload.history || existing.history || [];
                    existing.has_evidence = !!payload.has_evidence;
                    existing.valid_evidence_count = Number(payload.valid_evidence_count || 0);
                    existing.fulfillment_source = this.requirementFulfillmentSource(existing);
                    this.recalculateProgressCounters();
                },
                recalculateProgressCounters() {
                    this.groups.forEach(group => {
                        let groupDone = 0;
                        let groupTotal = 0;

                        (group.subgroups || []).forEach(subgroup => {
                            const requirements = (subgroup.requirement_ids || [])
                                .map(id => this.requirementById(id))
                                .filter(Boolean);

                            const total = requirements.length;
                            const done = requirements.filter(req => !!req.has_evidence).length;

                            subgroup.total = total;
                            subgroup.done = done;
                            subgroup.percent = total > 0 ? Math.round((done / total) * 100) : 0;

                            groupTotal += total;
                            groupDone += done;
                        });

                        group.total = groupTotal;
                        group.done = groupDone;
                        group.percent = groupTotal > 0 ? Math.round((groupDone / groupTotal) * 100) : 0;
                    });
                },
                openCustomCertificationModal() {
                    this.customCertificationOpen = true;
                    this.customCertificationName = '';
                    this.customCertificationError = '';
                    this.customCertificationBusy = false;
                    this.$nextTick(() => {
                        if (this.$refs.customCertificationName) this.$refs.customCertificationName.focus();
                    });
                },
                closeCustomCertificationModal() {
                    if (this.customCertificationBusy) return;
                    this.customCertificationOpen = false;
                    this.customCertificationName = '';
                    this.customCertificationError = '';
                    if (this.$refs.customCertificationFile) this.$refs.customCertificationFile.value = '';
                },
                async submitCustomCertification() {
                    const name = (this.customCertificationName || '').trim();
                    if (!name || this.customCertificationBusy) {
                        this.customCertificationError = 'Escribe el nombre de la certificación.';
                        return;
                    }

                    const formData = new FormData();
                    formData.append('nombre_certificacion', name);
                    const fileInput = this.$refs.customCertificationFile;
                    if (fileInput && fileInput.files && fileInput.files.length > 0) {
                        formData.append('archivo', fileInput.files[0]);
                    }

                    this.customCertificationBusy = true;
                    this.customCertificationError = '';
                    try {
                        const response = await fetch(this.customCertificationUrl, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': this.csrfToken,
                            },
                            credentials: 'same-origin',
                        });
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok || !data.ok) {
                            throw new Error(data.message || 'No se pudo crear la certificación.');
                        }
                        window.location.reload();
                    } catch (error) {
                        this.customCertificationError = error.message || 'No se pudo crear la certificación.';
                    } finally {
                        this.customCertificationBusy = false;
                    }
                },
                queueStatusLabel(item) {
                    const labels = {
                        pending: 'En espera',
                        initializing: 'Preparando',
                        uploading: 'Cargando',
                        completed: 'Completado',
                        failed: 'Falló',
                        cancelled: 'Cancelado',
                    };
                    return labels[item?.status] || item?.status || 'Pendiente';
                },
                queueStatusClass(item) {
                    const status = item?.status || 'pending';
                    if (status === 'completed') return 'bg-emerald-100 text-emerald-800 border-emerald-300';
                    if (status === 'failed') return 'bg-rose-50 text-rose-700 border-rose-200';
                    if (status === 'cancelled') return 'bg-gray-50 text-gray-500 border-gray-200';
                    if (status === 'uploading' || status === 'initializing') return 'bg-blue-50 text-blue-700 border-blue-200';
                    return 'bg-amber-50 text-amber-700 border-amber-200';
                },
                queueStatusStyle(item) {
                    const status = item?.status || 'pending';
                    if (status === 'completed') return 'background:#dcfce7;color:#166534;border-color:#86efac;';
                    if (status === 'failed') return 'background:#fff1f2;color:#be123c;border-color:#fecdd3;';
                    if (status === 'cancelled') return 'background:#f8fafc;color:#64748b;border-color:#e2e8f0;';
                    if (status === 'uploading' || status === 'initializing') return 'background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe;';
                    return 'background:#fffbeb;color:#b45309;border-color:#fde68a;';
                },
                uploadProgressPercent(item) {
                    const progress = Number(item?.progress || 0);
                    return Math.max(0, Math.min(100, progress));
                },
                uploadProgressStyle(item) {
                    const progress = this.uploadProgressPercent(item);
                    return `width:${progress}%;background:linear-gradient(90deg,#16a34a,#84cc16);`;
                },
                formatBytes(bytes) {
                    const n = Number(bytes || 0);
                    if (n >= 1024 * 1024 * 1024) return `${(n / (1024 * 1024 * 1024)).toFixed(2)} GB`;
                    if (n >= 1024 * 1024) return `${(n / (1024 * 1024)).toFixed(1)} MB`;
                    if (n >= 1024) return `${(n / 1024).toFixed(1)} KB`;
                    return `${n} B`;
                },
                normalizeUploadMimeType(file) {
                    const name = String(file?.name || '').toLowerCase();
                    const detected = String(file?.type || '').toLowerCase();

                    if (name.endsWith('.xlsx')) {
                        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                    }
                    if (name.endsWith('.xls')) {
                        return 'application/vnd.ms-excel';
                    }
                    if (name.endsWith('.xlsm')) {
                        return 'application/vnd.ms-excel.sheet.macroenabled.12';
                    }
                    if (name.endsWith('.csv')) {
                        return 'text/csv';
                    }

                    return detected || 'application/octet-stream';
                },
                enqueueCurrentRequirement(event) {
                    const req = this.currentRequirement();
                    if (!req) return;
                    if (req.is_composite_parent) {
                        this.setUploadMessage('error', req.composite_message || 'Este requisito se cumple automáticamente con sus documentos requeridos.');
                        return;
                    }
                    const input = event?.target;
                    if (!input || !input.files || input.files.length === 0) return;
                    const file = input.files[0];
                    this.uploadQueue = this.uploadQueue.filter(item => ['initializing', 'uploading'].includes(item.status));
                    this.uploadQueueSeq += 1;
                    this.uploadQueue.push({
                        local_id: `${Date.now()}-${this.uploadQueueSeq}`,
                        requirement_id: req.id,
                        requirement_title: req.title,
                        init_url: req.large_upload_init_url,
                        file,
                        name: file.name,
                        size: file.size,
                        mime_type: this.normalizeUploadMimeType(file),
                        index: 1,
                        total: 1,
                        status: 'pending',
                        completed_by_verify: false,
                        progress: 0,
                        uploaded_bytes: 0,
                        session: null,
                        error: '',
                        abortController: null,
                    });
                    this.setUploadMessage('success', 'Archivo listo. Presiona Cargar para iniciar.');
                },
                hasPendingUpload() {
                    return this.uploadQueue.some(item => item.status === 'pending');
                },
                processUploadQueue() {
                    this.uploadModalOpen = true;
                    if (this.uploadActiveCount > 0) {
                        this.setUploadMessage('error', 'Ya hay una carga en proceso. Espera a que termine o cancélala.');
                        return;
                    }

                    const next = this.uploadQueue.find(item => item.status === 'pending');
                    if (!next) {
                        this.setUploadMessage('error', 'Selecciona un archivo antes de cargar.');
                        return;
                    }

                    this.runUploadQueueItem(next);
                },
                closeUploadModal() {
                    this.uploadModalOpen = false;
                },
                uploadModalTitle() {
                    if (this.uploadActiveCount > 0) return 'Cargando archivo a Drive';
                    if (this.uploadQueue.some(item => item.status === 'completed')) return 'Carga finalizada';
                    if (this.uploadQueue.some(item => item.status === 'failed')) return 'Carga con novedad';
                    return 'Archivo listo para cargar';
                },
                async runUploadQueueItem(item) {
                    this.uploadActiveCount += 1;
                    item.abortController = new AbortController();
                    try {
                        item.status = 'initializing';
                        let chunkSize = this.uploadDefaultChunkSize;
                        if (!item.session?.upload_url) {
                            const initResponse = await fetch(item.init_url, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': this.csrfToken,
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                credentials: 'same-origin',
                                body: JSON.stringify({
                                    name: item.name,
                                    size: item.size,
                                    mime_type: item.mime_type,
                                    index: item.index,
                                    total: item.total,
                                }),
                            });
                            const initData = await initResponse.json().catch(() => ({}));
                            if (!initResponse.ok || !initData.ok) throw new Error(initData.message || 'No se pudo iniciar la carga.');
                            item.session = initData.session;
                            chunkSize = Number(initData.chunk_size || this.uploadDefaultChunkSize);
                        }
                        item.status = 'uploading';
                        const driveFile = await this.uploadFileDirectToDrive(item, Number(chunkSize || this.uploadDefaultChunkSize));
                        item.drive_file_id = driveFile.id || null;
                        if (!item.completed_by_verify) {
                            await this.completeUploadQueueItem(item, driveFile);
                        }
                    } catch (error) {
                        if (item.status !== 'cancelled') {
                            const recovered = await this.verifyUploadQueueItem(item, true);
                            if (!recovered) {
                                item.status = 'failed';
                                item.error = error.message || 'La carga falló.';
                                await this.reportUploadFailure(item, item.error);
                            }
                        }
                    } finally {
                        this.uploadActiveCount = Math.max(0, this.uploadActiveCount - 1);
                        this.processUploadQueue();
                    }
                },
                sleep(ms) {
                    return new Promise(resolve => setTimeout(resolve, ms));
                },
                driveRangeOffset(response) {
                    const range = response.headers.get('Range') || response.headers.get('range') || '';
                    const match = range.match(/bytes=0-(\d+)/i);
                    return match ? Number(match[1]) + 1 : null;
                },
                async queryDriveUploadOffset(item) {
                    if (!item?.session?.upload_url) return null;
                    try {
                        const response = await fetch(item.session.upload_url, {
                            method: 'PUT',
                            headers: {
                                'Content-Range': `bytes */${item.file.size}`,
                            },
                            signal: item.abortController?.signal,
                        });
                        if (response.status === 308) {
                            return this.driveRangeOffset(response) ?? 0;
                        }
                        if (response.ok) {
                            const payload = await response.json().catch(() => ({}));
                            if (payload && payload.id) {
                                item.drive_file_id = payload.id;
                                return item.file.size;
                            }
                        }
                    } catch (_) {}
                    return null;
                },
                async uploadChunkWithRetry(item, offset, end, chunk, maxRetries = 5) {
                    let lastError = null;
                    for (let attempt = 1; attempt <= maxRetries; attempt++) {
                        if (item.status === 'cancelled') throw new Error('Carga cancelada.');
                        try {
                            const response = await fetch(item.session.upload_url, {
                                method: 'PUT',
                                headers: {
                                    'Content-Type': item.mime_type || 'application/octet-stream',
                                    'Content-Range': `bytes ${offset}-${end}/${item.file.size}`,
                                },
                                body: chunk,
                                signal: item.abortController?.signal,
                            });

                            if (response.status === 308) {
                                const confirmedOffset = this.driveRangeOffset(response);
                                return { done: false, nextOffset: confirmedOffset ?? (end + 1), payload: null };
                            }
                            if (response.ok) {
                                const payload = await response.json().catch(() => ({}));
                                return { done: true, nextOffset: item.file.size, payload };
                            }

                            const txt = await response.text().catch(() => '');
                            lastError = new Error(txt || `Drive rechazó el bloque (${response.status}).`);
                        } catch (error) {
                            if (error.name === 'AbortError' && item.status === 'cancelled') {
                                throw error;
                            }
                            lastError = error;
                        }

                        const confirmedOffset = await this.queryDriveUploadOffset(item);
                        if (confirmedOffset !== null && confirmedOffset > offset) {
                            return { done: confirmedOffset >= item.file.size, nextOffset: confirmedOffset, payload: item.drive_file_id ? { id: item.drive_file_id } : null };
                        }

                        const isFinalChunk = end + 1 >= item.file.size;
                        if (isFinalChunk && item.session?.verify_url) {
                            item.error = 'Verificando si Drive recibió el archivo...';
                            const recovered = await this.verifyUploadQueueItem(item, true);
                            if (recovered) {
                                return { done: true, nextOffset: item.file.size, payload: item.drive_file_id ? { id: item.drive_file_id } : null };
                            }
                        }

                        item.error = `Reintentando bloque (${attempt}/${maxRetries})...`;
                        await this.sleep(Math.min(12000, 1000 * attempt * attempt));
                    }

                    throw lastError || new Error('No se pudo cargar el bloque tras varios intentos.');
                },
                async uploadFileDirectToDrive(item, chunkSize) {
                    let offset = await this.queryDriveUploadOffset(item);
                    if (offset === null) offset = Number(item.uploaded_bytes || 0);
                    let finalPayload = item.drive_file_id ? { id: item.drive_file_id } : null;
                    while (offset < item.file.size) {
                        if (item.status === 'cancelled') throw new Error('Carga cancelada.');
                        const end = Math.min(offset + chunkSize, item.file.size) - 1;
                        const chunk = item.file.slice(offset, end + 1);
                        const result = await this.uploadChunkWithRetry(item, offset, end, chunk);

                        offset = Math.max(offset, Number(result.nextOffset || 0));
                        if (result.done) {
                            finalPayload = result.payload || finalPayload;
                            offset = item.file.size;
                        }

                        item.error = '';
                        item.uploaded_bytes = offset;
                        item.progress = item.file.size > 0 ? Math.min(100, Math.round((offset / item.file.size) * 100)) : 0;
                        await this.reportUploadProgress(item);
                    }
                    if (!finalPayload || !finalPayload.id) {
                        throw new Error('Drive no devolvió el ID del archivo cargado.');
                    }
                    return finalPayload;
                },
                async reportUploadProgress(item) {
                    if (!item.session?.progress_url) return;
                    try {
                        await fetch(item.session.progress_url, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ uploaded_bytes: item.uploaded_bytes || 0 }),
                        });
                    } catch (_) {}
                },
                async completeUploadQueueItem(item, driveFile) {
                    const response = await fetch(item.session.complete_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ drive_file_id: driveFile.id }),
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || !data.ok) throw new Error(data.message || 'No se pudo registrar la evidencia.');
                    item.status = 'completed';
                    item.progress = 100;
                    item.uploaded_bytes = item.size;
                    this.applyRequirementUpdate(data.requirement);
                    this.setUploadMessage('success', `${item.name} quedó cargado en Drive.`);
                },
                async verifyUploadQueueItem(item, quiet = false) {
                    if (!item?.session?.verify_url) return false;
                    const previousStatus = item.status;
                    item.status = 'initializing';
                    item.error = quiet ? 'Verificando en Drive...' : '';
                    const attempts = quiet ? 3 : 1;

                    for (let attempt = 1; attempt <= attempts; attempt++) {
                        try {
                            const response = await fetch(item.session.verify_url, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': this.csrfToken,
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                credentials: 'same-origin',
                                body: JSON.stringify({ drive_file_id: item.drive_file_id || null }),
                            });
                            const data = await response.json().catch(() => ({}));
                            if (!response.ok || !data.ok) throw new Error(data.message || 'No se pudo verificar la carga.');

                            item.completed_by_verify = true;
                            item.drive_file_id = data.session?.drive_file_id || item.drive_file_id || null;
                            item.status = 'completed';
                            item.progress = 100;
                            item.uploaded_bytes = item.size;
                            item.error = '';
                            this.applyRequirementUpdate(data.requirement);
                            this.setUploadMessage('success', data.message || 'Carga verificada y vinculada.');
                            return true;
                        } catch (error) {
                            if (attempt < attempts) {
                                await this.sleep(1500 * attempt);
                                continue;
                            }
                            if (quiet) {
                                item.status = previousStatus;
                                item.error = '';
                                return false;
                            }
                            item.status = 'failed';
                            item.error = error.message || 'No se pudo verificar la carga.';
                            return false;
                        }
                    }

                    return false;
                },
                async reportUploadFailure(item, message) {
                    if (!item.session?.fail_url) return;
                    try {
                        await fetch(item.session.fail_url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ message, drive_file_id: item.drive_file_id || null }),
                        });
                    } catch (_) {}
                },
                async cancelUploadQueueItem(item) {
                    if (!item || ['completed', 'failed', 'cancelled'].includes(item.status)) return;
                    item.status = 'cancelled';
                    item.error = 'Cancelado por el usuario.';
                    if (item.abortController) item.abortController.abort();
                    if (item.session?.cancel_url) {
                        try {
                            await fetch(item.session.cancel_url, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': this.csrfToken,
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                credentials: 'same-origin',
                            });
                        } catch (_) {}
                    }
                },
                retryUploadQueueItem(item) {
                    if (!item || item.status !== 'failed') return;
                    item.status = 'pending';
                    item.completed_by_verify = false;
                    item.progress = 0;
                    item.uploaded_bytes = 0;
                    item.error = '';
                    this.processUploadQueue();
                },
                async verifyRecentUploadSession(url) {
                    if (!url) return;
                    this.setUploadMessage('', '');
                    try {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ drive_file_id: null }),
                        });
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok || !data.ok) throw new Error(data.message || 'No se pudo verificar la carga.');
                        this.setUploadMessage('success', data.message || 'Carga verificada y vinculada.');
                        setTimeout(() => window.location.reload(), 900);
                    } catch (error) {
                        this.setUploadMessage('error', error.message || 'No se pudo verificar la carga.');
                    }
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
            };
        }
    </script>

    <div class="py-1">
        <div
            class="w-full space-y-3"
            x-data="projectManagePanelData()"
            x-init="init()"
        >
            @if (session('status'))
                <div class="rounded-md bg-emerald-50 p-4 text-emerald-700 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-md bg-rose-50 p-4 text-rose-700 text-sm">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-md bg-rose-50 p-4 text-rose-700 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            @if (($recentDriveUploadSessions ?? collect())->isNotEmpty())
                <div class="rounded-xl border border-gray-200 bg-white p-3 text-xs text-gray-700">
                    <div class="mb-2 font-semibold text-gray-800">Cargas recientes</div>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($recentDriveUploadSessions as $session)
                            @php
                                $status = (string) $session->status;
                                $tone = match ($status) {
                                    'completed' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                                    'failed' => 'border-rose-200 bg-rose-50 text-rose-800',
                                    'cancelled' => 'border-gray-200 bg-gray-50 text-gray-600',
                                    default => 'border-blue-200 bg-blue-50 text-blue-800',
                                };
                                $percent = $session->size_bytes > 0 ? min(100, (int) round(($session->uploaded_bytes / $session->size_bytes) * 100)) : 0;
                            @endphp
                            <div class="rounded-md border p-2 {{ $tone }}">
                                <div class="truncate font-semibold">{{ $session->target_name }}</div>
                                <div class="mt-1 uppercase tracking-wide">{{ $status }} · {{ $percent }}%</div>
                                @if (in_array($status, ['failed', 'uploading'], true))
                                    <button
                                        type="button"
                                        @click="verifyRecentUploadSession('{{ route('drive-upload-sessions.verify', $session) }}')"
                                        class="mt-2 rounded border border-emerald-300 bg-white px-2 py-1 text-[11px] font-semibold text-emerald-700 hover:bg-emerald-50">
                                        Verificar
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div x-show="uploadMessage" x-cloak class="rounded-md p-3 text-sm"
                :class="uploadMessageType === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'"
                x-text="uploadMessage">
            </div>

            @if (!$driveConnected && !$project->drive_folder_id)
                <div class="rounded-md bg-amber-50 p-4 text-amber-700 text-sm">
                    Este proyecto no tiene carpeta de Drive configurada. Agrega la ruta en Editar proyecto.
                </div>
            @elseif (!$project->drive_folder_id)
                <div class="rounded-md bg-amber-50 p-4 text-amber-700 text-sm">
                    Este proyecto no tiene carpeta de Drive configurada. Agrega la ruta en Editar proyecto.
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
                        <h3 class="text-lg font-semibold text-gray-800">Requisitos Aplicables</h3>
                        <p class="text-sm text-gray-500">Panel maestro-detalle para gestionar evidencias por grupo.</p>
                    </div>
                    <div class="flex items-center gap-2 sm:gap-3 flex-wrap justify-end">
                        @if (!empty($mgaUrl))
                            <a
                                href="{{ $mgaUrl }}"
                                target="_blank"
                                rel="noopener"
                                style="display:inline-flex;align-items:center;padding:2px 8px;border:1px solid #86efac;border-radius:6px;background:#ecfdf5;color:#15803d;font-size:12px;font-weight:600;text-decoration:none;">
                                MGA
                            </a>
                        @endif
                        <a
                            href="{{ route('filament.admin.resources.projects.bank', ['record' => $project]) }}"
                            class="h-8 px-3 inline-flex items-center rounded-md border border-emerald-300 bg-emerald-50 text-emerald-700 text-xs font-medium hover:bg-emerald-100 transition-colors">
                            Generar documentos del banco
                        </a>
                        <a
                            href="{{ route('filament.admin.resources.projects.documents', ['record' => $project]) }}"
                            class="h-8 px-3 inline-flex items-center rounded-md border border-indigo-300 bg-indigo-50 text-indigo-700 text-xs font-medium hover:bg-indigo-100 transition-colors">
                            Crear certificaciones
                        </a>
                        <form method="POST" action="{{ route('projects.manage.renumber', $project) }}" onsubmit="return confirm('¿Renumerar archivos cargados de este proyecto?');">
                            @csrf
                            <button
                                type="submit"
                                class="h-8 px-3 inline-flex items-center rounded-md border border-fuchsia-300 bg-fuchsia-50 text-fuchsia-700 text-xs font-medium hover:bg-fuchsia-100 transition-colors">
                                Renumerar archivos
                            </button>
                        </form>
                        <button
                            type="button"
                            @click="toggleOnlyPendingGlobal()"
                            class="px-2 py-1 rounded border text-xs transition"
                            :class="onlyPendingGlobal ? 'border-amber-300 bg-amber-50 text-amber-700' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'">
                            Solo pendientes (Global)
                        </button>
                        <div class="text-sm text-gray-600">
                            <span class="font-semibold text-gray-800">Avance general:</span>
                            {{ $overallPercent }}% ({{ $folderProgress ? array_sum(array_column($folderProgress, 'done')) : 0 }} de {{ $folderProgress ? array_sum(array_column($folderProgress, 'total')) : 0 }})
                        </div>
                    </div>
                </div>
                <div class="mb-4">
                    <div class="h-1.5 w-44 rounded-full bg-gray-200">
                        <div class="h-1.5 rounded-full bg-emerald-500" style="width: {{ $overallPercent }}%"></div>
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
                                <div class="text-sm font-semibold text-gray-800">Grupos de requisitos (01-05)</div>
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
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="text-sm font-semibold text-gray-800">Requisitos del subgrupo activo</div>
                                        <button
                                            type="button"
                                            x-show="isOtherCertificationsSubgroup()"
                                            x-cloak
                                            @click="openCustomCertificationModal()"
                                            class="h-8 rounded-md border border-amber-200 bg-amber-50 px-3 text-xs font-semibold text-amber-700 hover:bg-amber-100">
                                            Agregar certificación
                                        </button>
                                    </div>
                                </div>
                                <div class="pane-body">
                                    <div class="pane-inner" :class="toneClass()">
                                    <template x-if="currentSubgroup()">
                                        <div class="mb-2 flex items-center justify-between gap-2">
                                            <div class="text-xs text-gray-600" x-text="currentSubgroup().name"></div>
                                        </div>
                                    </template>
                                    <template x-if="visibleRequirementsInSelectedSubgroup().length === 0">
                                        <div class="text-sm text-gray-500">No hay requisitos en el subgrupo seleccionado.</div>
                                    </template>
                                    <div class="space-y-1.5">
                                        <template x-for="req in visibleRequirementsInSelectedSubgroup()" :key="`right-top-${req.id}`">
                                            <div
                                                @click="selectRequirement(req.folder_key, req.id)"
                                                class="w-full rounded-md border px-2 py-2 text-left transition cursor-pointer"
                                                :class="selectedRequirementId === req.id ? activeClassFor(selectedGroupCode || &quot;01&quot;) : 'border-gray-200 bg-white hover:bg-gray-50'">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <div class="text-sm font-semibold leading-snug text-slate-600 truncate" x-text="req.title"></div>
                                                        <template x-if="firstEvidenceLink(req)">
                                                            <div class="flex items-center gap-2 shrink-0">
                                                                <a :href="firstEvidenceLink(req)" target="_blank" rel="noopener" class="text-[8px] font-medium leading-none text-indigo-600 hover:text-indigo-700">Ver</a>
                                                                <a x-show="firstEvidenceDownloadLink(req)" :href="firstEvidenceDownloadLink(req)" class="text-[8px] font-medium leading-none text-emerald-700 hover:text-emerald-800">Descargar</a>
                                                            </div>
                                                        </template>
                                                        <template x-if="!firstEvidenceLink(req)">
                                                            <span class="text-[11px]" :class="req.has_evidence ? 'text-emerald-600 font-semibold' : 'text-gray-500'" x-text="req.is_composite_parent ? (req.has_evidence ? 'OK' : 'Parcial') : 'Pendiente'"></span>
                                                        </template>
                                                    </div>
                                                    <div class="mt-1">
                                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium" :class="fulfillmentClass(req)" :style="fulfillmentStyle(req)" x-text="fulfillmentLabel(req)"></span>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="pane pane-split p-3">
                                <div class="pane-head mb-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2">
                                    <div class="text-sm font-semibold text-gray-800">Detalle del requisito activo</div>
                                </div>
                                <div class="pane-body">
                                    <div class="pane-inner space-y-4" :class="toneClass()">
                                        <template x-if="!currentRequirement()">
                                            <div class="text-sm text-gray-500">Selecciona un requisito para ver el detalle.</div>
                                        </template>

                                        <template x-if="currentRequirement()">
                                            <div class="space-y-3">
                                                <div class="text-sm font-semibold text-gray-800" x-text="currentDisplayName(currentRequirement())"></div>
                                                <template x-if="currentRequirement().is_composite_parent">
                                                    <div class="space-y-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                                                        <div class="font-semibold">Carga directa deshabilitada</div>
                                                        <div x-text="currentRequirement().composite_message"></div>
                                                        <div>
                                                            Avance de documentos requeridos:
                                                            <span class="font-semibold" x-text="`${currentRequirement().composite_done || 0} de ${currentRequirement().composite_total || 0}`"></span>
                                                        </div>
                                                    </div>
                                                </template>
                                                <template x-if="!currentRequirement().is_composite_parent">
                                                    <div class="space-y-3 rounded-lg border-2 border-emerald-200 bg-emerald-50/70 p-3">
                                                        <div class="space-y-2">
                                                            <label class="text-xs font-semibold text-emerald-800">Archivo de evidencia</label>
                                                            <div class="text-[11px] text-emerald-800/80">
                                                                Cuenta como válido: <span class="font-semibold" x-text="currentRequirement().evidence_format_label || 'Sin regla'"></span>
                                                            </div>
                                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                                                <input type="file" x-ref="requirementUploadInput" :accept="currentRequirement().upload_accept || null" @change="enqueueCurrentRequirement($event)" class="block min-w-0 flex-1 rounded-md border border-emerald-200 bg-white px-2 py-2 text-xs text-gray-700">
                                                                <button
                                                                    type="button"
                                                                    @click="processUploadQueue()"
                                                                    :disabled="uploadActiveCount > 0 || !hasPendingUpload()"
                                                                    class="rounded-md border border-emerald-300 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-800 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-100 disabled:text-gray-400">
                                                                    Cargar
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    x-show="uploadQueue.length > 0"
                                                                    @click="uploadModalOpen = true"
                                                                    class="rounded-md border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50">
                                                                    Ver estado
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>

                                                <div>
                                                    <span
                                                        class="inline-flex items-center h-7 rounded-full px-2.5 text-xs font-medium"
                                                        :class="currentRequirement().has_evidence ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                                                        x-text="evidenceCountLabel(currentRequirement())"></span>
                                                    <span class="inline-flex items-center h-7 rounded-full px-2.5 text-xs font-medium ml-2" :class="fulfillmentClass(currentRequirement())" :style="fulfillmentStyle(currentRequirement())" x-text="fulfillmentLabel(currentRequirement())"></span>
                                                </div>


                                                <div class="space-y-2">
                                                    <template x-if="!currentEvidence(currentRequirement())">
                                                        <div class="text-xs text-gray-500">No hay evidencias visibles para este requisito.</div>
                                                    </template>
                                                    <template x-if="currentEvidence(currentRequirement())">
                                                        <div class="rounded-md border border-gray-200 p-2 text-xs">
                                                            <div class="flex items-start justify-between gap-2">
                                                                <div class="font-medium text-gray-700 truncate" x-text="currentEvidence(currentRequirement()).name"></div>
                                                                <div class="flex items-center gap-2 shrink-0">
                                                                    <a
                                                                        x-show="currentEvidence(currentRequirement()).preview_url"
                                                                        :href="currentEvidence(currentRequirement()).preview_url"
                                                                        target="_blank"
                                                                        rel="noopener"
                                                                        class="text-[8px] font-medium leading-none text-indigo-600 hover:text-indigo-700">
                                                                        Ver
                                                                    </a>
                                                                    <a
                                                                        x-show="currentEvidence(currentRequirement()).download_url"
                                                                        :href="currentEvidence(currentRequirement()).download_url"
                                                                        class="text-[8px] font-medium leading-none text-emerald-700 hover:text-emerald-800">
                                                                        Descargar
                                                                    </a>
                                                                    <button
                                                                        type="button"
                                                                        x-show="currentEvidence(currentRequirement()).source === 'manual_link'"
                                                                        @click="unlinkEvidence(currentEvidence(currentRequirement()))"
                                                                        class="text-rose-600 hover:text-rose-700">
                                                                        Quitar
                                                                    </button>
                                                                    <button
                                                                        type="button"
                                                                        x-show="currentEvidence(currentRequirement()).file_id"
                                                                        @click="openDeleteConfirm(currentEvidence(currentRequirement()))"
                                                                        class="text-rose-700 hover:text-rose-800">
                                                                        Borrar
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <div class="mt-1 text-[11px] text-emerald-600">Evidencia vigente</div>
                                                        </div>
                                                    </template>
                                                    <button type="button" @click="openHistoryModal()" class="h-8 px-3 rounded-md border border-gray-300 bg-white text-gray-700 text-xs font-medium hover:bg-gray-50">
                                                        Ver historial
                                                    </button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                @endif
            </div>


            <div x-show="customCertificationOpen" x-cloak @click.self="closeCustomCertificationModal()" x-on:keydown.escape.window="closeCustomCertificationModal()" class="gp-modal-overlay">
                <div class="gp-modal-card">
                    <div class="gp-modal-head px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <div class="text-sm font-semibold text-gray-800">Agregar certificación libre</div>
                        <button type="button" @click="closeCustomCertificationModal()" class="text-xs text-gray-500 hover:text-gray-700">Cerrar</button>
                    </div>
                    <div class="gp-modal-body p-4 space-y-3">
                        <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                            Se creará un requisito propio de este proyecto en <strong>3.3 Otras Certificaciones</strong> y contará en el avance.
                        </div>
                        <label class="block">
                            <span class="text-xs font-semibold text-gray-700">Nombre de la certificación</span>
                            <input
                                type="text"
                                x-ref="customCertificationName"
                                x-model="customCertificationName"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm"
                                maxlength="180"
                                placeholder="Ej: Certificación de no riesgo">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold text-gray-700">Archivo inicial (opcional)</span>
                            <input
                                type="file"
                                x-ref="customCertificationFile"
                                class="mt-1 block w-full rounded-md border border-gray-200 bg-white px-2 py-2 text-xs text-gray-700">
                        </label>
                        <div x-show="customCertificationError" x-cloak class="rounded-md border border-rose-200 bg-rose-50 p-2 text-xs text-rose-700" x-text="customCertificationError"></div>
                    </div>
                    <div class="gp-modal-foot px-4 py-3 border-t border-gray-100 flex items-center justify-end gap-2">
                        <button type="button" @click="closeCustomCertificationModal()" :disabled="customCertificationBusy" class="h-8 px-3 rounded-md border border-gray-300 text-xs text-gray-700 hover:bg-gray-50 disabled:opacity-50">Cancelar</button>
                        <button
                            type="button"
                            @click="submitCustomCertification()"
                            :disabled="customCertificationBusy"
                            class="h-9 px-4 rounded-md border border-amber-200 bg-amber-50 text-xs font-bold text-amber-700 hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-50">
                            <span x-text="customCertificationBusy ? 'Creando...' : 'Crear certificación'"></span>
                        </button>
                    </div>
                </div>
            </div>

            <div x-show="drivePickerOpen" x-cloak @click.self="closeDrivePicker()" x-on:keydown.escape.window="closeDrivePicker()" class="gp-modal-overlay">
                <div class="gp-modal-card">
                    <div class="gp-modal-head px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <div class="text-sm font-semibold text-gray-800">Vincular archivo de Drive</div>
                        <button type="button" @click="closeDrivePicker()" class="text-xs text-gray-500 hover:text-gray-700">Cerrar</button>
                    </div>
                    <div class="gp-modal-body p-4 space-y-3">
                        <div class="flex items-center gap-2">
                            <input x-model="drivePickerQuery" type="text" placeholder="Buscar por nombre..." class="flex-1 rounded-md border-gray-300 text-xs">
                            <input x-model="drivePickerExt" type="text" placeholder="ext (pdf/xlsx/mpp)" class="w-40 rounded-md border-gray-300 text-xs">
                            <button type="button" @click="loadDriveFiles(currentRequirement())" class="h-8 px-3 rounded-md border border-gray-300 text-xs text-gray-700 hover:bg-gray-50">Buscar</button>
                        </div>
                        <div class="text-xs text-gray-500" x-text="drivePickerMessage"></div>
                        <div class="gp-modal-list border border-gray-100 rounded-md">
                            <template x-if="drivePickerLoading">
                                <div class="p-3 text-xs text-gray-500">Cargando archivos...</div>
                            </template>
                            <template x-if="!drivePickerLoading && drivePickerFiles.length === 0">
                                <div class="p-3 text-xs text-gray-500">No hay archivos para mostrar.</div>
                            </template>
                            <template x-for="file in drivePickerFiles" :key="`pick-${file.id}`">
                                <label class="flex items-start gap-3 px-3 py-2 border-b border-gray-50 text-xs">
                                    <input type="checkbox" :value="file.id" x-model="drivePickerSelected" class="mt-0.5 rounded border-gray-300">
                                    <div class="min-w-0">
                                        <div class="font-medium text-gray-700 truncate" x-text="file.name"></div>
                                        <div class="text-[11px] text-gray-500" x-text="file.ext ? `.${file.ext}` : 'sin extensión'"></div>
                                    </div>
                                </label>
                            </template>
                        </div>
                    </div>
                    <div class="gp-modal-foot px-4 py-3 border-t border-gray-100 flex items-center justify-end gap-2">
                        <button type="button" @click="closeDrivePicker()" class="h-8 px-3 rounded-md border border-gray-300 text-xs text-gray-700 hover:bg-gray-50">Cancelar</button>
                        <button type="button" @click="submitManualLink()" :disabled="drivePickerLoading || drivePickerSelected.length === 0" class="h-8 px-3 rounded-md text-xs font-medium disabled:opacity-50" style="background:#7c3aed;color:#fff;border:1px solid #6d28d9;">Vincular seleccionados</button>
                    </div>
                </div>
            </div>

            <div x-show="bulkOpen" x-cloak @click.self="closeBulkLinker()" x-on:keydown.escape.window="closeBulkLinker()" class="gp-modal-overlay">
                <div class="gp-modal-card-lg">
                    <div class="gp-modal-head px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <div class="text-sm font-semibold text-gray-800">Vinculación masiva por subgrupo</div>
                        <button type="button" @click="closeBulkLinker()" class="text-xs text-gray-500 hover:text-gray-700">Cerrar</button>
                    </div>
                    <div class="gp-modal-body p-4 space-y-3">
                        <template x-if="bulkLoading">
                            <div class="text-xs text-gray-500">Cargando candidatos...</div>
                        </template>
                        <template x-if="!bulkLoading">
                            <div class="gp-modal-list border border-gray-100 rounded-md">
                                <table class="min-w-full text-xs">
                                    <thead class="bg-gray-50 text-gray-600">
                                        <tr>
                                            <th class="px-3 py-2 text-left">Requisito</th>
                                            <th class="px-3 py-2 text-left">Archivo vinculado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="row in bulkRows" :key="`bulk-${row.requirement_id}`">
                                            <tr class="border-t border-gray-100">
                                                <td class="px-3 py-2 text-gray-700" x-text="row.title"></td>
                                                <td class="px-3 py-2">
                                                    <select x-model="row.selected_file_id" class="w-full rounded-md border-gray-300 text-xs">
                                                        <option value="">-- Sin selección --</option>
                                                        <template x-for="file in bulkFiles" :key="`opt-${row.requirement_id}-${file.id}`">
                                                            <option :value="file.id" x-text="file.name"></option>
                                                        </template>
                                                    </select>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                        <template x-if="bulkReport">
                            <div class="text-xs rounded-md border p-2"
                                :class="bulkReport.error ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'">
                                <span x-show="bulkReport.error" x-text="bulkReport.error"></span>
                                <span x-show="!bulkReport.error" x-text="`Vinculados: ${bulkReport.linked_count || 0} | Omitidos: ${bulkReport.omitted_count || 0} | Conflictos: ${bulkReport.conflicts_count || 0}`"></span>
                            </div>
                        </template>
                    </div>
                    <div class="gp-modal-foot px-4 py-3 border-t border-gray-100 flex items-center justify-end gap-2">
                        <button type="button" @click="closeBulkLinker()" class="h-8 px-3 rounded-md border border-gray-300 text-xs text-gray-700 hover:bg-gray-50">Cancelar</button>
                        <button type="button" @click="submitBulkLink()" :disabled="bulkLoading" class="h-8 px-3 rounded-md bg-sky-600 text-white text-xs font-medium disabled:opacity-50">Aplicar vínculo masivo</button>
                    </div>
                </div>
            </div>

    
        <div x-show="uploadModalOpen" x-cloak @click.self="closeUploadModal()" x-on:keydown.escape.window="closeUploadModal()" class="gp-modal-overlay">
            <div class="gp-modal-card upload-modal-card">
                <div class="upload-modal-head">
                    <div>
                        <div class="upload-modal-title-row">
                            <span class="upload-modal-kicker">Carga a Drive</span>
                            <div class="upload-modal-title" x-text="uploadModalTitle()"></div>
                        </div>
                        <p class="upload-modal-help">Mantén esta pestaña abierta hasta finalizar. Puedes seguir revisando esta pantalla; para ir a otro módulo, abre otra pestaña.</p>
                    </div>
                    <button type="button" @click="closeUploadModal()" class="upload-modal-close">×</button>
                </div>

                <div class="gp-modal-body upload-modal-body">
                    <template x-if="uploadQueue.length === 0">
                        <div class="upload-card" style="text-align:center;color:#6b7280;font-size:.82rem;">Aún no hay archivo seleccionado.</div>
                    </template>

                    <template x-for="item in uploadQueue" :key="item.local_id">
                        <div class="upload-card">
                            <div class="upload-card-top">
                                <div style="min-width:0;flex:1;">
                                    <div class="upload-status-line">
                                        <span class="upload-status-badge" :style="queueStatusStyle(item)" x-text="queueStatusLabel(item)"></span>
                                        <span class="upload-size-text" x-text="`${formatBytes(item.uploaded_bytes)} / ${formatBytes(item.size)}`"></span>
                                    </div>
                                    <div class="upload-file-name" x-text="item.name"></div>
                                    <div class="upload-target">
                                        <strong style="color:#374151;">Requisito destino:</strong>
                                        <span x-text="item.requirement_title || '-'"></span>
                                    </div>
                                    <div x-show="item.error" class="upload-error" x-text="item.error"></div>
                                </div>
                                <div class="upload-actions">
                                    <button type="button" x-show="item.status === 'failed' && item.session && item.session.verify_url" @click="verifyUploadQueueItem(item)" class="upload-btn upload-btn-success">Verificar</button>
                                    <button type="button" x-show="item.status === 'failed'" @click="retryUploadQueueItem(item)" class="upload-btn">Reintentar</button>
                                    <button type="button" x-show="['pending','initializing','uploading'].includes(item.status)" @click="cancelUploadQueueItem(item)" class="upload-btn upload-btn-danger">Cancelar</button>
                                </div>
                            </div>
                            <div class="upload-progress-meta">
                                <span>Progreso</span>
                                <span x-text="`${uploadProgressPercent(item)}%`"></span>
                            </div>
                            <div class="upload-progress-track">
                                <div class="upload-progress-bar" :style="uploadProgressStyle(item)"></div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="gp-modal-foot px-4 py-3 border-t border-gray-100 flex items-center justify-between gap-2">
                    <div class="upload-modal-foot-note">La notificación final llegará a la campanita del panel.</div>
                    <div class="flex items-center gap-2">
                        <button type="button" x-show="hasPendingUpload()" @click="processUploadQueue()" :disabled="uploadActiveCount > 0" class="upload-btn upload-btn-success">Cargar</button>
                        <button type="button" @click="closeUploadModal()" class="upload-btn">Ocultar</button>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="deleteConfirmOpen" x-cloak @click.self="closeDeleteConfirm()" x-on:keydown.escape.window="closeDeleteConfirm()" class="gp-modal-overlay">
                <div class="gp-modal-card">
                    <div class="gp-modal-head px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <div class="text-sm font-semibold text-gray-800">Borrar archivo de Drive</div>
                        <button type="button" @click="closeDeleteConfirm()" class="text-xs text-gray-500 hover:text-gray-700">Cerrar</button>
                    </div>
                    <div class="gp-modal-body p-4 space-y-3">
                        <div class="rounded-md border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700">
                            Esta acción borrará el archivo en Google Drive y no se puede deshacer.
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-gray-700">Archivo</div>
                            <div class="mt-1 break-all rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-700" x-text="deleteConfirmEvidence ? deleteConfirmEvidence.name : ''"></div>
                        </div>
                        <label class="block">
                            <span class="text-xs font-semibold text-gray-700">Escribe BORRAR para confirmar</span>
                            <input
                                type="text"
                                x-model="deleteConfirmText"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm"
                                autocomplete="off"
                                placeholder="BORRAR">
                        </label>
                        <div x-show="deleteConfirmError" x-cloak class="rounded-md border border-rose-200 bg-rose-50 p-2 text-xs text-rose-700" x-text="deleteConfirmError"></div>
                    </div>
                    <div class="gp-modal-foot px-4 py-3 border-t border-gray-100 flex items-center justify-end gap-2">
                        <button type="button" @click="closeDeleteConfirm()" :disabled="deleteConfirmBusy" class="h-8 px-3 rounded-md border border-gray-300 text-xs text-gray-700 hover:bg-gray-50 disabled:opacity-50">Cancelar</button>
                        <button
                            type="button"
                            @click="confirmDeleteEvidenceFromDrive()"
                            :disabled="deleteConfirmText !== 'BORRAR' || deleteConfirmBusy"
                            class="h-9 px-4 rounded-md text-xs font-bold shadow-sm disabled:cursor-not-allowed disabled:opacity-50"
                            style="background:#b91c1c;color:#ffffff;border:1px solid #991b1b;">
                            <span x-text="deleteConfirmBusy ? 'Borrando...' : 'Borrar definitivamente'"></span>
                        </button>
                    </div>
                </div>
            </div>

            <div x-show="historyOpen" x-cloak @click.self="closeHistoryModal()" x-on:keydown.escape.window="closeHistoryModal()" class="gp-modal-overlay">
                <div class="gp-modal-card">
                    <div class="gp-modal-head px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <div class="text-sm font-semibold text-gray-800">Historial de archivos asociados</div>
                        <button type="button" @click="closeHistoryModal()" class="text-xs text-gray-500 hover:text-gray-700">Cerrar</button>
                    </div>
                    <div class="gp-modal-body p-4 space-y-3">
                        <div class="text-xs text-gray-600" x-text="currentRequirement() ? currentRequirement().title : ''"></div>
                        <div class="gp-modal-list border border-gray-100 rounded-md">
                            <template x-if="!currentRequirement() || !(currentRequirement().history || []).length">
                                <div class="p-3 text-xs text-gray-500">Sin historial para este requisito.</div>
                            </template>
                            <template x-for="item in (currentRequirement() && currentRequirement().history ? currentRequirement().history : [])" :key="`hist-${item.id}`">
                                <div class="p-3 border-b border-gray-100 last:border-b-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="text-xs font-medium text-gray-700 break-all" x-text="item.name || 'Sin nombre'"></div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <a x-show="item.preview_url" :href="item.preview_url" target="_blank" rel="noopener" class="text-[8px] font-medium leading-none text-indigo-600 hover:text-indigo-700">Ver</a>
                                            <a x-show="item.download_url" :href="item.download_url" class="text-[8px] font-medium leading-none text-emerald-700 hover:text-emerald-800">Descargar</a>
                                        </div>
                                    </div>
                                    <div class="mt-1 text-[11px] text-gray-500">
                                        <span x-text="item.created_at || '-'"></span>
                                        <span class="mx-1">·</span>
                                        <span x-text="item.source || 'n/a'"></span>
                                        <span class="mx-1">·</span>
                                        <span :class="item.is_valid ? 'text-emerald-600' : 'text-amber-700'" x-text="item.is_valid ? 'vigente' : 'histórico/no disponible'"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="gp-modal-foot px-4 py-3 border-t border-gray-100 flex items-center justify-end gap-2">
                        <button type="button" @click="closeHistoryModal()" class="h-8 px-3 rounded-md border border-gray-300 text-xs text-gray-700 hover:bg-gray-50">Cerrar</button>
                    </div>
                </div>
            </div>

            <div class="rounded-md border border-violet-100 bg-violet-50/40 p-3 mt-3 space-y-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="text-xs font-semibold text-violet-700">Estado transferencia MGA</div>
                        @if ($transferRequest)
                            <div class="mt-1 text-xs text-gray-700">
                                Estado:
                                <span class="font-semibold">{{ strtoupper($transferRequest->status) }}</span>
                                · Enviado: {{ optional($transferRequest->requested_at)->format('Y-m-d H:i') }}
                                @if($transferRequest->decided_at)
                                    · Decidido: {{ optional($transferRequest->decided_at)->format('Y-m-d H:i') }}
                                @endif
                            </div>
                        @else
                            <div class="mt-1 text-xs text-gray-700">Sin solicitudes aún.</div>
                        @endif
                        <div class="mt-1 text-xs {{ $canTransferToMga ? 'text-emerald-700' : 'text-amber-700' }}">
                            {{ $canTransferToMga ? 'Transferencia a MGA habilitada.' : 'Transferencia a MGA no habilitada aún.' }}
                        </div>
                    </div>
                    @if ($canRequestTransfer)
                        <form method="POST" action="{{ route('projects.mga_transfer.send', $project) }}" class="flex items-center gap-2">
                            @csrf
                            <input type="text" name="request_note" placeholder="Comentario de envío (opcional)" class="h-8 rounded-md border border-violet-200 px-2 text-xs text-gray-700">
                            <button type="submit" class="h-8 px-3 rounded-md border border-violet-300 bg-white text-violet-700 text-xs font-semibold hover:bg-violet-100">Enviar para evaluación</button>
                        </form>
                    @endif
                </div>

                @if ($transferRequest && $transferRequest->decision_note)
                    <div class="rounded-md border border-gray-200 bg-white p-2">
                        <div class="text-[11px] font-semibold text-gray-700">Comentario de decisión</div>
                        <div class="text-xs text-gray-700 mt-1">{{ $transferRequest->decision_note }}</div>
                        <div class="text-[11px] text-gray-500 mt-1">Por: {{ $transferRequest->decidedBy?->name ?? 'N/A' }}</div>
                    </div>
                @endif

                @if ($transferRequest && $transferRequest->status === 'pending')
                    <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        Estado de transferencia: en revisión.
                    </div>
                @endif

                
            </div>

            <div class="rounded-md border border-indigo-100 bg-indigo-50/40 p-3 flex items-center justify-between mt-3">
                <div>
                    <div class="text-xs font-semibold text-indigo-700">Paquete PDF con adjuntos</div>
                </div>
                <a
                    href="{{ route('filament.admin.resources.projects.attachments', ['record' => $project]) }}"
                    class="px-3 py-1.5 rounded-md bg-white text-indigo-700 text-xs font-semibold border border-indigo-200 hover:bg-indigo-50">
                    Abrir modulo
                </a>
            </div>

            @if (!$driveConnected && auth()->user()?->isAdminUser())
                <div class="rounded-md bg-amber-50 p-4 text-amber-700 text-sm flex items-center justify-between">
                    <span>Conecta Google Drive para sincronizar evidencias automaticamente.</span>
                    <a href="{{ route('drive.auth', ['return' => route('filament.admin.resources.projects.manage', ['record' => $project])]) }}" class="text-amber-700 font-semibold hover:text-amber-800">
                        Conectar Drive
                    </a>
                </div>
            @endif

            @if (!$driveConnected && !auth()->user()?->isAdminUser())
                <div class="rounded-md bg-amber-50 p-4 text-amber-700 text-sm">
                    Google Drive no está conectado por administración. Solicita al administrador conectar Drive para habilitar carga y sincronización.
                </div>
            @endif

            @if ($driveConnected && $driveReady)
                <div class="rounded-lg border border-sky-200 bg-sky-50/70 px-3 py-2 text-sky-700 text-sm flex items-center justify-between gap-2">
                    <span class="font-medium">Drive conectado.</span>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('filament.admin.resources.projects.manage', ['record' => $project]) }}?sync=1" class="h-8 px-3 inline-flex items-center rounded-md border border-sky-300 bg-white text-sky-700 text-xs font-medium hover:bg-sky-100 transition-colors">
                            Sincronizar ahora
                        </a>
                        <a href="{{ route('filament.admin.resources.projects.manage', ['record' => $project]) }}?sync=1&debug=1" class="h-8 px-3 inline-flex items-center rounded-md border border-sky-300 bg-white text-sky-700 text-xs font-medium hover:bg-sky-100 transition-colors">
                            Sincronizar + reporte
                        </a>
                    </div>
                </div>
            @endif

        </div>
    </div>
