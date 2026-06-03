<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttachmentPackageSectionResource\Pages;
use App\Models\AttachmentPackageSection;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttachmentPackageSectionResource extends Resource
{
    protected static ?string $model = AttachmentPackageSection::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Parametrizacion';
    protected static ?string $navigationLabel = 'Estructura de Carteras';
    protected static ?string $pluralModelLabel = 'Estructura de Carteras';
    protected static ?int $navigationSort = 18;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(2)->schema([
                Select::make('parent_id')
                    ->label('Grupo padre')
                    ->helperText('Déjalo vacío para crear un grupo principal.')
                    ->options(fn () => AttachmentPackageSection::query()
                        ->whereNull('parent_id')
                        ->orderBy('orden')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload(),
                TextInput::make('orden')->numeric()->required()->default(0)->minValue(0),
                TextInput::make('name')->label('Nombre visible del PDF / grupo')->required()->maxLength(255)->columnSpanFull(),
                Toggle::make('active')->label('Activo')->default(true),
                Toggle::make('include_all_folder_files')->label('Incluir todos los archivos de la carpeta Drive')->default(false),
            ]),
            Grid::make(2)->schema([
                Select::make('match_type')
                    ->label('Regla de selección')
                    ->required()
                    ->options([
                        'group' => 'Grupo principal',
                        'group_code' => 'Por grupo/código principal',
                        'folder' => 'Por carpeta exacta',
                        'code_prefix' => 'Por códigos iniciales',
                        'studies_subfolders' => 'Estudios: una cartera por subcarpeta',
                    ])
                    ->default('folder'),
                TextInput::make('source_group_code')
                    ->label('Código grupo origen')
                    ->helperText('Ej: 01, 02, 03, 04, 05')
                    ->maxLength(5),
                TextInput::make('source_folder')
                    ->label('Carpeta origen')
                    ->helperText('Ej: 3.3 Otras Certificaciones')
                    ->maxLength(255),
                TagsInput::make('code_prefixes')
                    ->label('Códigos iniciales')
                    ->helperText('Ej: 1.01, 1.06, 1.13')
                    ->separator(','),
                TagsInput::make('allowed_extensions')
                    ->label('Extensiones permitidas')
                    ->helperText('Opcional. Ej: pdf, docx, xlsx')
                    ->separator(','),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->columns([
                TextColumn::make('parent.name')->label('Grupo')->sortable()->placeholder('Grupo principal'),
                TextColumn::make('orden')->sortable()->width('80px'),
                TextColumn::make('name')->label('Nombre')->searchable()->wrap(),
                TextColumn::make('match_type')->label('Regla')->badge(),
                TextColumn::make('source_group_code')->label('Código'),
                TextColumn::make('source_folder')->label('Carpeta')->searchable()->wrap(),
                IconColumn::make('include_all_folder_files')->label('Todos')->boolean(),
                IconColumn::make('active')->label('Activo')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttachmentPackageSections::route('/'),
            'create' => Pages\CreateAttachmentPackageSection::route('/create'),
            'edit' => Pages\EditAttachmentPackageSection::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $u = auth()->user();
        return (bool) ($u && $u->canManageDirectorCatalogs());
    }

    public static function canCreate(): bool { return static::canViewAny(); }
    public static function canEdit($record): bool { return static::canViewAny(); }
    public static function canDelete($record): bool { return false; }
}
