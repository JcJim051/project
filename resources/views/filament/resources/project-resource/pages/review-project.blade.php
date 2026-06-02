<x-filament-panels::page>
    <div
        x-data="reviewApp({ groups: @js($reviewGroups) })"
        class="space-y-4"
    >
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Revisión documental por requisito</h2>
                    <p class="text-sm text-gray-600 mt-1">Proyecto: <span class="font-semibold">{{ $project->nombre_clave ?: $project->nombre }}</span></p>
                </div>
                <a href="{{ route('filament.admin.resources.project-portfolios.index') }}" class="inline-flex h-9 items-center rounded-md border border-gray-300 px-3 text-sm text-gray-700 hover:bg-gray-50">
                    Volver al tablero
                </a>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:40% 60%; gap:1rem; align-items:start;">
            <section class="rounded-xl border border-gray-200 bg-white p-4 overflow-y-auto" style="min-width:0; height:82vh;">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Requisitos</h3>
                <template x-for="group in groups" :key="group.code">
                    <details class="rounded-lg border border-gray-200 mb-3">
                        <summary class="cursor-pointer select-none px-3 py-2 text-sm font-semibold text-gray-800 bg-gray-50">
                            <span x-text="group.label"></span>
                        </summary>
                        <div class="p-2 space-y-2">
                            <template x-for="folder in group.folders" :key="group.code + '-' + folder.name">
                                <div class="rounded-md border border-gray-200">
                                    <div class="flex items-center justify-between px-2 py-2 bg-white">
                                        <div class="text-xs font-semibold text-gray-700" x-text="folder.name"></div>
                                        <div class="text-[11px] text-gray-500" x-text="folder.progress"></div>
                                    </div>
                                    <div class="px-2 pb-2 space-y-1">
                                        <template x-for="req in folder.items" :key="req.id">
                                            <button type="button" @click="selectRequirement(req.id)" class="w-full text-left rounded-md border px-2 py-2 text-xs"
                                                    :class="selectedRequirement && selectedRequirement.id === req.id ? 'border-primary-400 bg-primary-50 text-primary-800' : 'border-gray-200 hover:bg-gray-50 text-gray-700'">
                                                <div class="font-medium" x-text="req.title"></div>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </details>
                </template>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-4 space-y-3 overflow-y-auto" style="min-width:0; height:82vh;">
                <template x-if="selectedRequirement">
                    <div class="space-y-3">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900" x-text="selectedRequirement.title"></h3>
                            <p class="text-xs text-gray-500" x-text="selectedRequirement.folder"></p>
                        </div>

                        <div class="rounded-lg border border-gray-200 p-3">
                            <div class="text-xs font-semibold text-gray-700 mb-2">Visualización de evidencia</div>
                            <template x-if="selectedRequirement.is_composite_parent">
                                <div class="text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-md px-2 py-2">
                                    Este requisito se cumple por los documentos requeridos de
                                    <span class="font-semibold" x-text="selectedRequirement.composite_folder"></span>:
                                    <span class="font-semibold" x-text="`${selectedRequirement.composite_done || 0} de ${selectedRequirement.composite_total || 0}`"></span>.
                                </div>
                            </template>
                            <template x-if="!selectedRequirement.is_composite_parent && selectedRequirement.evidences.length === 0">
                                <div class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-2 py-2">Sin evidencias para visualizar.</div>
                            </template>
                            <template x-if="!selectedRequirement.is_composite_parent && selectedRequirement.evidences.length > 0">
                                <div class="space-y-2">
                                    <select class="w-full rounded-md border border-gray-300 px-2 py-1 text-xs" x-model="selectedEvidenceId" @change="updatePreview()">
                                        <template x-for="ev in selectedRequirement.evidences" :key="ev.id">
                                            <option :value="ev.id" x-text="ev.name"></option>
                                        </template>
                                    </select>
                                    <div class="rounded-md border border-gray-200 overflow-hidden bg-gray-50" style="height:74vh; min-height:560px;">
                                        <iframe x-show="previewUrl" :src="previewUrl" class="h-full w-full" loading="lazy"></iframe>
                                    </div>
                                    <a x-show="viewUrl" :href="viewUrl" target="_blank" class="inline-flex h-8 items-center rounded-md border border-gray-300 px-2 text-xs text-gray-700 hover:bg-gray-50">Abrir en Drive</a>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </section>
        </div>
    </div>

    <script>
        function reviewApp(config) {
            return {
                groups: config.groups || [],
                flat: [],
                selectedRequirement: null,
                selectedEvidenceId: null,
                previewUrl: null,
                viewUrl: null,
                init() {
                    this.flat = this.groups.flatMap(g => (g.folders || []).flatMap(f => f.items || []));
                    if (this.flat.length > 0) this.selectRequirement(this.flat[0].id);
                },
                selectRequirement(id) {
                    const found = this.flat.find(r => Number(r.id) === Number(id));
                    if (!found) return;
                    this.selectedRequirement = found;
                    this.selectedEvidenceId = found.evidences.length ? found.evidences[0].id : null;
                    this.updatePreview();
                },
                updatePreview() {
                    if (!this.selectedRequirement) return;
                    const ev = this.selectedRequirement.evidences.find(e => Number(e.id) === Number(this.selectedEvidenceId));
                    this.previewUrl = ev ? ev.preview_url : null;
                    this.viewUrl = ev ? ev.view_url : null;
                },
            }
        }
    </script>
</x-filament-panels::page>
