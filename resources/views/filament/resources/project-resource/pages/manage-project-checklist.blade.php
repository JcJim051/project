<x-filament-panels::page>
    @php
        $requirements = $requirements ?? collect();
        $applied = $applied ?? [];
        $totalsByFolder = $totalsByFolder ?? collect();
        $studyAssignments = $studyAssignments ?? [];
        $specialistOptions = $specialistOptions ?? [];
        $specialistDetails = $specialistDetails ?? [];
    @endphp
    <div class="space-y-4">
        <div class="text-sm text-gray-500">Proyecto: {{ $project->nombre }}</div>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if ($requirements->isEmpty())
            <div class="rounded-lg border border-gray-200 bg-white p-6 text-sm text-gray-500">
                No hay requisitos cargados. Importa el Excel desde Requisitos.
            </div>
        @else
            <form method="POST" action="{{ route('projects.checklist.update', $project) }}" class="space-y-3">
                @csrf
                @method('PUT')
                <input type="hidden" name="panel_return" value="1">

                <div class="sticky top-2 z-20">
                    <div class="rounded-lg border border-indigo-100 bg-white/95 backdrop-blur px-3 py-2 flex justify-end shadow-sm">
                        <x-filament::button type="submit" size="sm">
                            Guardar checklist
                        </x-filament::button>
                    </div>
                </div>

                @php
                    $certFolders = [
                        '3.1 Certificaciones Generales',
                        '3.2 Certificaciones Generales Adicionales',
                        '3.3 Otras Certificaciones',
                        '3.4 Documentos Sectoriales',
                    ];
                    $certGroups = [];
                    foreach ($certFolders as $folderKey) {
                        if ($requirements->has($folderKey)) {
                            $certGroups[$folderKey] = $requirements->get($folderKey);
                        }
                    }
                    $regularGroups = $requirements->except(array_keys($certGroups));

                    $formulationGroups = [];
                    foreach ($regularGroups as $folderKey => $groups) {
                        $normalized = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii((string) $folderKey));
                        if ($folderKey === '01 Formulacion'
                            || preg_match('/^0?1($|\.|\s)/', (string) $folderKey)
                            || str_contains($normalized, 'formulacion')
                        ) {
                            $formulationGroups[$folderKey] = $groups;
                        }
                    }
                    $regularGroups = $regularGroups->except(array_keys($formulationGroups));

                    $budgetGroups = [];
                    foreach ($regularGroups as $folderKey => $groups) {
                        if (preg_match('/^2\./', (string) $folderKey) || $folderKey === '02 Presupuesto') {
                            $budgetGroups[$folderKey] = $groups;
                        }
                    }
                    $regularGroups = $regularGroups->except(array_keys($budgetGroups));

                    $studiesGroups = [];
                    foreach ($regularGroups as $folderKey => $groups) {
                        $normalized = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii((string) $folderKey));
                        $hasStudyCode = false;
                        foreach ($groups as $group) {
                            $candidates = [];
                            if (!empty($group['parent'])) {
                                $candidates[] = (string) ($group['parent']->codigo_interno ?? '');
                                $candidates[] = (string) ($group['parent']->texto ?? '');
                                $candidates[] = (string) ($group['parent']->requisito ?? '');
                            }
                            if (!empty($group['children'])) {
                                foreach ($group['children'] as $child) {
                                    $candidates[] = (string) ($child->codigo_interno ?? '');
                                    $candidates[] = (string) ($child->texto ?? '');
                                    $candidates[] = (string) ($child->requisito ?? '');
                                }
                            }
                            foreach ($candidates as $candidate) {
                                if (preg_match('/^\s*5(\.|$)/', trim($candidate))) {
                                    $hasStudyCode = true;
                                    break 2;
                                }
                            }
                        }
                        if (
                            preg_match('/^5\./', (string) $folderKey)
                            || preg_match('/^05\b/', (string) $folderKey)
                            || str_contains($normalized, 'estudios y disenos')
                            || $hasStudyCode
                        ) {
                            $studiesGroups[$folderKey] = $groups;
                        }
                    }
                    $regularGroups = $regularGroups->except(array_keys($studiesGroups));

                    $sections = [];
                    if (!empty($formulationGroups)) {
                        $sections[] = ['type' => 'formulation', 'name' => '01 Formulación', 'order' => 1];
                    }
                    if (!empty($budgetGroups)) {
                        $sections[] = ['type' => 'budget', 'name' => '02 Presupuesto', 'order' => 2];
                    }
                    if (!empty($certGroups)) {
                        $sections[] = ['type' => 'cert', 'name' => '03 Certificaciones', 'order' => 3];
                    }
                    if (!empty($studiesGroups)) {
                        $sections[] = ['type' => 'studies', 'name' => '05 Estudios y Diseños', 'order' => 5];
                    }
                    foreach ($regularGroups as $carpeta => $groups) {
                        $order = 999;
                        if (preg_match('/^(\d+)/', $carpeta, $m)) {
                            $order = (int) $m[1];
                        }
                        $sections[] = ['type' => 'folder', 'name' => $carpeta, 'order' => $order];
                    }
                    usort($sections, function ($a, $b) {
                        if ($a['order'] === $b['order']) {
                            return strcmp($a['name'], $b['name']);
                        }
                        return $a['order'] <=> $b['order'];
                    });
                @endphp

                @foreach ($sections as $section)
                    @if ($section['type'] === 'formulation')
                        <details class="rounded-lg border border-gray-200 bg-white">
                            <summary class="cursor-pointer list-none border-b border-gray-100 p-4 flex items-center justify-between">
                                <div class="text-base font-semibold text-gray-800">01 Formulación</div>
                            </summary>
                            <div class="p-4 space-y-3">
                                @php
                                    $formulationBuckets = [
                                        'general' => ['title' => 'Requisitos Generales', 'groups' => []],
                                        'bank' => ['title' => 'Documentos del Banco', 'groups' => []],
                                        'support' => ['title' => 'Otros Soportes', 'groups' => []],
                                        'strategic' => ['title' => 'Proyecto Estrategico', 'groups' => []],
                                    ];

                                    foreach ($formulationGroups as $carpeta => $groups) {
                                        foreach ($groups as $group) {
                                            $probe = $group['parent'] ?? ($group['children'][0] ?? null);
                                            $code = trim((string) ($probe->codigo_interno ?? $probe->orden ?? ''));
                                            $bucketKey = 'support';

                                            if (preg_match('/^1\.06(\b|\s|\.|$)/', $code)) {
                                                $bucketKey = 'bank';
                                            } elseif (preg_match('/^1\.13(\b|\s|\.|$)/', $code)) {
                                                $bucketKey = 'strategic';
                                            } elseif (preg_match('/^1\.0[1-5](\b|\s|\.|$)/', $code)) {
                                                $bucketKey = 'general';
                                            } elseif (preg_match('/^1\.(0[7-9]|1[0-2])(\b|\s|\.|$)/', $code)) {
                                                $bucketKey = 'support';
                                            }

                                            $formulationBuckets[$bucketKey]['groups'][] = $group;
                                        }
                                    }
                                @endphp

                                @foreach ($formulationBuckets as $bucket)
                                    @continue(empty($bucket['groups']))
                                    @php
                                        $bucketTotal = 0;
                                        $bucketActive = 0;
                                        foreach ($bucket['groups'] as $tmpGroup) {
                                            $items = collect();
                                            if (!empty($tmpGroup['parent'])) {
                                                $items->push($tmpGroup['parent']);
                                            }
                                            foreach (($tmpGroup['children'] ?? []) as $child) {
                                                $items->push($child);
                                            }
                                            foreach ($items as $item) {
                                                if (strtoupper((string) $item->requiere_check) === 'SI') {
                                                    $bucketTotal++;
                                                    if (in_array($item->id, $applied, true)) {
                                                        $bucketActive++;
                                                    }
                                                }
                                            }
                                        }
                                        $activeClass = $bucketActive > 0 ? 'text-emerald-600 font-semibold' : 'text-gray-500';
                                    @endphp
                                    <details class="border border-gray-100 rounded-md">
                                        <summary class="cursor-pointer list-none border-b border-gray-100 p-3 flex items-center justify-between">
                                            <div class="text-sm font-semibold text-gray-800">{{ $bucket['title'] }}</div>
                                            <div class="text-xs text-gray-500">
                                                <span class="{{ $activeClass }}">{{ $bucketActive }}</span> / <span>{{ $bucketTotal }}</span>
                                            </div>
                                        </summary>
                                        <div class="p-3 space-y-3">
                                            @foreach ($bucket['groups'] as $group)
                                                @php
                                                    $parent = $group['parent'] ?? null;
                                                    $children = $group['children'] ?? [];
                                                @endphp
                                                <div class="space-y-2">
                                                    @if ($parent)
                                                        <label class="flex items-start gap-3 text-sm text-gray-700">
                                                            @if (strtoupper((string) $parent->requiere_check) === 'SI')
                                                                <input type="checkbox" name="aplica[]" value="{{ $parent->id }}" class="mt-1 rounded border-gray-300" {{ in_array($parent->id, $applied) ? 'checked' : '' }}>
                                                            @endif
                                                            <div>
                                                                <div class="font-medium">{{ $parent->texto ?: $parent->requisito }}</div>
                                                                <div class="text-xs text-gray-500">Documento: {{ $parent->nombre_documento }}{{ $parent->codigo_interno ? ' | Código: ' . $parent->codigo_interno : '' }}</div>
                                                            </div>
                                                        </label>
                                                    @endif
                                                    @if (!empty($children))
                                                        <div class="pl-6 border-l border-gray-100 space-y-2">
                                                            @foreach ($children as $child)
                                                                <label class="flex items-start gap-3 text-sm text-gray-700">
                                                                    @if (strtoupper((string) $child->requiere_check) === 'SI')
                                                                        <input type="checkbox" name="aplica[]" value="{{ $child->id }}" class="mt-1 rounded border-gray-300" {{ in_array($child->id, $applied) ? 'checked' : '' }}>
                                                                    @endif
                                                                    <div>
                                                                        <div class="font-medium">{{ $child->texto ?: $child->requisito }}</div>
                                                                        <div class="text-xs text-gray-500">Documento: {{ $child->nombre_documento }}{{ $child->codigo_interno ? ' | Código: ' . $child->codigo_interno : '' }}</div>
                                                                    </div>
                                                                </label>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </details>
                                @endforeach
                            </div>
                        </details>
                    @elseif ($section['type'] === 'budget')
                        <details class="rounded-lg border border-gray-200 bg-white">
                            <summary class="cursor-pointer list-none border-b border-gray-100 p-4 flex items-center justify-between">
                                <div class="text-base font-semibold text-gray-800">02 Presupuesto</div>
                            </summary>
                            <div class="p-4 space-y-3">
                                @foreach ($budgetGroups as $carpeta => $groups)
                                    <details class="border border-gray-100 rounded-md">
                                        <summary class="cursor-pointer list-none border-b border-gray-100 p-3 flex items-center justify-between">
                                            <div class="text-sm font-semibold text-gray-800">{{ $carpeta }}</div>
                                            <div class="text-xs text-gray-500">
                                                @php
                                                    $totals = $totalsByFolder[$carpeta] ?? ['active' => 0, 'total' => 0];
                                                    $activeClass = ((int) $totals['active']) > 0 ? 'text-emerald-600 font-semibold' : 'text-gray-500';
                                                @endphp
                                                <span class="{{ $activeClass }}">{{ $totals['active'] }}</span> / <span>{{ $totals['total'] }}</span>
                                            </div>
                                        </summary>
                                        <div class="p-3 space-y-3">
                                            @foreach ($groups as $group)
                                                @php
                                                    $parent = $group['parent'] ?? null;
                                                    $children = $group['children'] ?? [];
                                                @endphp
                                                <div class="space-y-2">
                                                    @if ($parent)
                                                        <label class="flex items-start gap-3 text-sm text-gray-700">
                                                            @if (strtoupper((string) $parent->requiere_check) === 'SI')
                                                                <input type="checkbox" name="aplica[]" value="{{ $parent->id }}" class="mt-1 rounded border-gray-300" {{ in_array($parent->id, $applied) ? 'checked' : '' }}>
                                                            @endif
                                                            <div>
                                                                <div class="font-medium">{{ $parent->texto ?: $parent->requisito }}</div>
                                                                <div class="text-xs text-gray-500">Documento: {{ $parent->nombre_documento }}{{ $parent->codigo_interno ? ' | Código: ' . $parent->codigo_interno : '' }}</div>
                                                            </div>
                                                        </label>
                                                    @endif
                                                    @if (!empty($children))
                                                        <div class="pl-6 border-l border-gray-100 space-y-2">
                                                            @foreach ($children as $child)
                                                                <label class="flex items-start gap-3 text-sm text-gray-700">
                                                                    @if (strtoupper((string) $child->requiere_check) === 'SI')
                                                                        <input type="checkbox" name="aplica[]" value="{{ $child->id }}" class="mt-1 rounded border-gray-300" {{ in_array($child->id, $applied) ? 'checked' : '' }}>
                                                                    @endif
                                                                    <div>
                                                                        <div class="font-medium">{{ $child->texto ?: $child->requisito }}</div>
                                                                        <div class="text-xs text-gray-500">Documento: {{ $child->nombre_documento }}{{ $child->codigo_interno ? ' | Código: ' . $child->codigo_interno : '' }}</div>
                                                                    </div>
                                                                </label>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </details>
                                @endforeach
                            </div>
                        </details>
                    @elseif ($section['type'] === 'cert')
                        <details class="rounded-lg border border-gray-200 bg-white">
                            <summary class="cursor-pointer list-none border-b border-gray-100 p-4 flex items-center justify-between">
                                <div class="text-base font-semibold text-gray-800">03 Certificaciones</div>
                            </summary>
                            <div class="p-4 space-y-3">
                                @foreach ($certGroups as $carpeta => $groups)
                                    <details class="border border-gray-100 rounded-md">
                                        <summary class="cursor-pointer list-none border-b border-gray-100 p-3 flex items-center justify-between">
                                            <div class="text-sm font-semibold text-gray-800">{{ $carpeta }}</div>
                                            <div class="text-xs text-gray-500">
                                                @php $totals = $totalsByFolder[$carpeta] ?? ['active' => 0, 'total' => 0]; @endphp
                                                {{ $totals['active'] }} / {{ $totals['total'] }}
                                            </div>
                                        </summary>
                                        @php
                                            $isSectorialFolder = \Illuminate\Support\Str::contains(
                                                \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii((string) $carpeta)),
                                                'documentos sectoriales'
                                            );
                                        @endphp

                                        @if ($isSectorialFolder)
                                            @php
                                                $sectorBuckets = [];
                                                foreach (($sectorCatalog['ordered'] ?? []) as $sectorRow) {
                                                    $k = (string) ($sectorRow['normalized'] ?? '');
                                                    if ($k === '') {
                                                        continue;
                                                    }
                                                    $sectorBuckets[$k] = [
                                                        'label' => (string) ($sectorRow['name'] ?? $k),
                                                        'is_primary' => (bool) ($sectorRow['is_primary'] ?? false),
                                                        'requirements' => collect(),
                                                    ];
                                                }

                                                foreach ($groups as $group) {
                                                    if (!empty($group['parent'])) {
                                                        $req = $group['parent'];
                                                        $k = trim(mb_strtolower(\Illuminate\Support\Str::ascii((string) ($req->sector ?? ''))));
                                                        if (!isset($sectorBuckets[$k])) {
                                                            continue;
                                                        }
                                                        $sectorBuckets[$k]['requirements']->push($req);
                                                    }
                                                    foreach (($group['children'] ?? []) as $req) {
                                                        $k = trim(mb_strtolower(\Illuminate\Support\Str::ascii((string) ($req->sector ?? ''))));
                                                        if (!isset($sectorBuckets[$k])) {
                                                            continue;
                                                        }
                                                        $sectorBuckets[$k]['requirements']->push($req);
                                                    }
                                                }

                                                foreach ($sectorBuckets as $k => $bucket) {
                                                    $sectorBuckets[$k]['requirements'] = $bucket['requirements']
                                                        ->unique('id')
                                                        ->sortBy(function ($req) {
                                                            return sprintf('%08d-%s', (int) ($req->orden ?? 0), (string) ($req->codigo_interno ?? ''));
                                                        })
                                                        ->values();
                                                }
                                            @endphp

                                            <div class="space-y-2">
                                                @foreach ($sectorBuckets as $bucket)
                                                    <details class="rounded-md border border-gray-200">
                                                        <summary class="cursor-pointer list-none bg-gray-50 px-3 py-2">
                                                            <div class="text-xs font-semibold text-gray-700">
                                                                {{ $bucket['label'] }}
                                                                <span class="ml-2 text-[11px] {{ $bucket['is_primary'] ? 'text-emerald-700' : 'text-sky-700' }}">
                                                                    {{ $bucket['is_primary'] ? 'Sector principal' : 'Sector secundario' }}
                                                                </span>
                                                            </div>
                                                        </summary>
                                                        <div class="p-3 space-y-2">
                                                            @if ($bucket['requirements']->isEmpty())
                                                                <div class="text-xs text-gray-500">No hay requisitos sectoriales para este sector.</div>
                                                            @endif
                                                            @foreach ($bucket['requirements'] as $req)
                                                                <label class="flex items-start gap-3 text-sm text-gray-700">
                                                                    @if (strtoupper((string) $req->requiere_check) === 'SI')
                                                                        <input type="checkbox" name="aplica[]" value="{{ $req->id }}" class="mt-1 rounded border-gray-300" {{ in_array($req->id, $applied) ? 'checked' : '' }}>
                                                                    @endif
                                                                    <div>
                                                                        <div class="font-medium">{{ $req->texto ?: $req->requisito }}</div>
                                                                        <div class="text-xs text-gray-500">Documento: {{ $req->nombre_documento }}{{ $req->codigo_interno ? ' | Código: ' . $req->codigo_interno : '' }}</div>
                                                                    </div>
                                                                </label>
                                                            @endforeach
                                                        </div>
                                                    </details>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="p-3 space-y-3">
                                                @foreach ($groups as $group)
                                                    @php
                                                        $parent = $group['parent'] ?? null;
                                                        $children = $group['children'] ?? [];
                                                    @endphp
                                                    <div class="space-y-2">
                                                        @if ($parent)
                                                            <label class="flex items-start gap-3 text-sm text-gray-700">
                                                                @if (strtoupper((string) $parent->requiere_check) === 'SI')
                                                                    <input type="checkbox" name="aplica[]" value="{{ $parent->id }}" class="mt-1 rounded border-gray-300" {{ in_array($parent->id, $applied) ? 'checked' : '' }}>
                                                                @endif
                                                                <div>
                                                                    <div class="font-medium">{{ $parent->texto ?: $parent->requisito }}</div>
                                                                    <div class="text-xs text-gray-500">Documento: {{ $parent->nombre_documento }}{{ $parent->codigo_interno ? ' | Código: ' . $parent->codigo_interno : '' }}</div>
                                                                </div>
                                                            </label>
                                                        @endif
                                                        @if (!empty($children))
                                                            <div class="pl-6 border-l border-gray-100 space-y-2">
                                                                @foreach ($children as $child)
                                                                    <label class="flex items-start gap-3 text-sm text-gray-700">
                                                                        @if (strtoupper((string) $child->requiere_check) === 'SI')
                                                                            <input type="checkbox" name="aplica[]" value="{{ $child->id }}" class="mt-1 rounded border-gray-300" {{ in_array($child->id, $applied) ? 'checked' : '' }}>
                                                                        @endif
                                                                        <div>
                                                                            <div class="font-medium">{{ $child->texto ?: $child->requisito }}</div>
                                                                            <div class="text-xs text-gray-500">Documento: {{ $child->nombre_documento }}{{ $child->codigo_interno ? ' | Código: ' . $child->codigo_interno : '' }}</div>
                                                                        </div>
                                                                    </label>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </details>
                                @endforeach
                            </div>
                        </details>
                    @elseif ($section['type'] === 'studies')
                        <details class="rounded-lg border border-gray-200 bg-white">
                            <summary class="cursor-pointer list-none border-b border-gray-100 p-4 flex items-center justify-between">
                                <div class="text-base font-semibold text-gray-800">05 Estudios y Diseños</div>
                            </summary>
                            <div class="p-4">
                                <div class="grid gap-3 grid-cols-1 md:grid-cols-3 items-start">
                                    @foreach ($studiesGroups as $carpeta => $groups)
                                        <details class="border border-gray-100 rounded-md lg:col-span-1">
                                            <summary class="cursor-pointer list-none border-b border-gray-100 p-3 flex items-start justify-between gap-3">
                                                @php $totals = $totalsByFolder[$carpeta] ?? ['active' => 0, 'total' => 0]; @endphp
                                                <div class="text-sm font-semibold" style="{{ ((int) $totals['active'] > 0) ? 'color:#7e22ce;' : 'color:#1f2937;' }}">{{ $carpeta }}</div>
                                                <div class="text-xs text-gray-500 whitespace-nowrap">
                                                    @if ((int) $totals['active'] > 0)
                                                        <span class="font-semibold" style="color:#7e22ce;">{{ $totals['active'] }}</span>
                                                    @else
                                                        <span class="text-gray-500">{{ $totals['active'] }}</span>
                                                    @endif
                                                    <span class="text-gray-400"> / </span>
                                                    <span class="text-gray-500">{{ $totals['total'] }}</span>
                                                </div>
                                            </summary>
                                            <div class="p-3 space-y-3">
                                                <div class="rounded-md border border-emerald-100 bg-emerald-50/60 p-3 space-y-2">
                                                    <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Especialista del estudio</div>
                                                    <select name="study_specialists[{{ $carpeta }}]" class="w-full rounded-md border-gray-300 text-sm focus:border-emerald-400 focus:ring-emerald-300">
                                                        <option value="">Sin asignar</option>
                                                        @foreach (($specialistOptions ?? []) as $specialistId => $specialistLabel)
                                                            <option value="{{ $specialistId }}" {{ (int) (($studyAssignments[$carpeta] ?? 0)) === (int) $specialistId ? 'selected' : '' }}>{{ $specialistLabel }}</option>
                                                        @endforeach
                                                    </select>
                                                    @php
                                                        $selectedSpecialistId = (int) (($studyAssignments[$carpeta] ?? 0) ?: 0);
                                                        $selectedSpecialist = $selectedSpecialistId > 0 ? (($specialistDetails ?? [])[$selectedSpecialistId] ?? null) : null;
                                                        $planeState = $selectedSpecialist['plane_sync_status'] ?? null;
                                                        $planeStateLabel = match ($planeState) {
                                                            'linked' => 'Vinculado en Plane',
                                                            'not_found' => 'No encontrado en Plane',
                                                            'error' => 'Con novedad en Plane',
                                                            default => 'Pendiente por sincronizar',
                                                        };
                                                        $planeStateClass = match ($planeState) {
                                                            'linked' => 'border-emerald-200 bg-emerald-100 text-emerald-700',
                                                            'not_found' => 'border-amber-200 bg-amber-100 text-amber-800',
                                                            'error' => 'border-rose-200 bg-rose-100 text-rose-700',
                                                            default => 'border-gray-200 bg-gray-100 text-gray-600',
                                                        };
                                                    @endphp
                                                    @if ($selectedSpecialist)
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $planeStateClass }}">
                                                                {{ $planeStateLabel }}
                                                            </span>
                                                            <span class="text-[11px] text-gray-600">{{ $selectedSpecialist['correo'] }}</span>
                                                        </div>
                                                        @if (!empty($selectedSpecialist['plane_last_error']) && $planeState !== 'linked')
                                                            <div class="rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-[11px] leading-4 text-amber-800">
                                                                {{ $selectedSpecialist['plane_last_error'] }}
                                                            </div>
                                                        @endif
                                                    @endif
                                                    <div class="text-[11px] text-emerald-700/80">Orbit usará este especialista para resolver el vínculo con Plane por correo justo cuando sincronice las actividades del estudio.</div>
                                                </div>
                                                @php
                                                    $flat = collect();
                                                    foreach ($groups as $group) {
                                                        if (!empty($group['parent'])) {
                                                            $flat->push($group['parent']);
                                                        }
                                                        if (!empty($group['children'])) {
                                                            foreach ($group['children'] as $child) {
                                                                $flat->push($child);
                                                            }
                                                        }
                                                    }
                                                    $groupedByTitle = $flat->groupBy(function ($req) {
                                                        $title = $req->texto ?: $req->requisito ?: '';
                                                        $code = $req->codigo_interno ?: '';
                                                        return trim($title . '||' . $code);
                                                    });
                                                @endphp
                                                <div class="space-y-4">
                                                    @foreach ($groupedByTitle as $titleKey => $items)
                                                        @php
                                                            [$title, $code] = array_pad(explode('||', $titleKey, 2), 2, '');
                                                            $groupKey = 'gs' . $loop->parent->index . '_' . $loop->index;
                                                        @endphp
                                                        <div class="w-full border border-gray-100 rounded-md p-4 space-y-3">
                                                            <div class="flex items-start justify-between gap-3">
                                                                <div class="text-sm font-semibold text-gray-800">{{ $title }}{{ $code ? ' | Código: ' . $code : '' }}</div>
                                                                <label class="flex items-center gap-2 text-xs text-gray-500">
                                                                    <input type="checkbox" class="rounded border-gray-300" onclick="document.querySelectorAll('.group-{{ $groupKey }}').forEach(cb => { cb.checked = this.checked; });">
                                                                    Todas
                                                                </label>
                                                            </div>
                                                            <div class="space-y-2">
                                                                @foreach ($items as $item)
                                                                    <label class="flex items-start gap-3 text-sm text-gray-700">
                                                                        @if (strtoupper((string) $item->requiere_check) === 'SI')
                                                                            <input type="checkbox" name="aplica[]" value="{{ $item->id }}" class="mt-1 rounded border-gray-300 group-{{ $groupKey }}" {{ in_array($item->id, $applied) ? 'checked' : '' }}>
                                                                        @endif
                                                                        <div><div class="font-medium">Documento: {{ $item->nombre_documento }}</div></div>
                                                                    </label>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </details>
                                    @endforeach
                                </div>
                            </div>
                        </details>
                    @else
                        @php
                            $carpeta = $section['name'];
                            $groups = $regularGroups[$carpeta];
                            $totals = $totalsByFolder[$carpeta] ?? ['active' => 0, 'total' => 0];
                        @endphp
                        <details class="rounded-lg border border-gray-200 bg-white">
                            <summary class="cursor-pointer list-none border-b border-gray-100 p-4 flex items-center justify-between">
                                <div class="text-base font-semibold text-gray-800">{{ $carpeta }}</div>
                                <div class="text-xs text-gray-500">{{ $totals['active'] }} / {{ $totals['total'] }}</div>
                            </summary>
                            <div class="p-4 space-y-3">
                                @foreach ($groups as $group)
                                    @php
                                        $parent = $group['parent'] ?? null;
                                        $children = $group['children'] ?? [];
                                    @endphp
                                    <div class="space-y-2">
                                        @if ($parent)
                                            <label class="flex items-start gap-3 text-sm text-gray-700">
                                                @if (strtoupper((string) $parent->requiere_check) === 'SI')
                                                    <input type="checkbox" name="aplica[]" value="{{ $parent->id }}" class="mt-1 rounded border-gray-300" {{ in_array($parent->id, $applied) ? 'checked' : '' }}>
                                                @endif
                                                <div>
                                                    <div class="font-medium">{{ $parent->texto ?: $parent->requisito }}</div>
                                                    <div class="text-xs text-gray-500">Documento: {{ $parent->nombre_documento }}{{ $parent->codigo_interno ? ' | Código: ' . $parent->codigo_interno : '' }}</div>
                                                </div>
                                            </label>
                                        @endif
                                        @if (!empty($children))
                                            <div class="pl-6 border-l border-gray-100 space-y-2">
                                                @foreach ($children as $child)
                                                    <label class="flex items-start gap-3 text-sm text-gray-700">
                                                        @if (strtoupper((string) $child->requiere_check) === 'SI')
                                                            <input type="checkbox" name="aplica[]" value="{{ $child->id }}" class="mt-1 rounded border-gray-300" {{ in_array($child->id, $applied) ? 'checked' : '' }}>
                                                        @endif
                                                        <div>
                                                            <div class="font-medium">{{ $child->texto ?: $child->requisito }}</div>
                                                            <div class="text-xs text-gray-500">Documento: {{ $child->nombre_documento }}{{ $child->codigo_interno ? ' | Código: ' . $child->codigo_interno : '' }}</div>
                                                        </div>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endif
                @endforeach

                <div class="sticky bottom-2">
                    <div class="rounded-lg border border-indigo-100 bg-white/95 backdrop-blur px-3 py-2 flex justify-end">
                        <x-filament::button type="submit" size="sm">
                            Guardar checklist
                        </x-filament::button>
                    </div>
                </div>
            </form>
        @endif
    </div>
</x-filament-panels::page>
