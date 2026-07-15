<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="font-semibold text-gray-700">Estado Plane:</span>
                @if($isConfigured)
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700">Configurado</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-1 text-xs font-medium text-rose-700">Sin configurar</span>
                @endif

                @if($connectionStatus === 'connected')
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700">Conectado</span>
                @elseif($connectionStatus === 'auth_error')
                    <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-1 text-xs font-medium text-rose-700">Error autenticación</span>
                @elseif($connectionStatus === 'network_error')
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-700">Error de red</span>
                @elseif($connectionStatus === 'http_error')
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-700">Error HTTP</span>
                @elseif($connectionStatus === 'missing_connection')
                    <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-1 text-xs font-medium text-rose-700">Configuración incompleta</span>
                @endif
            </div>
            @if($connectionMessage)
                <p class="mt-2 text-xs text-gray-500">{{ $connectionMessage }}</p>
            @endif
        </div>

        <p class="text-sm text-gray-600">
            Configura aquí la conexión backend hacia Plane. Orbit usará esta conexión para provisionar la capa operativa de los proyectos nuevos.
        </p>

        <form wire:submit="save" class="space-y-4">
            {{ $this->form }}

            <div class="flex flex-wrap gap-3">
                <x-filament::button type="submit">
                    Guardar
                </x-filament::button>
                <x-filament::button color="success" type="button" wire:click="saveAndTest">
                    Guardar y probar
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
