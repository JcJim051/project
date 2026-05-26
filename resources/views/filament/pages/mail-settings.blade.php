<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="font-semibold text-gray-700">Estado SMTP:</span>
                @if($isConfigured)
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700">Configurado</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-700">Pendiente</span>
                @endif
            </div>
            <p class="mt-2 text-xs text-gray-500">
                Los correos saldrán desde esta cuenta (misma cuenta corporativa usada para notificaciones oficiales).
            </p>
        </div>

        <form wire:submit="save" class="space-y-4">
            {{ $this->form }}

            <div class="flex flex-wrap gap-2">
                <x-filament::button type="submit">
                    Guardar credenciales
                </x-filament::button>
                <x-filament::button type="button" color="gray" wire:click="sendTestEmail">
                    Enviar prueba a mi correo
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>

