<x-filament-panels::page>
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
                    @if ($section['type'] === 'budget')
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
