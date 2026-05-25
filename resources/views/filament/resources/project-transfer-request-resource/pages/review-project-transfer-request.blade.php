<x-filament-panels::page>
    <div
        x-data="transferReviewApp({
            groups: @js($reviewGroups),
            saveUrl: @js(route('project-transfer-requests.comments', $transferRequest)),
            csrf: @js(csrf_token())
        })"
        class="space-y-4"
    >
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Ficha previa de revisión interna (MGA)</h2>
                    <p class="text-sm text-gray-600 mt-1">
                        Proyecto: <span class="font-semibold">{{ $project->nombre_clave ?: $project->nombre }}</span>
                        · Estado: <span class="font-semibold uppercase">{{ $transferRequest->status }}</span>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    @if ($transferRequest->status === 'pending')
                        <form method="POST" action="{{ route('project-transfer-requests.decide', ['transferRequest' => $transferRequest, 'decision' => 'approve']) }}">
                            @csrf
                            <input type="hidden" name="decision_note" value="Aprobado tras revisión interna por requisito.">
                            <button type="submit" class="inline-flex h-10 items-center rounded-md px-4 text-sm font-semibold"
                                    style="background:#16a34a;color:#ffffff;border:1px solid #15803d;">
                                Aceptar
                            </button>
                        </form>
                        <form method="POST" action="{{ route('project-transfer-requests.decide', ['transferRequest' => $transferRequest, 'decision' => 'reject']) }}">
                            @csrf
                            <input type="hidden" name="decision_note" value="Rechazado tras revisión interna por requisito.">
                            <button type="submit" class="inline-flex h-10 items-center rounded-md px-4 text-sm font-semibold"
                                    style="background:#dc2626;color:#ffffff;border:1px solid #b91c1c;">
                                Rechazar
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('filament.admin.resources.project-transfer-requests.index') }}" class="inline-flex h-9 items-center rounded-md border border-gray-300 px-3 text-sm text-gray-700 hover:bg-gray-50">
                        Volver
                    </a>
                </div>
            </div>
            <div x-show="flash.message" x-transition class="mt-3 rounded-md border px-3 py-2 text-sm"
                 :class="flash.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700'"
                 x-text="flash.message"></div>
        </div>

        <div style="display:grid; grid-template-columns:40% 60%; gap:1rem; align-items:start;">
            <section class="rounded-xl border border-gray-200 bg-white p-4 overflow-y-auto" style="min-width:0; height:84vh;">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Requisitos (colapsado por grupo)</h3>
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
                                            <div class="rounded-md border" :class="selectedRequirement && selectedRequirement.id === req.id ? 'border-primary-400 bg-primary-50/40' : 'border-gray-200'">
                                                <button type="button" @click="selectRequirement(req.id)" class="w-full text-left rounded-md px-2 py-2 text-xs"
                                                        :class="selectedRequirement && selectedRequirement.id === req.id ? 'text-primary-800' : 'hover:bg-gray-50 text-gray-700'">
                                                    <div class="font-medium" x-text="req.title"></div>
                                                </button>

                                                <div x-show="selectedRequirement && selectedRequirement.id === req.id" x-transition class="px-2 pb-2 space-y-2">
                                                    <label class="text-[11px] font-semibold text-gray-600">Comentario del requisito</label>
                                                    <textarea
                                                        rows="4"
                                                        class="w-full rounded-md border border-gray-300 px-2 py-1 text-xs text-gray-700"
                                                        x-model="req.comment"
                                                        placeholder="Redacta observación, ajuste o hallazgo..."
                                                    ></textarea>
                                                    <template x-if="(selectedRequirement && selectedRequirement.id === req.id) && (selectedRequirement.previous_comment || selectedRequirement.comment)">
                                                        <div class="rounded-md border border-amber-200 bg-amber-50 px-2 py-2">
                                                            <div class="text-[11px] font-semibold text-amber-800">Último comentario registrado</div>
                                                            <div class="text-[11px] text-amber-800 mt-1" x-text="selectedRequirement.previous_comment || selectedRequirement.comment"></div>
                                                            <div class="text-[10px] text-amber-700 mt-1" x-text="(selectedRequirement.previous_author ? selectedRequirement.previous_author + ' · ' : '') + (selectedRequirement.previous_date || '')"></div>
                                                        </div>
                                                    </template>
                                                    <template x-if="(selectedRequirement && selectedRequirement.id === req.id) && !(selectedRequirement.previous_comment || selectedRequirement.comment)">
                                                        <div class="rounded-md border border-gray-200 bg-gray-50 px-2 py-2 text-[11px] text-gray-500">
                                                            Sin comentario previo para este requisito.
                                                        </div>
                                                    </template>
                                                    <div class="flex items-center gap-2">
                                                        <button type="button" @click="goNext()" :disabled="saving" class="inline-flex h-8 items-center rounded-md border border-primary-400 bg-primary-600 px-3 text-xs font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                                                            <span x-text="saving ? 'Guardando...' : 'Guardar'"></span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </details>
                </template>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-4 space-y-3 overflow-y-auto" style="min-width:0; height:84vh;">
                <template x-if="selectedRequirement">
                    <div class="space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900" x-text="selectedRequirement.title"></h3>
                                <p class="text-xs text-gray-500" x-text="selectedRequirement.folder"></p>
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 p-3">
                            <div class="text-xs font-semibold text-gray-700 mb-2">Visualización PDF</div>
                            <template x-if="selectedRequirement.evidences.length === 0">
                                <div class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-2 py-2">Sin evidencias para visualizar.</div>
                            </template>
                            <template x-if="selectedRequirement.evidences.length > 0">
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
        function transferReviewApp(config) {
            return {
                groups: config.groups || [],
                saveUrl: config.saveUrl,
                csrf: config.csrf,
                flat: [],
                selectedRequirement: null,
                selectedEvidenceId: null,
                previewUrl: null,
                viewUrl: null,
                saving: false,
                flash: { message: '', ok: true },
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
                async saveCurrent() {
                    if (!this.selectedRequirement) return;
                    this.saving = true;
                    this.flash = { message: '', ok: true };
                    try {
                        const payload = { comments: {} };
                        payload.comments[this.selectedRequirement.id] = this.selectedRequirement.comment || '';
                        const res = await fetch(this.saveUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify(payload),
                        });
                        const data = await res.json();
                        if (!res.ok || !data.ok) throw new Error(data.message || 'No se pudo guardar.');
                        this.flash = { message: data.message || 'Guardado.', ok: true };
                    } catch (e) {
                        this.flash = { message: e.message || 'Error guardando comentario.', ok: false };
                    } finally {
                        this.saving = false;
                    }
                },
                async goNext() {
                    if (!this.selectedRequirement) return;
                    await this.saveCurrent();
                    const idx = this.flat.findIndex(r => Number(r.id) === Number(this.selectedRequirement.id));
                    if (idx < 0) return;
                    const next = this.flat[(idx + 1) % this.flat.length];
                    this.selectRequirement(next.id);
                },
            }
        }
    </script>
</x-filament-panels::page>
