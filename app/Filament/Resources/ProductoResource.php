<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductoResource\Pages;
use App\Models\Producto;
use App\Models\Sector;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductoResource extends Resource
{
    protected static ?string $model = Producto::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Parametrizacion';

    protected static ?string $modelLabel = 'Producto MGA';

    protected static ?string $pluralModelLabel = 'Productos MGA';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Datos del producto')
                    ->columns(2)
                    ->schema([
                        Select::make('sector_id')
                            ->label('Sector')
                            ->options(fn (): array => Sector::query()
                                ->where('activo', true)
                                ->orderBy('codigo')
                                ->orderBy('nombre')
                                ->get()
                                ->mapWithKeys(fn (Sector $sector): array => [$sector->id => $sector->nombre_con_codigo])
                                ->all())
                            ->getOptionLabelUsing(fn ($value): ?string => Sector::find($value)?->nombre_con_codigo)
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false),
                        TextInput::make('codigo')
                            ->label('Codigo')
                            ->required()
                            ->maxLength(50),
                        TextInput::make('nombre')
                            ->label('Nombre Producto MGA')
                            ->required()
                            ->maxLength(500)
                            ->columnSpanFull(),
                        Toggle::make('activo')
                            ->label('Activo')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sector.nombre')
                    ->label('Sector')
                    ->state(fn (Producto $record): string => $record->sector?->nombre_con_codigo ?: '-')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('sector', function (Builder $sectorQuery) use ($search): void {
                            $sectorQuery->where('codigo', 'like', "%{$search}%")
                                ->orWhere('nombre', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('codigo')
                    ->label('Codigo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nombre')
                    ->label('Nombre Producto MGA')
                    ->searchable()
                    ->wrap()
                    ->limit(80),
                IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('sector_id')
                    ->label('Sector')
                    ->options(fn (): array => Sector::query()
                        ->orderBy('codigo')
                        ->orderBy('nombre')
                        ->get()
                        ->mapWithKeys(fn (Sector $sector): array => [$sector->id => $sector->nombre_con_codigo])
                        ->all()),
                TernaryFilter::make('activo')
                    ->label('Activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('desactivar')
                        ->label('Desactivar')
                        ->icon('heroicon-o-eye-slash')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['activo' => false])),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductos::route('/'),
            'create' => Pages\CreateProducto::route('/create'),
            'edit' => Pages\EditProducto::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->canManageParametrizacion());
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
