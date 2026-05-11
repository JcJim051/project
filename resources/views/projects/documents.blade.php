<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Generar certificaciones</h2>
                <p class="text-sm text-gray-500">Proyecto: {{ $project->nombre }}</p>
            </div>
            <x-filament::button tag="a" color="gray" href="{{ route('projects.manage', $project) }}" icon="heroicon-o-arrow-left">
                Volver a gestionar
            </x-filament::button>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

        @if (session('status'))
            <x-filament::section>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    {{ session('status') }}
                </div>
            </x-filament::section>
        @endif

        @if ($errors->any())
            <x-filament::section>
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    {{ $errors->first() }}
                </div>
            </x-filament::section>
        @endif

        <x-filament::section>
            <x-slot name="heading">Resumen del proyecto</x-slot>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Objeto</div>
                    <div class="mt-1 text-sm font-semibold text-gray-800">{{ $project->objeto_proyecto }}</div>
                </div>
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">BIPIN</div>
                    <div class="mt-1 text-sm font-semibold text-gray-800">{{ $project->bipin ?: 'Sin BIPIN' }}</div>
                </div>
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Fecha</div>
                    <div class="mt-1 text-sm font-semibold text-gray-800">{{ now()->format('d/m/Y') }}</div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Certificaciones disponibles</x-slot>
            <x-slot name="description">Solo se muestran las certificaciones marcadas en requisitos.</x-slot>

            @if ($templates->isEmpty())
                <p class="text-sm text-gray-500">No hay certificaciones disponibles para este proyecto.</p>
            @else
                <form method="POST" action="{{ route('projects.documents.zip', $project) }}" class="space-y-4">
                    @csrf

                    <div class="space-y-2">
                        @foreach ($templates as $template)
                            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3">
                                <label class="flex items-center gap-3 text-sm font-medium text-gray-700">
                                    <input type="checkbox" name="templates[]" value="{{ $template->id }}" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                    {{ $template->nombre }}
                                </label>
                                <x-filament::button
                                    tag="a"
                                    size="xs"
                                    color="gray"
                                    href="{{ route('projects.documents.download', [$project, $template]) }}"
                                    icon="heroicon-o-arrow-down-tray"
                                >
                                    Descargar
                                </x-filament::button>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex justify-end">
                        <x-filament::button type="submit" icon="heroicon-o-archive-box-arrow-down">
                            Descargar seleccionados (ZIP)
                        </x-filament::button>
                    </div>
                </form>
            @endif
        </x-filament::section>
        </div>
    </div>
</x-app-layout>
