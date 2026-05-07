<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Sector;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';

    protected static ?string $navigationGroup = 'Proyectos';

    protected static ?string $modelLabel = 'Proyecto';

    protected static ?string $pluralModelLabel = 'Mis proyectos';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        static::syncSectorsFromRequirements();

        return $form
            ->schema([
                Section::make('Datos principales')
                    ->columns(3)
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('id_proyecto')
                            ->label('ID proyecto')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true),
                        TextInput::make('bipin')
                            ->label('BIPIN')
                            ->maxLength(100),
                        TextInput::make('nombre_clave')
                            ->label('Nombre clave')
                            ->maxLength(255),
                        TextInput::make('municipio')
                            ->label('Municipio')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('secretaria')
                            ->label('Secretaria')
                            ->maxLength(255),
                        DatePicker::make('fecha_creacion')
                            ->label('Fecha creacion'),
                        Select::make('formulador_id')
                            ->label('Formulador')
                            ->options(User::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload(),
                        Select::make('estructurador_id')
                            ->label('Estructurador')
                            ->options(User::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload(),
                        Select::make('sectores')
                            ->label('Sectores')
                            ->relationship('sectores', 'nombre')
                            ->multiple()
                            ->required()
                            ->preload()
                            ->searchable()
                            ->columnSpanFull(),
                    ]),
                Section::make('Contenido y Drive')
                    ->columns(2)
                    ->schema([
                        Textarea::make('objeto_proyecto')
                            ->label('Objeto del proyecto')
                            ->required()
                            ->maxLength(500)
                            ->rows(4)
                            ->columnSpanFull(),
                        TextInput::make('ruta_drive')
                            ->label('Ruta Drive')
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->helperText('Acepta URL de carpeta o solo ID.'),
                        TextInput::make('drive_folder_id')
                            ->label('ID carpeta Drive')
                            ->disabled()
                            ->dehydrated(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre_clave')
                    ->label('Proyecto')
                    ->state(fn (Project $record): string => trim((string) ($record->nombre_clave ?: $record->nombre)))
                    ->limit(34)
                    ->wrap()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $inner) use ($search): void {
                            $inner->where('nombre_clave', 'like', "%{$search}%")
                                ->orWhere('nombre', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw("COALESCE(NULLIF(nombre_clave, ''), nombre) {$direction}")),
                TextColumn::make('municipio')
                    ->label('Municipio')
                    ->limit(18)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sectores.nombre')
                    ->label('Sectores')
                    ->limit(24)
                    ->wrap()
                    ->badge()
                    ->separator(',')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('formulador.name')
                    ->label('Formulador')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('estructurador.name')
                    ->label('Estructurador')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('avance')
                    ->label('Avance')
                    ->state(function (Project $record): int {
                        $total = (int) ($record->requisitos_count ?? 0);
                        $done = (int) ($record->evidencias_validas_count ?? 0);
                        if ($total === 0) {
                            return 0;
                        }

                        return (int) round(($done / $total) * 100);
                    })
                    ->formatStateUsing(fn (int $state): string => $state . '%')
                    ->badge()
                    ->color(fn (int $state): string => $state >= 80 ? 'success' : ($state >= 40 ? 'warning' : 'danger')),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('Y-m-d H:i')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('sectores')
                    ->relationship('sectores', 'nombre')
                    ->label('Sector'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('requisitos')
                    ->label('Requisitos')
                    ->icon('heroicon-o-check-badge')
                    ->url(fn (Project $record): string => static::getUrl('checklist', ['record' => $record])),
                Tables\Actions\Action::make('gestionar')
                    ->label('Gestionar')
                    ->icon('heroicon-o-folder')
                    ->url(fn (Project $record): string => static::getUrl('manage', ['record' => $record])),
                Tables\Actions\Action::make('adjuntos_pdf')
                    ->label('Paquete PDF')
                    ->icon('heroicon-o-archive-box')
                    ->url(fn (Project $record): string => static::getUrl('attachments', ['record' => $record])),
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['sectores', 'formulador', 'estructurador'])
            ->withCount('requisitos')
            ->withCount([
                'evidences as evidencias_validas_count' => function ($query) {
                    $query->select(DB::raw('count(distinct requirement_id)'))
                        ->where('in_drive', true);
                },
            ]);
    }

    protected static function syncSectorsFromRequirements(): void
    {
        $sectors = Requirement::query()
            ->whereNotNull('sector')
            ->where('sector', '!=', '')
            ->distinct()
            ->orderBy('sector')
            ->pluck('sector');

        foreach ($sectors as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            Sector::firstOrCreate(['nombre' => $name]);
        }
    }

    public static function extractDriveFolderId(?string $input): ?string
    {
        $input = trim((string) $input);
        if ($input === '') {
            return null;
        }

        if (preg_match('/^[a-zA-Z0-9_-]{10,}$/', $input)) {
            return $input;
        }

        if (preg_match('#/folders/([a-zA-Z0-9_-]+)#', $input, $m)) {
            return $m[1];
        }

        if (preg_match('#id=([a-zA-Z0-9_-]+)#', $input, $m)) {
            return $m[1];
        }

        if (preg_match('#/drive/folders/([a-zA-Z0-9_-]+)#', $input, $m)) {
            return $m[1];
        }

        return null;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
            'checklist' => Pages\ManageProjectChecklist::route('/{record}/checklist'),
            'manage' => Pages\ManageProject::route('/{record}/manage'),
            'attachments' => Pages\ManageProjectAttachments::route('/{record}/attachments-pdf'),
        ];
    }
}
