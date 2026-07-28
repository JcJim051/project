@php
    $showNotApplicableWorkflow = request()->boolean('show_not_applicable')
        && ($canOverrideWorkflowApplicability ?? false);
    $visibleWorkflowStages = collect($workflowStages ?? [])
        ->filter(fn (array $stage) => $showNotApplicableWorkflow
            || ($stage['applicable'] ?? true)
            || $stage['model']?->slug === 'estructuracion')
        ->values();
@endphp

@if($visibleWorkflowStages->isNotEmpty())
    @php
        $workflowStatus = [
            'pending' => ['label' => 'Pendiente', 'class' => 'border-slate-200 bg-slate-100 text-slate-600'],
            'completed' => ['label' => 'Completado', 'class' => 'border-sky-200 bg-sky-50 text-sky-700'],
            'validated' => ['label' => 'Validado', 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-700'],
            'not_applicable' => ['label' => 'No aplica', 'class' => 'workflow-status-not-applicable'],
        ];
    @endphp

    @foreach($visibleWorkflowStages as $stageIndex => $stage)
        @if($stageIndex === 0)
            <div x-show="workflowStage === 0" x-cloak class="bg-slate-50/50 p-2 sm:p-3">
                <div id="workflow-structure-host"></div>
            </div>
        @else
            <div x-show="workflowStage === {{ $stageIndex }}" x-cloak class="manage-shell p-3 shadow-sm sm:p-4">
                <div class="manage-main">
                    <aside class="pane pane-split manage-left p-3">
                        <div class="pane-head mb-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2">
                            <div class="text-sm font-semibold text-gray-800">Grupos de requisitos</div>
                        </div>

                        <div class="pane-body space-y-2 px-1 py-1">
                            @forelse($stage['steps'] as $stepIndex => $step)
                                @php
                                    $stepStatus = $workflowStatus[$step['status']] ?? $workflowStatus['pending'];
                                    $tone = ($stepIndex % 5) + 1;
                                @endphp
                                <button
                                    type="button"
                                    @click="selectWorkflowStep({{ $stepIndex }})"
                                    class="workflow-tone-{{ $tone }} w-full rounded-md border px-3 py-3 text-left transition"
                                    :class="{ 'workflow-tone-active-{{ $tone }}': workflowStep === {{ $stepIndex }} }"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="text-sm font-semibold text-gray-800">{{ $step['name'] }}</div>
                                        <span class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-semibold {{ $stepStatus['class'] }}">
                                            {{ $stepStatus['label'] }}
                                        </span>
                                    </div>
                                    @if($step['description'])
                                        <div class="mt-1 text-xs leading-snug text-gray-500">{{ $step['description'] }}</div>
                                    @endif
                                </button>
                            @empty
                                <div class="rounded-md border border-dashed border-gray-300 p-5 text-center text-sm text-gray-500">
                                    No hay grupos configurados para esta etapa.
                                </div>
                            @endforelse
                        </div>
                    </aside>

                    <section class="manage-right">
                        <div class="pane pane-split p-3">
                            <div class="pane-head mb-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2">
                                <div class="text-sm font-semibold text-gray-800">Requisitos del subgrupo activo</div>
                            </div>

                            <div class="pane-body">
                                @forelse($stage['steps'] as $stepIndex => $step)
                                    @php $tone = ($stepIndex % 5) + 1; @endphp
                                    <div x-show="workflowStep === {{ $stepIndex }}" x-cloak class="pane-inner workflow-tone-{{ $tone }}">
                                        <div class="mb-2 text-xs text-gray-600">{{ $step['name'] }}</div>

                                        @if(!$step['applicable'])
                                            <div class="rounded-md border border-dashed border-gray-300 bg-white p-4 text-sm text-gray-500">
                                                Este grupo no aplica para el proyecto.
                                            </div>
                                        @elseif($step['requirements']->isEmpty())
                                            <div class="rounded-md border border-dashed border-gray-300 bg-white p-4 text-sm text-gray-500">
                                                No hay requisitos configurados en este grupo.
                                            </div>
                                        @else
                                            <div class="space-y-1.5">
                                                @foreach($step['requirements'] as $requirementIndex => $requirementRow)
                                                    @php
                                                        $requirement = $requirementRow['model'];
                                                        $requirementName = $requirement?->nombre_documento ?: $requirement?->requisito;
                                                        $followUpLabels = [
                                                            'pending_structure' => ['label' => 'Pendiente en estructuración', 'class' => 'text-amber-700'],
                                                            'definitive_pending' => ['label' => 'Definitivo pendiente', 'class' => 'text-rose-700'],
                                                            'definitive_loaded' => ['label' => 'Definitivo cargado', 'class' => 'text-emerald-700'],
                                                        ];
                                                        $followUpStatus = $followUpLabels[$requirementRow['follow_up_status'] ?? ''] ?? null;
                                                    @endphp
                                                    <button
                                                        type="button"
                                                        @click="workflowRequirement = {{ $requirementIndex }}"
                                                        class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-left transition"
                                                        :class="{ 'workflow-tone-active-{{ $tone }}': workflowRequirement === {{ $requirementIndex }} }"
                                                    >
                                                        <div class="flex items-center justify-between gap-3">
                                                            <span class="truncate text-sm font-semibold text-gray-700">{{ $requirementName }}</span>
                                                            <span class="shrink-0 text-[11px] font-semibold {{ $followUpStatus['class'] ?? ($requirementRow['complete'] ? 'text-emerald-700' : 'text-gray-500') }}">
                                                                {{ $followUpStatus['label'] ?? ($requirementRow['complete'] ? 'Cargado' : 'Pendiente') }}
                                                            </span>
                                                        </div>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="pane-inner text-sm text-gray-500">No hay un grupo activo.</div>
                                @endforelse
                            </div>
                        </div>

                        <div class="pane pane-split p-3">
                            <div class="pane-head mb-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2">
                                <div class="text-sm font-semibold text-gray-800">Detalle del requisito activo</div>
                            </div>

                            <div class="pane-body">
                                @forelse($stage['steps'] as $stepIndex => $step)
                                    @php $tone = ($stepIndex % 5) + 1; @endphp
                                    <div x-show="workflowStep === {{ $stepIndex }}" x-cloak class="pane-inner workflow-tone-{{ $tone }} space-y-3">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <div class="text-sm font-semibold text-gray-800">{{ $step['name'] }}</div>
                                                @if($step['validated'] && $step['state_model'])
                                                    <div class="mt-1 text-xs text-emerald-700">
                                                        Validado por {{ $step['state_model']->validatedBy?->name ?: 'usuario autorizado' }}
                                                        · {{ optional($step['state_model']->validated_at)->format('d/m/Y H:i') }}
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="flex flex-wrap gap-2">
                                                @if($step['model']->slug === 'solicitud-del-banco-de-programas-y-proyectos')
                                                    <a href="{{ route('filament.admin.resources.projects.bank-request', ['record' => $project]) }}" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                                        Generar solicitud del banco
                                                    </a>
                                                @endif

                                                @if($canOverrideWorkflowApplicability ?? false)
                                                    <form method="POST" action="{{ route('projects.workflow.applicability', [$project, $step['model']]) }}">
                                                        @csrf
                                                        @method('PUT')
                                                        <input type="hidden" name="applicability" value="{{ $step['applicable'] ? 'not_applicable' : 'applicable' }}">
                                                        <button class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                                            {{ $step['applicable'] ? 'Marcar no aplica' : 'Marcar aplicable' }}
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>

                                        @if(($canValidateWorkflow ?? false) && $step['applicable'] && $step['complete'])
                                            @if($step['validated'])
                                                <form method="POST" action="{{ route('projects.workflow.validation.clear', [$project, $step['model']]) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                                        Retirar validación
                                                    </button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('projects.workflow.validate', [$project, $step['model']]) }}" class="flex flex-col gap-2 sm:flex-row">
                                                    @csrf
                                                    <input name="validation_note" placeholder="Observación opcional" class="min-w-0 flex-1 rounded-md border-slate-300 text-xs">
                                                    <button class="rounded-md bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700">Validar</button>
                                                </form>
                                            @endif
                                        @endif

                                        @if(!$step['applicable'])
                                            <div class="text-sm text-gray-500">Este grupo no aplica para el proyecto.</div>
                                        @elseif($step['requirements']->isEmpty())
                                            <div class="text-sm text-gray-500">No hay requisitos para mostrar.</div>
                                        @else
                                            @foreach($step['requirements'] as $requirementIndex => $requirementRow)
                                                    @php
                                                        $requirement = $requirementRow['model'];
                                                        $requirementName = $requirement?->nombre_documento ?: $requirement?->requisito;
                                                        $followUpStatus = $requirementRow['follow_up_status'] ?? null;
                                                    @endphp
                                                <div x-show="workflowRequirement === {{ $requirementIndex }}" x-cloak class="space-y-3">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span class="text-sm font-semibold text-gray-800">{{ $requirementName }}</span>
                                                        @if($requirementRow['required'])
                                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">Obligatorio</span>
                                                        @endif
                                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $requirementRow['complete'] ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                                            {{ $requirementRow['complete'] ? 'Cargado' : 'Pendiente' }}
                                                        </span>
                                                    </div>

                                                    @foreach($requirementRow['evidences'] as $evidence)
                                                        <div class="rounded-md border border-gray-200 bg-white p-2 text-xs">
                                                            <div class="flex items-center justify-between gap-3">
                                                                <span class="truncate font-medium text-gray-700">{{ $evidence->drive_file_name }}</span>
                                                                <div class="flex shrink-0 items-center gap-2">
                                                                    @if($evidence->canPreviewInPortal())
                                                                        <a class="font-semibold text-sky-700" href="{{ route('requirement-evidences.preview', $evidence) }}" target="_blank">Ver</a>
                                                                    @endif
                                                                    <a class="font-semibold text-slate-700" href="{{ route('requirement-evidences.download', $evidence) }}">Descargar</a>
                                                                </div>
                                                            </div>
                                                            @if($requirementRow['license_permit_follow_up'] ?? false)
                                                                <div class="mt-1 text-[10px] font-semibold {{ $evidence->license_permit_status === \App\Models\RequirementEvidence::LICENSE_PERMIT_ISSUED ? 'text-emerald-700' : ($evidence->license_permit_status === \App\Models\RequirementEvidence::LICENSE_PERMIT_APPLICATION ? 'text-amber-700' : 'text-gray-500') }}">
                                                                    {{ $evidence->licensePermitStatusLabel() }}
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endforeach

                                                    @if(($requirementRow['license_permit_follow_up'] ?? false) && $followUpStatus === 'pending_structure')
                                                        <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                                                            Primero clasifica una evidencia vigente en Estructuración como solicitud/radicado o documento expedido.
                                                        </div>
                                                    @elseif($requirement && (!($requirementRow['license_permit_follow_up'] ?? false) || $followUpStatus === 'definitive_pending'))
                                                        <form method="POST" enctype="multipart/form-data" action="{{ route('projects.manage.upload', [$project, $requirement]) }}" class="rounded-md border border-gray-200 bg-white p-3">
                                                            @csrf
                                                            @if($requirementRow['license_permit_follow_up'] ?? false)
                                                                <input type="hidden" name="license_permit_status" value="issued">
                                                            @endif
                                                            <label class="mb-2 block text-xs font-semibold text-gray-700">
                                                                {{ ($requirementRow['license_permit_follow_up'] ?? false) ? 'Licencia o permiso expedido' : 'Archivo de evidencia' }}
                                                            </label>
                                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                                                <input type="file" name="archivos[]" required class="min-w-0 flex-1 rounded-md border border-gray-300 bg-white text-xs">
                                                                <button class="rounded-md bg-slate-800 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700">Cargar a Drive</button>
                                                            </div>
                                                        </form>
                                                    @endif
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                @empty
                                    <div class="pane-inner text-sm text-gray-500">Selecciona un grupo para ver su detalle.</div>
                                @endforelse
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        @endif
    @endforeach
@endif
