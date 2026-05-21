<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Requisitos') }}
                </h2>
                <p class="text-sm text-gray-500">Proyecto: {{ $project->nombre }}</p>
            </div>
            <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-emerald-50 p-4 text-emerald-700 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            @if ($requirements->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-6 text-gray-500">
                    No hay requisitos cargados. Importa el Excel desde Requisitos.
                </div>
            @else
                <form method="POST" action="{{ route('projects.checklist.update', $project) }}">
                    @csrf
                    @method('PUT')

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
                            if (preg_match('/^5\./', (string) $folderKey)
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
                            $sections[] = [
                                'type' => 'formulation',
                                'name' => '01 Formulación',
                                'order' => 1,
                            ];
                        }
                        if (!empty($budgetGroups)) {
                            $sections[] = [
                                'type' => 'budget',
                                'name' => '02 Presupuesto',
                                'order' => 2,
                            ];
                        }
                        if (!empty($certGroups)) {
                            $sections[] = [
                                'type' => 'cert',
                                'name' => '03 Certificaciones',
                                'order' => 3,
                            ];
                        }
                        if (!empty($studiesGroups)) {
                            $sections[] = [
                                'type' => 'studies',
                                'name' => '05 Estudios y Diseños',
                                'order' => 5,
                            ];
                        }
                        foreach ($regularGroups as $carpeta => $groups) {
                            $order = 999;
                            if (preg_match('/^(\d+)/', $carpeta, $m)) {
                                $order = (int) $m[1];
                            }
                            $sections[] = [
                                'type' => 'folder',
                                'name' => $carpeta,
                                'order' => $order,
                            ];
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
                            <details class="bg-white shadow-sm sm:rounded-lg">
                                <summary class="cursor-pointer list-none border-b border-gray-100 p-6 flex items-center justify-between">
                                    <div class="text-lg font-semibold text-gray-800">01 Formulación</div>
                                </summary>
                                <div class="p-6 space-y-3">
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
                                                if (!empty($tmpGroup['children'])) {
                                                    foreach ($tmpGroup['children'] as $child) {
                                                        $items->push($child);
                                                    }
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
                                            <summary class="cursor-pointer list-none border-b border-gray-100 p-4 flex items-center justify-between">
                                                <div class="text-sm font-semibold text-gray-800">{{ $bucket['title'] }}</div>
                                                <div class="text-xs text-gray-500">
                                                    <span class="{{ $activeClass }}">{{ $bucketActive }}</span> / <span>{{ $bucketTotal }}</span>
                                                </div>
                                            </summary>
                                            <div class="p-4 space-y-3">
                                                @foreach ($bucket['groups'] as $group)
                                                    @php
                                                        $parent = $group['parent'] ?? null;
                                                        $children = $group['children'] ?? [];
                                                    @endphp

                                                    <div class="space-y-2">
                                                        @if ($parent)
                                                            <label class="flex items-start gap-3 text-sm text-gray-700">
                                                                @if (strtoupper((string) $parent->requiere_check) === 'SI')
                                                                    <input type="checkbox" name="aplica[]" value="{{ $parent->id }}" class="mt-1 rounded border-gray-300"
                                                                        {{ in_array($parent->id, $applied) ? 'checked' : '' }}>
                                                                @endif
                                                                <div>
                                                                    <div class="font-medium">
                                                                        {{ $parent->texto ?: $parent->requisito }}
                                                                    </div>
                                                                    <div class="text-xs text-gray-500">
                                                                        Documento: {{ $parent->nombre_documento }}{{ $parent->codigo_interno ? ' | Código: ' . $parent->codigo_interno : '' }}
                                                                    </div>
                                                                </div>
                                                            </label>
                                                        @else
                                                            <div class="text-sm font-semibold text-gray-700">
                                                                Grupo
                                                            </div>
                                                        @endif

                                                        @if (!empty($children))
                                                            <div class="pl-6 border-l border-gray-100 space-y-2">
                                                                @foreach ($children as $child)
                                                                    <label class="flex items-start gap-3 text-sm text-gray-700">
                                                                        @if (strtoupper((string) $child->requiere_check) === 'SI')
                                                                            <input type="checkbox" name="aplica[]" value="{{ $child->id }}" class="mt-1 rounded border-gray-300"
                                                                                {{ in_array($child->id, $applied) ? 'checked' : '' }}>
                                                                        @endif
                                                                        <div>
                                                                            <div class="font-medium">
                                                                                {{ $child->texto ?: $child->requisito }}
                                                                            </div>
                                                                            <div class="text-xs text-gray-500">
                                                                                Documento: {{ $child->nombre_documento }}{{ $child->codigo_interno ? ' | Código: ' . $child->codigo_interno : '' }}
                                                                            </div>
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
                            <details class="bg-white shadow-sm sm:rounded-lg">
                                <summary class="cursor-pointer list-none border-b border-gray-100 p-6 flex items-center justify-between">
                                    <div class="text-lg font-semibold text-gray-800">02 Presupuesto</div>
                                </summary>
                                <div class="p-6 space-y-3">
                                    @foreach ($budgetGroups as $carpeta => $groups)
                                        <details class="border border-gray-100 rounded-md">
                                            <summary class="cursor-pointer list-none border-b border-gray-100 p-4 flex items-center justify-between">
                                                <div class="text-sm font-semibold text-gray-800">{{ $carpeta }}</div>
                                                <div class="text-xs text-gray-500">
                                                    @php
                                                        $totals = $totalsByFolder[$carpeta] ?? ['active' => 0, 'total' => 0];
                                                        $activeClass = ((int) $totals['active']) > 0 ? 'text-emerald-600 font-semibold' : 'text-gray-500';
                                                    @endphp
                                                    <span class="{{ $activeClass }}">{{ $totals['active'] }}</span> / <span>{{ $totals['total'] }}</span>
                                                </div>
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
                                                                    <input type="checkbox" name="aplica[]" value="{{ $parent->id }}" class="mt-1 rounded border-gray-300"
                                                                        {{ in_array($parent->id, $applied) ? 'checked' : '' }}>
                                                                @endif
                                                                <div>
                                                                    <div class="font-medium">
                                                                        {{ $parent->texto ?: $parent->requisito }}
                                                                    </div>
                                                                    <div class="text-xs text-gray-500">
                                                                        Documento: {{ $parent->nombre_documento }}{{ $parent->codigo_interno ? ' | Código: ' . $parent->codigo_interno : '' }}
                                                                    </div>
                                                                </div>
                                                            </label>
                                                        @else
                                                            <div class="text-sm font-semibold text-gray-700">
                                                                Grupo
                                                            </div>
                                                        @endif

                                                        @if (!empty($children))
                                                            <div class="pl-6 border-l border-gray-100 space-y-2">
                                                                @foreach ($children as $child)
                                                                    <label class="flex items-start gap-3 text-sm text-gray-700">
                                                                        @if (strtoupper((string) $child->requiere_check) === 'SI')
                                                                            <input type="checkbox" name="aplica[]" value="{{ $child->id }}" class="mt-1 rounded border-gray-300"
                                                                                {{ in_array($child->id, $applied) ? 'checked' : '' }}>
                                                                        @endif
                                                                        <div>
                                                                            <div class="font-medium">
                                                                                {{ $child->texto ?: $child->requisito }}
                                                                            </div>
                                                                            <div class="text-xs text-gray-500">
                                                                                Documento: {{ $child->nombre_documento }}{{ $child->codigo_interno ? ' | Código: ' . $child->codigo_interno : '' }}
                                                                            </div>
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
                            <details class="bg-white shadow-sm sm:rounded-lg">
                                <summary class="cursor-pointer list-none border-b border-gray-100 p-6 flex items-center justify-between">
                                    <div class="text-lg font-semibold text-gray-800">03 Certificaciones</div>
                                </summary>
                                <div class="p-6 space-y-3">
                                    @foreach ($certGroups as $carpeta => $groups)
                                        <details class="border border-gray-100 rounded-md">
                                            <summary class="cursor-pointer list-none border-b border-gray-100 p-4 flex items-center justify-between">
                                                <div class="text-sm font-semibold text-gray-800">{{ $carpeta }}</div>
                                                <div class="text-xs text-gray-500">
                                                    @php
                                                        $totals = $totalsByFolder[$carpeta] ?? ['active' => 0, 'total' => 0];
                                                    @endphp
                                                    {{ $totals['active'] }} / {{ $totals['total'] }}
                                                </div>
                                            </summary>
                                            <div class="p-4 space-y-3">
                                                @php
                                                    $isSectorialFolder = \Illuminate\Support\Str::contains(
                                                        \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii((string) $carpeta)),
                                                        'documentos sectoriales'
                                                    );
                                                    $sectorGroups = [];
                                                    if ($isSectorialFolder) {
                                                        $sectorOrder = collect($sectorCatalog['ordered'] ?? [])->pluck('normalized')->values()->all();
                                                        foreach ($groups as $group) {
                                                            $probe = $group['parent'] ?? ($group['children'][0] ?? null);
                                                            $sectorLabel = trim((string) ($probe->sector ?? 'Sin sector'));
                                                            $sectorNorm = trim(mb_strtolower(\Illuminate\Support\Str::ascii($sectorLabel)));
                                                            if ($sectorNorm === '') {
                                                                $sectorNorm = 'sin-sector';
                                                                $sectorLabel = 'Sin sector';
                                                            }
                                                            $sectorGroups[$sectorNorm]['label'] = $sectorLabel;
                                                            $sectorGroups[$sectorNorm]['groups'][] = $group;
                                                        }

                                                        uksort($sectorGroups, function ($a, $b) use ($sectorOrder) {
                                                            $ai = array_search($a, $sectorOrder, true);
                                                            $bi = array_search($b, $sectorOrder, true);
                                                            $ai = $ai === false ? 999 : $ai;
                                                            $bi = $bi === false ? 999 : $bi;
                                                            return $ai <=> $bi ?: strcmp($a, $b);
                                                        });
                                                    } else {
                                                        $sectorGroups = ['_default' => ['label' => null, 'groups' => $groups]];
                                                    }
                                                @endphp

                                                @foreach ($sectorGroups as $sectorKey => $bucket)
                                                    @php
                                                        $sectorMeta = $isSectorialFolder ? collect($sectorCatalog['ordered'] ?? [])->firstWhere('normalized', $sectorKey) : null;
                                                        $sectorTotal = 0;
                                                        $sectorActive = 0;
                                                        foreach ($bucket['groups'] as $tmpGroup) {
                                                            $tmpReqs = collect();
                                                            if (!empty($tmpGroup['parent'])) {
                                                                $tmpReqs->push($tmpGroup['parent']);
                                                            }
                                                            if (!empty($tmpGroup['children'])) {
                                                                foreach ($tmpGroup['children'] as $tmpChild) {
                                                                    $tmpReqs->push($tmpChild);
                                                                }
                                                            }
                                                            foreach ($tmpReqs as $tmpReq) {
                                                                if (strtoupper((string) ($tmpReq->requiere_check ?? '')) === 'SI') {
                                                                    $sectorTotal++;
                                                                    if (in_array($tmpReq->id, $applied, true)) {
                                                                        $sectorActive++;
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    @endphp

                                                    @if ($isSectorialFolder)
                                                        <details class="rounded-md border border-gray-200" @if (($sectorMeta['is_primary'] ?? false) === true) open @endif>
                                                            <summary class="cursor-pointer list-none bg-gray-50 px-3 py-2 flex items-center justify-between gap-3">
                                                                <div class="text-xs font-semibold text-gray-700">
                                                                    {{ $bucket['label'] }}
                                                                    @if ($sectorMeta)
                                                                        <span class="ml-2 text-[11px] {{ ($sectorMeta['is_primary'] ?? false) ? 'text-emerald-700' : 'text-sky-700' }}">
                                                                            {{ ($sectorMeta['is_primary'] ?? false) ? 'Sector principal' : 'Sector secundario' }}
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                                <div class="text-[11px] text-gray-500">{{ $sectorActive }} / {{ $sectorTotal }}</div>
                                                            </summary>
                                                            <div class="p-3 space-y-2">
                                                    @endif

                                                @foreach ($bucket['groups'] as $group)
                                                    @php
                                                        $parent = $group['parent'] ?? null;
                                                        $children = $group['children'] ?? [];
                                                    @endphp

                                                    <div class="space-y-2">
                                                        @if ($parent)
                                                            <label class="flex items-start gap-3 text-sm text-gray-700">
                                                                @if (strtoupper((string) $parent->requiere_check) === 'SI')
                                                                    <input type="checkbox" name="aplica[]" value="{{ $parent->id }}" class="mt-1 rounded border-gray-300"
                                                                        {{ in_array($parent->id, $applied) ? 'checked' : '' }}>
                                                                @endif
                                                                <div>
                                                                    <div class="font-medium">
                                                                        {{ $parent->texto ?: $parent->requisito }}
                                                                    </div>
                                                                    <div class="text-xs text-gray-500">
                                                                        Documento: {{ $parent->nombre_documento }}{{ $parent->codigo_interno ? ' | Código: ' . $parent->codigo_interno : '' }}
                                                                    </div>
                                                                </div>
                                                            </label>
                                                        @else
                                                            <div class="text-sm font-semibold text-gray-700">
                                                                Grupo
                                                            </div>
                                                        @endif

                                                        @if (!empty($children))
                                                            <div class="pl-6 border-l border-gray-100 space-y-2">
                                                                @foreach ($children as $child)
                                                                    <label class="flex items-start gap-3 text-sm text-gray-700">
                                                                        @if (strtoupper((string) $child->requiere_check) === 'SI')
                                                                            <input type="checkbox" name="aplica[]" value="{{ $child->id }}" class="mt-1 rounded border-gray-300"
                                                                                {{ in_array($child->id, $applied) ? 'checked' : '' }}>
                                                                        @endif
                                                                        <div>
                                                                            <div class="font-medium">
                                                                                {{ $child->texto ?: $child->requisito }}
                                                                            </div>
                                                                            <div class="text-xs text-gray-500">
                                                                                Documento: {{ $child->nombre_documento }}{{ $child->codigo_interno ? ' | Código: ' . $child->codigo_interno : '' }}
                                                                            </div>
                                                                        </div>
                                                                    </label>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach

                                                    @if ($isSectorialFolder)
                                                            </div>
                                                        </details>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </details>
                                    @endforeach
                                </div>
                            </details>
                        @elseif ($section['type'] === 'studies')
                            <details class="bg-white shadow-sm sm:rounded-lg">
                                <summary class="cursor-pointer list-none border-b border-gray-100 p-6 flex items-center justify-between">
                                    <div class="text-lg font-semibold text-gray-800">05 Estudios y Diseños</div>
                                </summary>
                                <div class="p-6">
                                    <div class="grid gap-3 lg:grid-cols-3 items-start">
                                    @foreach ($studiesGroups as $carpeta => $groups)
                                        <details class="border border-gray-100 rounded-md lg:col-span-1 [&[open]]:lg:col-span-3 transition-all">
                                            <summary class="cursor-pointer list-none border-b border-gray-100 p-4 flex items-start justify-between gap-3">
                                                @php
                                                    $totals = $totalsByFolder[$carpeta] ?? ['active' => 0, 'total' => 0];
                                                @endphp
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
                                            <div class="p-4 space-y-3">
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
                                                                <div class="text-sm font-semibold text-gray-800">
                                                                    {{ $title }}{{ $code ? ' | Código: ' . $code : '' }}
                                                                </div>
                                                                <label class="flex items-center gap-2 text-xs text-gray-500">
                                                                    <input type="checkbox" class="rounded border-gray-300"
                                                                        onclick="document.querySelectorAll('.group-{{ $groupKey }}').forEach(cb => { cb.checked = this.checked; });">
                                                                    Todas
                                                                </label>
                                                            </div>
                                                            <div class="space-y-2">
                                                                @foreach ($items as $item)
                                                                    <label class="flex items-start gap-3 text-sm text-gray-700">
                                                                        @if (strtoupper((string) $item->requiere_check) === 'SI')
                                                                            <input type="checkbox" name="aplica[]" value="{{ $item->id }}" class="mt-1 rounded border-gray-300 group-{{ $groupKey }}"
                                                                                {{ in_array($item->id, $applied) ? 'checked' : '' }}>
                                                                        @endif
                                                                        <div>
                                                                            <div class="font-medium">
                                                                                Documento: {{ $item->nombre_documento }}
                                                                            </div>
                                                                        </div>
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
                            @endphp
                            <details class="bg-white shadow-sm sm:rounded-lg">
                            <summary class="cursor-pointer list-none border-b border-gray-100 p-6 flex items-center justify-between">
                                <div class="text-lg font-semibold text-gray-800">{{ $carpeta }}</div>
                                <div class="text-xs text-gray-500">
                                    @php
                                        $totals = $totalsByFolder[$carpeta] ?? ['active' => 0, 'total' => 0];
                                    @endphp
                                    {{ $totals['active'] }} / {{ $totals['total'] }}
                                </div>
                            </summary>
                            <div class="p-6 space-y-3">
                                @php
                                    $isEstudios = str_contains(\Illuminate\Support\Str::lower((string) $carpeta), 'estudios y disenos')
                                        || str_contains(\Illuminate\Support\Str::lower((string) $carpeta), 'estudios y diseños')
                                        || preg_match('/^05\b/', (string) $carpeta);
                                @endphp

                                @if ($isEstudios)
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

                                    <div class="grid gap-4 md:grid-cols-3">
                                    @foreach ($groupedByTitle as $titleKey => $items)
                                        @php
                                            [$title, $code] = array_pad(explode('||', $titleKey, 2), 2, '');
                                            $groupKey = 'g' . $loop->index;
                                        @endphp
                                        <div class="border border-gray-100 rounded-md p-4 space-y-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="text-sm font-semibold text-gray-800">
                                                    {{ $title }}{{ $code ? ' | Código: ' . $code : '' }}
                                                </div>
                                                <label class="flex items-center gap-2 text-xs text-gray-500">
                                                    <input type="checkbox" class="rounded border-gray-300"
                                                        onclick="document.querySelectorAll('.group-{{ $groupKey }}').forEach(cb => { cb.checked = this.checked; });">
                                                    Todas
                                                </label>
                                            </div>
                                            <div class="space-y-2">
                                                @foreach ($items as $item)
                                                    <label class="flex items-start gap-3 text-sm text-gray-700">
                                                        @if (strtoupper((string) $item->requiere_check) === 'SI')
                                                            <input type="checkbox" name="aplica[]" value="{{ $item->id }}" class="mt-1 rounded border-gray-300 group-{{ $groupKey }}"
                                                                {{ in_array($item->id, $applied) ? 'checked' : '' }}>
                                                        @endif
                                                        <div>
                                                            <div class="font-medium">
                                                                Documento: {{ $item->nombre_documento }}
                                                            </div>
                                                        </div>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                    </div>
                                @else
                                    @foreach ($groups as $group)
                                        @php
                                            $parent = $group['parent'] ?? null;
                                            $children = $group['children'] ?? [];
                                        @endphp

                                        <div class="space-y-2">
                                            @if ($parent)
                                                <label class="flex items-start gap-3 text-sm text-gray-700">
                                                    @if (strtoupper((string) $parent->requiere_check) === 'SI')
                                                        <input type="checkbox" name="aplica[]" value="{{ $parent->id }}" class="mt-1 rounded border-gray-300"
                                                            {{ in_array($parent->id, $applied) ? 'checked' : '' }}>
                                                    @endif
                                                    <div>
                                                        <div class="font-medium">
                                                            {{ $parent->texto ?: $parent->requisito }}
                                                        </div>
                                                        <div class="text-xs text-gray-500">
                                                            Documento: {{ $parent->nombre_documento }}{{ $parent->codigo_interno ? ' | Código: ' . $parent->codigo_interno : '' }}
                                                        </div>
                                                    </div>
                                                </label>
                                            @else
                                                <div class="text-sm font-semibold text-gray-700">
                                                    Grupo
                                                </div>
                                            @endif

                                            @if (!empty($children))
                                                <div class="pl-6 border-l border-gray-100 space-y-2">
                                                    @foreach ($children as $child)
                                                        <label class="flex items-start gap-3 text-sm text-gray-700">
                                                            @if (strtoupper((string) $child->requiere_check) === 'SI')
                                                                <input type="checkbox" name="aplica[]" value="{{ $child->id }}" class="mt-1 rounded border-gray-300"
                                                                    {{ in_array($child->id, $applied) ? 'checked' : '' }}>
                                                            @endif
                                                            <div>
                                                                <div class="font-medium">
                                                                    {{ $child->texto ?: $child->requisito }}
                                                                </div>
                                                                <div class="text-xs text-gray-500">
                                                                    Documento: {{ $child->nombre_documento }}{{ $child->codigo_interno ? ' | Código: ' . $child->codigo_interno : '' }}
                                                                </div>
                                                            </div>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                            </details>
                        @endif
                    @endforeach

                    <div class="flex justify-end">
                        <button class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700" type="submit">
                            Guardar checklist
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
