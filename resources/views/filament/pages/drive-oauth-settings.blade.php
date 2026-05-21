<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="font-semibold text-gray-700">Estado OAuth:</span>
                @if($isDriveConfigured)
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700">Configurado</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-1 text-xs font-medium text-rose-700">Sin configurar</span>
                @endif

                @if($isDriveConnected)
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700">Conectado</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-700">No conectado</span>
                @endif
            </div>
            @if($tokenUpdatedAt)
                <p class="mt-2 text-xs text-gray-500">Última conexión detectada: {{ $tokenUpdatedAt }}</p>
            @endif
        </div>

        <p class="text-sm text-gray-600">
            Configura la app OAuth de Google Drive desde el panel. Al guardar, se limpian los tokens para forzar reconexion.
        </p>

        <form wire:submit="save" class="space-y-4">
            {{ $this->form }}

            <div>
                <x-filament::button type="submit">
                    Guardar credenciales
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
