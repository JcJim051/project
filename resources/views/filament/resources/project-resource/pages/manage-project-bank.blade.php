<x-filament-panels::page>
    <div class="space-y-4 max-w-none">
        @if ($errors->has('bank_excel'))
            <div id="bank-error-box" class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
                {{ $errors->first('bank_excel') }}
            </div>
        @endif

        @if (session('status'))
            <div id="bank-status-box" class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Documentos Banco (Excel)</h3>
                    <p class="text-xs text-gray-500">Genera F-PE-23, F-PE-24 y F-PE-25 con los datos de la ficha.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('projects.bank.download.excel', [$project, 'bank_plan_inversion']) }}" class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700">Descargar F-PE-23</a>
                    <a href="{{ route('projects.bank.download.excel', [$project, 'bank_plan_desarrollo']) }}" class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700">Descargar F-PE-24</a>
                    <a href="{{ route('projects.bank.download.excel', [$project, 'bank_cronograma']) }}" class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700">Descargar F-PE-25</a>
                    <a href="{{ route('projects.bank.download.zip', $project) }}" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700">Descargar ZIP</a>
                </div>
            </div>
            @if (!empty($missingRequired))
                <div class="mt-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                    <div class="font-semibold">Faltan datos obligatorios para generar:</div>
                    <ul class="mt-1 list-disc pl-5">
                        @foreach ($missingRequired as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <h4 class="text-sm font-semibold text-gray-800">Perfil Banco</h4>
            <p class="mt-2 text-xs text-gray-600">
                Esta sección se diligencia desde Crear/Editar proyecto. Aquí solo gestionas firmantes, cadena de valor y descargas Excel.
            </p>
            <a href="{{ \App\Filament\Resources\ProjectResource::getUrl('edit', ['record' => $project]) }}" class="mt-3 inline-flex rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700">
                Ir a editar proyecto
            </a>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <h4 class="text-sm font-semibold text-gray-800">Firmantes</h4>
            <form method="POST" action="{{ route('projects.bank.signatories.update', $project) }}" class="mt-3 space-y-2">
                @csrf
                @method('PUT')
                <div class="overflow-x-auto">
                    <table class="min-w-[1200px] w-full text-xs">
                        <thead>
                            <tr class="text-left text-gray-600">
                                <th class="p-2">Rol</th><th class="p-2">Nombre</th><th class="p-2">Cargo</th><th class="p-2">Correo</th><th class="p-2">Teléfono</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($signatories as $i => $row)
                                <tr class="border-t border-gray-100">
                                    <td class="p-1">
                                        <input type="hidden" name="rows[{{ $i }}][role]" value="{{ $row->role }}">
                                        <span class="inline-flex rounded-md border border-gray-200 bg-gray-50 px-2 py-1 text-xs text-gray-700">
                                            {{ match($row->role) { 'formulador_oficial' => 'Nombre/Cargo base', 'elaboro' => 'Elaboró', 'aprobo' => 'Aprobó', default => $row->role } }}
                                        </span>
                                    </td>
                                    <td class="p-1"><input name="rows[{{ $i }}][nombre]" value="{{ $row->nombre }}" class="w-56 rounded-md border-gray-300 text-xs"></td>
                                    <td class="p-1"><input name="rows[{{ $i }}][cargo]" value="{{ $row->cargo }}" class="w-64 rounded-md border-gray-300 text-xs"></td>
                                    <td class="p-1"><input name="rows[{{ $i }}][correo]" value="{{ $row->correo }}" class="w-64 rounded-md border-gray-300 text-xs"></td>
                                    <td class="p-1"><input name="rows[{{ $i }}][telefono]" value="{{ $row->telefono }}" class="w-36 rounded-md border-gray-300 text-xs"></td>
                                    <input type="hidden" name="rows[{{ $i }}][orden]" value="{{ $row->orden }}">
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Guardar firmantes</button>
            </form>
        </div>

        <div
            class="rounded-lg border border-gray-200 bg-white p-4"
            x-data="chainValueEditor({
                rows: @js($activityRows->map(fn($row) => [
                    'orden' => (int) $row->orden,
                    'actividad' => (string) ($row->actividad ?? ''),
                    'valor_actividad' => $row->valor_actividad,
                    'producto_mga' => (string) ($row->producto_mga ?? ''),
                    'ene' => (bool) $row->ene,
                    'feb' => (bool) $row->feb,
                    'mar' => (bool) $row->mar,
                    'abr' => (bool) $row->abr,
                    'may' => (bool) $row->may,
                    'jun' => (bool) $row->jun,
                    'jul' => (bool) $row->jul,
                    'ago' => (bool) $row->ago,
                    'sep' => (bool) $row->sep,
                    'oct' => (bool) $row->oct,
                    'nov' => (bool) $row->nov,
                    'dic' => (bool) $row->dic,
                ])->values()->all()),
                productoMga: @js((string) ($project->producto?->nombre_con_codigo ?? '')),
            })"
            x-init="init()"
        >
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h4 class="text-sm font-semibold text-gray-800">Cadena de valor (actividades)</h4>
                    <p class="text-xs text-gray-500">Agrega aquí N actividades con su valor. Estas mismas filas alimentan F-PE-23, F-PE-24 y F-PE-25.</p>
                </div>
                <button type="button" @click="addRow()" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">
                    + Agregar actividad
                </button>
            </div>
            <div class="mt-2">
                <button type="submit" form="activities-bank-form" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Guardar cronograma</button>
            </div>
            <form id="activities-bank-form" method="POST" action="{{ route('projects.bank.activities.update', $project) }}" class="mt-3 space-y-2">
                @csrf
                @method('PUT')
                <div class="overflow-x-hidden">
                    <table class="w-full table-fixed text-xs">
                        <colgroup>
                            <col class="w-10">
                            <col class="w-[34%]">
                            <col class="w-24">
                            <col span="12" class="w-10">
                            <col class="w-20">
                        </colgroup>
                        <thead>
                            <tr class="text-left text-gray-600">
                                <th class="p-2">#</th><th class="p-2">Actividad</th><th class="p-2">Valor</th><th class="p-2">Ene</th><th class="p-2">Feb</th><th class="p-2">Mar</th><th class="p-2">Abr</th><th class="p-2">May</th><th class="p-2">Jun</th><th class="p-2">Jul</th><th class="p-2">Ago</th><th class="p-2">Sep</th><th class="p-2">Oct</th><th class="p-2">Nov</th><th class="p-2">Dic</th><th class="p-2">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, i) in rows" :key="row.uid">
                                <tr class="border-t border-gray-100 align-top">
                                    <td class="p-1">
                                        <input type="hidden" :name="`rows[${i}][orden]`" :value="row.orden">
                                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-200 bg-gray-50 text-xs text-gray-700" x-text="row.orden"></span>
                                    </td>
                                    <td class="p-1">
                                        <textarea :name="`rows[${i}][actividad]`" x-model="row.actividad" rows="2" class="w-full rounded-md border-gray-300 text-xs"></textarea>
                                        <input type="hidden" :name="`rows[${i}][producto_mga]`" :value="row.producto_mga || productoMga">
                                    </td>
                                    <td class="p-1">
                                        <input :name="`rows[${i}][valor_actividad]`" x-model="row.valor_actividad" class="w-full rounded-md border-gray-300 text-xs">
                                    </td>
                                    <template x-for="m in months" :key="`${row.uid}-${m}`">
                                        <td class="p-1 text-center">
                                            <input type="checkbox" :name="`rows[${i}][${m}]`" value="1" x-model="row[m]">
                                        </td>
                                    </template>
                                    <td class="p-1">
                                        <button type="button" @click="removeRow(i)" class="rounded-md border border-rose-200 bg-rose-50 px-2 py-1 text-[11px] font-semibold text-rose-700">
                                            Eliminar
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="rows.length === 0">
                                <td colspan="16" class="p-3 text-center text-xs text-gray-500">
                                    No hay actividades. Usa "Agregar actividad" para crear la cadena de valor.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-500">Tip: puedes dejar meses sin marcar y luego ajustar solo el cronograma cuando lo necesites.</p>
                <button type="submit" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Guardar cronograma</button>
            </form>
        </div>
    </div>

    <script>
        function chainValueEditor(payload) {
            return {
                rows: payload.rows || [],
                productoMga: payload.productoMga || '',
                months: ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'],
                init() {
                    this.rows = (this.rows || []).map((row, idx) => ({ ...row, uid: this.makeUid(), orden: row.orden || (idx + 1) }));
                },
                addRow() {
                    this.rows.push({
                        uid: this.makeUid(),
                        orden: this.rows.length + 1,
                        actividad: '',
                        valor_actividad: '',
                        producto_mga: this.productoMga,
                        ene: false, feb: false, mar: false, abr: false, may: false, jun: false,
                        jul: false, ago: false, sep: false, oct: false, nov: false, dic: false,
                    });
                },
                removeRow(index) {
                    this.rows.splice(index, 1);
                    this.rows.forEach((row, idx) => { row.orden = idx + 1; });
                },
                makeUid() {
                    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                        return window.crypto.randomUUID();
                    }
                    return `row_${Date.now()}_${Math.floor(Math.random() * 100000)}`;
                },
            };
        }
    </script>
</x-filament-panels::page>
