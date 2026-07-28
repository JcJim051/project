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
            'pending' => ['card' => 'border-slate-200 bg-slate-50 text-slate-700'],
            'completed' => ['card' => 'border-sky-200 bg-sky-50 text-sky-900'],
            'validated' => ['card' => 'border-emerald-200 bg-emerald-50 text-emerald-900'],
            'not_applicable' => ['card' => 'border-gray-200 bg-gray-50 text-gray-500'],
        ];
    @endphp

    <style>
        .project-workflow-nav {
            min-width: 0;
            padding: 5px;
            border: 1px solid #e2e8f0;
            border-radius: 11px;
            background: linear-gradient(135deg, #f8fafc 0%, #fff 62%, #ecfdf5 100%);
            box-shadow: 0 2px 8px rgb(15 23 42 / 5%);
        }
        .project-workflow-meta {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 3px 3px;
            color: #64748b;
            font-size: 10px;
            font-weight: 700;
            line-height: 1;
            letter-spacing: .1em;
            text-transform: uppercase;
        }
        .project-workflow-stages {
            display: flex;
            gap: 4px;
            overflow-x: auto;
        }
        .project-workflow-stage {
            display: flex;
            align-items: center;
            flex: 1 1 0;
            min-width: 94px;
            min-height: 38px;
            padding: 5px 8px;
            border-radius: 7px;
            text-align: left;
            transition: border-color 150ms ease, background 150ms ease, box-shadow 150ms ease;
        }
        .project-workflow-stage:hover {
            border-color: #94a3b8;
            box-shadow: 0 1px 4px rgb(15 23 42 / 8%);
        }
        .project-workflow-stage-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            gap: 4px;
        }
        .project-workflow-stage-name {
            display: block;
            min-width: 0;
            overflow: hidden;
            color: inherit;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.1;
        }
        .project-workflow-stage-percent {
            flex: 0 0 auto;
            align-self: flex-start;
            font-size: 10px;
            font-weight: 800;
            line-height: 1;
            opacity: .68;
        }
        .workflow-stage-active {
            background: #ecfdf5 !important;
            border-color: #6ee7b7 !important;
            color: #047857 !important;
            box-shadow: 0 3px 8px rgb(16 185 129 / 14%);
        }
        .workflow-stage-progress-complete {
            background: #ecfdf5 !important;
            border-color: #86efac !important;
            color: #047857 !important;
        }
        .workflow-stage-active *,
        .workflow-stage-progress-complete * {
            color: #047857 !important;
        }
        .workflow-tone-1 {
            border-color: #bfdbfe !important;
            background: #eff6ff !important;
        }
        .workflow-tone-2 {
            border-color: #bbf7d0 !important;
            background: #f0fdf4 !important;
        }
        .workflow-tone-3 {
            border-color: #fde68a !important;
            background: #fffbeb !important;
        }
        .workflow-tone-4 {
            border-color: #c7d2fe !important;
            background: #eef2ff !important;
        }
        .workflow-tone-5 {
            border-color: #fbcfe8 !important;
            background: #fdf2f8 !important;
        }
        .workflow-tone-active-1 {
            border-color: #60a5fa !important;
            background: #dbeafe !important;
            box-shadow: 0 2px 7px rgb(59 130 246 / 12%);
        }
        .workflow-tone-active-2 {
            border-color: #4ade80 !important;
            background: #dcfce7 !important;
            box-shadow: 0 2px 7px rgb(34 197 94 / 12%);
        }
        .workflow-tone-active-3 {
            border-color: #fbbf24 !important;
            background: #fef3c7 !important;
            box-shadow: 0 2px 7px rgb(245 158 11 / 12%);
        }
        .workflow-tone-active-4 {
            border-color: #818cf8 !important;
            background: #e0e7ff !important;
            box-shadow: 0 2px 7px rgb(99 102 241 / 12%);
        }
        .workflow-tone-active-5 {
            border-color: #f472b6 !important;
            background: #fce7f3 !important;
            box-shadow: 0 2px 7px rgb(236 72 153 / 12%);
        }
        .workflow-status-not-applicable {
            border-color: #cbd5e1 !important;
            background: #fff !important;
            color: #475569 !important;
        }
    </style>

    <nav class="project-workflow-nav" aria-label="Ruta del proyecto">
        <div class="project-workflow-meta">
            <span>Ruta del proyecto</span>
        </div>

        <div class="project-workflow-stages">
            @foreach($visibleWorkflowStages as $stageIndex => $stage)
                @php
                    $stageStatus = $workflowStatus[$stage['status']] ?? $workflowStatus['pending'];
                    $hasReachedTarget = (int) ($stage['percent'] ?? 0) >= 95;
                @endphp
                <button
                    type="button"
                    @click="selectWorkflowStage({{ $stageIndex }})"
                    title="Etapa {{ $stageIndex + 1 }} · {{ $stage['name'] }} · {{ $stage['percent'] }}%"
                    class="project-workflow-stage border {{ $stageStatus['card'] }} {{ $hasReachedTarget ? 'workflow-stage-progress-complete' : '' }}"
                    :class="{ 'workflow-stage-active': workflowStage === {{ $stageIndex }} }"
                >
                    <span class="project-workflow-stage-content">
                        <span class="project-workflow-stage-name">{{ $stage['name'] }}</span>
                        <span class="project-workflow-stage-percent">{{ $stage['percent'] }}%</span>
                    </span>
                </button>
            @endforeach
        </div>
    </nav>
@endif
