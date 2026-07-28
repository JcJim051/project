<x-filament-panels::page>
    <style>
        .bank-page {
            display: flex;
            width: 100%;
            max-width: none;
            flex-direction: column;
            gap: 18px;
        }
        .bank-hero {
            overflow: hidden;
            border: 1px solid #d1fae5;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 8px 24px rgb(15 23 42 / 6%);
        }
        .bank-hero-head {
            padding: 22px 24px;
            color: #fff;
            background: linear-gradient(120deg, #065f46 0%, #047857 55%, #0f766e 100%);
        }
        .bank-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding: 24px;
            background: #f8fafc;
        }
        .bank-form-section,
        .bank-secondary-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
            padding: 18px;
            box-shadow: 0 2px 8px rgb(15 23 42 / 4%);
        }
        .bank-section-title {
            margin-bottom: 12px;
            color: #0f172a;
            font-size: 14px;
            font-weight: 700;
        }
        .bank-page input:not([type="checkbox"]):not([type="hidden"]),
        .bank-page select,
        .bank-page textarea {
            width: 100%;
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            background: #fff !important;
            padding: 8px 10px !important;
            color: #1e293b;
            box-shadow: 0 1px 2px rgb(15 23 42 / 4%);
        }
        .bank-page input:focus,
        .bank-page select:focus,
        .bank-page textarea:focus {
            border-color: #10b981 !important;
            outline: 2px solid rgb(16 185 129 / 15%);
            outline-offset: 0;
        }
        .bank-page input[type="checkbox"] {
            border-color: #94a3b8;
            color: #059669;
        }
        .bank-button {
            display: inline-flex;
            min-height: 34px;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: 1px solid transparent;
            border-radius: 8px;
            padding: 7px 13px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.2;
            text-decoration: none;
            transition: background 150ms ease, border-color 150ms ease, box-shadow 150ms ease;
        }
        .bank-button-primary {
            border-color: #047857;
            background: #047857;
            color: #fff !important;
            box-shadow: 0 3px 8px rgb(4 120 87 / 18%);
        }
        .bank-button-primary:hover {
            background: #065f46;
        }
        .bank-button-secondary {
            border-color: #cbd5e1;
            background: #fff;
            color: #334155 !important;
        }
        .bank-button-secondary:hover {
            background: #f1f5f9;
        }
        .bank-button-accent {
            border-color: #a7f3d0;
            background: #ecfdf5;
            color: #047857 !important;
        }
        .bank-button-accent:hover {
            background: #d1fae5;
        }
        .bank-button-danger {
            border-color: #fecdd3;
            background: #fff1f2;
            color: #be123c !important;
        }
        .bank-button-danger:hover {
            background: #ffe4e6;
        }
        .bank-table-shell {
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }
        .bank-page table {
            background: #fff;
        }
        .bank-page thead {
            background: #f1f5f9;
        }
        .bank-page th {
            color: #475569;
            font-weight: 700;
        }
        .bank-page tbody tr {
            border-top: 1px solid #e2e8f0;
        }
        @media (max-width: 767px) {
            .bank-hero-head,
            .bank-form,
            .bank-form-section,
            .bank-secondary-card {
                padding: 14px;
            }
        }
    </style>

    <div class="bank-page">
        @php($bankPageMode = $bankPageMode ?? 'structure')

        @if ($bankPageMode === 'structure' && $errors->has('bank_excel'))
            <div id="bank-error-box" class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
                {{ $errors->first('bank_excel') }}
            </div>
        @endif
        @if ($bankPageMode === 'request' && $errors->has('bank_request'))
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
                {{ $errors->first('bank_request') }}
            </div>
        @endif

        @if (session('status'))
            <div id="bank-status-box" class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if ($bankPageMode === 'request')
        <section class="bank-hero">
            <div class="bank-hero-head">
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-emerald-100">Banco de Programas y Proyectos</p>
                <div class="mt-1 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h3 class="text-xl font-semibold">Solicitud automática F-BS-01</h3>
                        <p class="mt-1 text-sm text-emerald-50">Genera solicitudes editables de obra, interventoría o apoyo usando siempre el ID del proyecto.</p>
                    </div>
                    <span class="rounded-full border border-white/30 bg-white/10 px-3 py-1 text-xs font-semibold">
                        Plantilla {{ $bankRequestTemplate?->version ? 'V'.$bankRequestTemplate->version : 'oficial V07' }}
                    </span>
                </div>
            </div>

            <form method="POST" action="{{ route('projects.bank.requests.store', $project) }}" class="bank-form">
                @csrf
                <div class="bank-form-section">
                    <h4 class="bank-section-title">Configuración de la solicitud</h4>
                    <div class="grid gap-4 md:grid-cols-4">
                        <label class="text-xs font-semibold text-gray-700">Modalidad
                            <select name="variant" class="mt-1 text-sm" required>
                                <option value="obra" @selected(old('variant') === 'obra')>Obra</option>
                                <option value="inter" @selected(old('variant') === 'inter')>Interventoría</option>
                                <option value="apoyo" @selected(old('variant') === 'apoyo')>Apoyo a la supervisión</option>
                            </select>
                        </label>
                        <label class="text-xs font-semibold text-gray-700">Tipo de generación
                            <select name="generation_type" class="mt-1 text-sm" required>
                                <option value="initial">Inicial</option>
                                <option value="update" @selected(old('generation_type') === 'update')>Actualización</option>
                            </select>
                        </label>
                        <label class="text-xs font-semibold text-gray-700">Fecha
                            <input type="date" name="request_date" value="{{ old('request_date', now()->toDateString()) }}" class="mt-1 text-sm" required>
                        </label>
                        <label class="text-xs font-semibold text-gray-700">Valor a certificar
                            <input type="number" step="0.01" min="0" name="value_to_certify" value="{{ old('value_to_certify', $project->valor) }}" class="mt-1 text-sm" required>
                        </label>
                    </div>
                    <label class="mt-4 block text-xs font-semibold text-gray-700">Motivo de actualización
                        <textarea name="update_reason" rows="2" class="mt-1 text-sm" placeholder="Obligatorio únicamente para actualizaciones">{{ old('update_reason') }}</textarea>
                    </label>
                </div>

                <div class="bank-form-section">
                    <h4 class="bank-section-title">Destinatario y asunto</h4>
                    <div class="mt-3 grid gap-3 md:grid-cols-4">
                        <input name="recipient_salutation" value="{{ old('recipient_salutation', 'Doctora') }}" placeholder="Tratamiento" class="rounded-md border-gray-300 text-sm">
                        <input name="recipient_name" value="{{ old('recipient_name', 'JEIMMY LISSED MOLANO MORENO') }}" placeholder="Nombre" class="rounded-md border-gray-300 text-sm" required>
                        <input name="recipient_title" value="{{ old('recipient_title', 'Gerente de Inversión Pública y Banco de Proyectos') }}" placeholder="Cargo" class="rounded-md border-gray-300 text-sm" required>
                        <input name="recipient_entity" value="{{ old('recipient_entity', 'Gobernación del Meta') }}" placeholder="Entidad" class="rounded-md border-gray-300 text-sm" required>
                    </div>
                    <input name="subject" value="{{ old('subject', 'Solicitud de Certificado de Banco de Programas y Proyectos de inversión Departamental para ejecución de recursos') }}" class="mt-3 w-full rounded-md border-gray-300 text-sm" required>
                </div>

                <div class="bank-form-section">
                    <h4 class="bank-section-title">Objeto y población beneficiaria</h4>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="text-xs font-semibold text-gray-700">Objeto del gasto
                            <textarea name="expense_object" rows="4" class="mt-1 text-sm" required>{{ old('expense_object', $project->objeto_proyecto) }}</textarea>
                        </label>
                        <label class="text-xs font-semibold text-gray-700">Descripción de beneficiarios
                            <textarea name="beneficiary_description" rows="4" class="mt-1 text-sm" required>{{ old('beneficiary_description') }}</textarea>
                        </label>
                    </div>
                    <div class="mt-4 grid gap-3 md:grid-cols-4">
                        <label class="text-xs font-semibold text-gray-700">Total beneficiarios
                            <input type="number" min="0" name="beneficiaries_total" value="{{ old('beneficiaries_total', $project->poblacion_objetivo ?? 0) }}" class="mt-1 text-sm" required>
                        </label>
                        <label class="text-xs font-semibold text-gray-700">Rurales
                            <input type="number" min="0" name="beneficiaries_rural" value="{{ old('beneficiaries_rural', 0) }}" class="mt-1 text-sm" required>
                        </label>
                        <label class="text-xs font-semibold text-gray-700">Urbanos
                            <input type="number" min="0" name="beneficiaries_urban" value="{{ old('beneficiaries_urban', 0) }}" class="mt-1 text-sm" required>
                        </label>
                        <label class="text-xs font-semibold text-gray-700">Trazador presupuestal
                            <select name="budget_tracer" class="mt-1 text-sm" required>
                                <option value="no_aplica">No aplica</option>
                                <option value="narp">NARP</option>
                                <option value="indigenas">Indígenas</option>
                                <option value="mujer">Equidad de la mujer</option>
                            </select>
                        </label>
                    </div>
                    <label class="mt-4 block text-xs font-semibold text-gray-700">Otros resultados
                        <textarea name="other_results" rows="3" class="mt-1 text-sm" required>{{ old('other_results') }}</textarea>
                    </label>
                </div>

                @php
                    $ageRows = ['0 a 14 años', '15 a 19 años', '20 a 59 años', 'Mayor de 60 años'];
                    $diffColumns = [
                        'men' => 'Hombres', 'women' => 'Mujeres', 'lgbti' => 'LGTBI',
                        'victim' => 'Víctimas', 'disability' => 'Discapacidad', 'afro' => 'Afro',
                        'indigenous' => 'Indígena', 'extreme_poverty' => 'Pobreza extrema', 'other' => 'Otro',
                    ];
                @endphp
                <div class="bank-form-section">
                    <h4 class="bank-section-title">Beneficiarios con enfoque diferencial</h4>
                    <div class="bank-table-shell">
                        <table class="min-w-[1000px] w-full text-xs">
                            <thead><tr class="bg-gray-50 text-left text-gray-600"><th class="p-2">Grupo</th>@foreach($diffColumns as $label)<th class="p-2">{{ $label }}</th>@endforeach</tr></thead>
                            <tbody>
                                @foreach($ageRows as $rowIndex => $ageLabel)
                                    <tr class="border-t border-gray-100">
                                        <td class="p-2 font-semibold text-gray-700">{{ $ageLabel }}</td>
                                        @foreach($diffColumns as $key => $label)
                                            <td class="p-1"><input type="number" min="0" name="differential[{{ $rowIndex }}][{{ $key }}]" value="{{ old("differential.$rowIndex.$key", 0) }}" class="w-20 rounded-md border-gray-300 text-xs"></td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bank-form-section">
                    <h4 class="bank-section-title">Justificación y criterios técnicos</h4>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="text-xs font-semibold text-gray-700">Pertinencia
                            <textarea name="pertinence" rows="5" class="mt-1 text-sm" required>{{ old('pertinence') }}</textarea>
                        </label>
                        <label class="text-xs font-semibold text-gray-700">Correspondencia con el marco legal
                            <textarea name="legal_framework" rows="5" class="mt-1 text-sm" required>{{ old('legal_framework') }}</textarea>
                        </label>
                        <label class="text-xs font-semibold text-gray-700">Estudio de precios de mercado
                            <textarea name="market_study" rows="5" class="mt-1 text-sm" required>{{ old('market_study') }}</textarea>
                        </label>
                        <label class="text-xs font-semibold text-gray-700">Observaciones
                            <textarea name="observations" rows="5" class="mt-1 text-sm">{{ old('observations') }}</textarea>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button class="bank-button bank-button-primary">Generar y descargar XLSX</button>
                </div>
            </form>
        </section>

        @if($bankRequests->isNotEmpty())
            <section class="bank-secondary-card">
                <h4 class="bank-section-title">Historial de solicitudes F-BS-01</h4>
                <div class="bank-table-shell">
                    <table class="min-w-[780px] w-full text-xs">
                        <thead><tr class="text-left text-gray-500"><th class="p-2">Modalidad</th><th class="p-2">Versión</th><th class="p-2">Tipo</th><th class="p-2">Generada</th><th class="p-2">Usuario</th><th class="p-2">Plantilla</th><th class="p-2">Archivo</th></tr></thead>
                        <tbody>
                            @foreach($bankRequests as $requestRow)
                                <tr class="border-t border-gray-100">
                                    <td class="p-2 font-semibold uppercase">{{ $requestRow->variant }}</td>
                                    <td class="p-2">V{{ $requestRow->version_number }}</td>
                                    <td class="p-2">{{ $requestRow->generation_type === 'update' ? 'Actualización' : 'Inicial' }}</td>
                                    <td class="p-2">{{ optional($requestRow->generated_at)->format('d/m/Y H:i') }}</td>
                                    <td class="p-2">{{ $requestRow->createdBy?->name ?: '-' }}</td>
                                    <td class="p-2">{{ $requestRow->template?->version ?: 'V07 base' }}</td>
                                    <td class="p-2">
                                        @if($requestRow->drive_file_id)
                                            <a href="{{ route('projects.bank.requests.download', [$project, $requestRow]) }}" class="bank-button bank-button-secondary">Descargar</a>
                                        @else
                                            <span class="text-gray-400">Solo descarga inicial</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
        @endif

        @if ($bankPageMode === 'structure')
        <div class="bank-secondary-card">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Documentos Banco (Excel)</h3>
                    <p class="text-xs text-gray-500">Genera F-PE-23, F-PE-24 y F-PE-25 con los datos de la ficha.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('projects.bank.download.excel', [$project, 'bank_plan_inversion']) }}" class="bank-button bank-button-secondary">Descargar F-PE-23</a>
                    <a href="{{ route('projects.bank.download.excel', [$project, 'bank_plan_desarrollo']) }}" class="bank-button bank-button-secondary">Descargar F-PE-24</a>
                    <a href="{{ route('projects.bank.download.excel', [$project, 'bank_cronograma']) }}" class="bank-button bank-button-secondary">Descargar F-PE-25</a>
                    <a href="{{ route('projects.bank.download.zip', $project) }}" class="bank-button bank-button-accent">Descargar ZIP</a>
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

        <div class="bank-secondary-card">
            <h4 class="text-sm font-semibold text-gray-800">Perfil Banco</h4>
            <p class="mt-2 text-xs text-gray-600">
                Esta sección se diligencia desde Crear/Editar proyecto. Aquí solo gestionas firmantes, cadena de valor y descargas Excel.
            </p>
            <a href="{{ \App\Filament\Resources\ProjectResource::getUrl('edit', ['record' => $project]) }}" class="bank-button bank-button-secondary mt-3">
                Ir a editar proyecto
            </a>
        </div>

        <div class="bank-secondary-card">
            <h4 class="bank-section-title">Firmantes</h4>
            <form method="POST" action="{{ route('projects.bank.signatories.update', $project) }}" class="mt-3 space-y-2">
                @csrf
                @method('PUT')
                <div class="bank-table-shell">
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
                <button type="submit" class="bank-button bank-button-primary">Guardar firmantes</button>
            </form>
        </div>

        <div
            class="bank-secondary-card"
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
                <button type="button" @click="addRow()" class="bank-button bank-button-accent">
                    + Agregar actividad
                </button>
            </div>
            <div class="mt-2">
                <button type="submit" form="activities-bank-form" class="bank-button bank-button-primary">Guardar cronograma</button>
            </div>
            <form id="activities-bank-form" method="POST" action="{{ route('projects.bank.activities.update', $project) }}" class="mt-3 space-y-2">
                @csrf
                @method('PUT')
                <div class="bank-table-shell">
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
                                        <button type="button" @click="removeRow(i)" class="bank-button bank-button-danger">
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
                <button type="submit" class="bank-button bank-button-primary">Guardar cronograma</button>
            </form>
        </div>
        @endif
    </div>

    @if ($bankPageMode === 'structure')
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
    @endif
</x-filament-panels::page>
