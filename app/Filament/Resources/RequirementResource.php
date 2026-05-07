<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RequirementResource\Pages;
use App\Models\Requirement;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables;
use Filament\Tables\Table;

class RequirementResource extends Resource
{
    protected static ?string $model = Requirement::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Requisitos';

    protected static ?string $modelLabel = 'Requisito';

    protected static ?string $pluralModelLabel = 'Requisitos';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Estructura')
                    ->columns(3)
                    ->schema([
                        TextInput::make('source_id')
                            ->label('ID origen')
                            ->numeric(),
                        TextInput::make('codigo_norma')
                            ->label('Codigo norma')
                            ->maxLength(255),
                        TextInput::make('codigo_interno')
                            ->label('Codigo interno')
                            ->maxLength(255),
                        Select::make('parent_id')
                            ->label('Parent')
                            ->relationship(
                                name: 'parent',
                                titleAttribute: 'nombre_documento',
                                modifyQueryUsing: fn ($query) => $query->select(['id', 'nombre_documento', 'texto', 'codigo_interno'])
                            )
                            ->getOptionLabelFromRecordUsing(fn (Requirement $record): string => trim((string) (
                                $record->nombre_documento
                                ?: $record->texto
                                ?: $record->codigo_interno
                                ?: ('ID ' . $record->id)
                            )))
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        TextInput::make('numeracion')
                            ->label('Numeracion')
                            ->maxLength(255),
                        TextInput::make('orden')
                            ->label('Orden')
                            ->maxLength(255),
                    ]),
                Section::make('Contenido')
                    ->columns(2)
                    ->schema([
                        Textarea::make('texto')
                            ->label('Texto')
                            ->rows(4)
                            ->columnSpanFull(),
                        Textarea::make('requisito')
                            ->label('Requisito')
                            ->rows(4)
                            ->columnSpanFull(),
                        TextInput::make('nombre_documento')
                            ->label('Nombre documento')
                            ->maxLength(255),
                        TextInput::make('carpeta')
                            ->label('Carpeta')
                            ->maxLength(255),
                        TextInput::make('sector')
                            ->label('Sector')
                            ->maxLength(255),
                        TextInput::make('tipo')
                            ->label('Tipo')
                            ->maxLength(255),
                        TextInput::make('literal')
                            ->label('Literal')
                            ->maxLength(255),
                        TextInput::make('origen')
                            ->label('Origen')
                            ->maxLength(255),
                    ]),
                Section::make('Estado')
                    ->columns(2)
                    ->schema([
                        Select::make('requiere_check')
                            ->label('Requiere check')
                            ->options([
                                'SI' => 'SI',
                                'NO' => 'NO',
                            ])
                            ->default('SI'),
                        Toggle::make('visible')
                            ->label('Visible')
                            ->inline(false)
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source_id')
                    ->label('ID')
                    ->width('72px')
                    ->sortable(),
                TextColumn::make('texto')
                    ->label('Texto')
                    ->limit(65)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('nombre_documento')
                    ->label('Documento')
                    ->limit(32)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('carpeta')
                    ->label('Carpeta')
                    ->limit(24)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                SelectColumn::make('requiere_check')
                    ->label('Requisito')
                    ->options([
                        'SI' => 'SI',
                        'NO' => 'NO',
                    ]),
                ToggleColumn::make('visible')
                    ->label('Visible'),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('Y-m-d H:i')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('visible')
                    ->label('Visible'),
                SelectFilter::make('requiere_check')
                    ->label('Requisito')
                    ->options([
                        'SI' => 'SI',
                        'NO' => 'NO',
                    ]),
                SelectFilter::make('sector')
                    ->label('Sector')
                    ->options(fn () => Requirement::query()
                        ->whereNotNull('sector')
                        ->where('sector', '!=', '')
                        ->distinct()
                        ->orderBy('sector')
                        ->pluck('sector', 'sector')
                        ->all()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRequirements::route('/'),
            'create' => Pages\CreateRequirement::route('/create'),
            'edit' => Pages\EditRequirement::route('/{record}/edit'),
        ];
    }
}
