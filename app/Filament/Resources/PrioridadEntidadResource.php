<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PrioridadEntidadResource\Pages;
use App\Models\PrioridadEntidad;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class PrioridadEntidadResource extends Resource
{
    protected static ?string $model = PrioridadEntidad::class;
    protected static ?string $navigationIcon = 'heroicon-o-flag';
    protected static ?string $navigationGroup = 'Parametrizacion';
    protected static ?string $pluralModelLabel = 'Prioridades entidad';
    protected static ?int $navigationSort = 12;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('numero')->numeric()->required()->minValue(1)->maxValue(99)->unique(ignoreRecord: true),
            TextInput::make('nombre')->required()->maxLength(255),
            ColorPicker::make('color_picker')
                ->label('Selector visual')
                ->hex()
                ->dehydrated(false)
                ->afterStateHydrated(function (Set $set, ?PrioridadEntidad $record): void {
                    $set('color_picker', $record?->color_hex ?: $record?->resolved_color_hex);
                })
                ->afterStateUpdated(function (Set $set, ?string $state): void {
                    if (blank($state)) {
                        return;
                    }

                    $set('color_hex', strtoupper($state));
                }),
            TextInput::make('color_hex')
                ->label('Color HEX')
                ->placeholder('#8FD400')
                ->live(onBlur: true)
                ->dehydrateStateUsing(function (?string $state): ?string {
                    $value = strtoupper(trim((string) $state));

                    if ($value === '') {
                        return null;
                    }

                    if (!str_starts_with($value, '#')) {
                        $value = '#' . $value;
                    }

                    return $value;
                })
                ->afterStateUpdated(function (Set $set, ?string $state): void {
                    $value = strtoupper(trim((string) $state));

                    if ($value === '') {
                        $set('color_picker', null);

                        return;
                    }

                    if (!str_starts_with($value, '#')) {
                        $value = '#' . $value;
                    }

                    if (preg_match('/^#[0-9A-F]{6}$/', $value)) {
                        $set('color_picker', $value);
                    }
                })
                ->rule('regex:/^#?[0-9A-Fa-f]{6}$/')
                ->helperText('Puedes pegar un valor como #8FD400 o elegirlo visualmente.'),
            Placeholder::make('color_preview')
                ->label('Vista previa')
                ->content(function (Get $get): HtmlString {
                    $numero = (int) ($get('numero') ?: 0);
                    $nombre = (string) ($get('nombre') ?: 'Prioridad');
                    $color = strtoupper(trim((string) $get('color_hex')));

                    if ($color !== '' && !str_starts_with($color, '#')) {
                        $color = '#' . $color;
                    }

                    if (!preg_match('/^#[0-9A-F]{6}$/', $color)) {
                        $color = null;
                    }

                    $preview = new PrioridadEntidad([
                        'numero' => $numero,
                        'nombre' => $nombre,
                        'color_hex' => $color,
                    ]);

                    return new HtmlString(sprintf(
                        '<span style="%s">%s</span>',
                        $preview->badgeStyle(),
                        e($preview->badgeLabel())
                    ));
                }),
            Toggle::make('activo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('numero')->sortable(),
            TextColumn::make('nombre')->searchable(),
            TextColumn::make('color_hex')
                ->label('Color')
                ->html()
                ->formatStateUsing(function (?string $state, PrioridadEntidad $record): HtmlString {
                    $color = $record->resolved_color_hex;

                    return new HtmlString(sprintf(
                        '<span style="display:inline-flex;align-items:center;gap:8px;"><span style="width:18px;height:18px;border-radius:9999px;background:%1$s;border:1px solid %1$s;display:inline-block;"></span><span>%2$s</span></span>',
                        e($color),
                        e($color)
                    ));
                }),
            IconColumn::make('activo')->boolean(),
        ])->defaultSort('numero')
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrioridadEntidads::route('/'),
            'create' => Pages\CreatePrioridadEntidad::route('/create'),
            'edit' => Pages\EditPrioridadEntidad::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $u = auth()->user();
        return (bool) ($u && $u->canManageDirectorCatalogs());
    }
    public static function canCreate(): bool { return static::canViewAny(); }
    public static function canEdit($record): bool { return static::canViewAny(); }
    public static function canDelete($record): bool { return static::canViewAny(); }
}
