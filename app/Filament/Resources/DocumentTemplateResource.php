<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentTemplateResource\Pages;
use App\Models\DocumentTemplate;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class DocumentTemplateResource extends Resource
{
    protected static ?string $model = DocumentTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Parametrizacion';

    protected static ?string $modelLabel = 'Plantilla';

    protected static ?string $pluralModelLabel = 'Plantillas';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('file_kind')
                    ->label('Tipo de archivo')
                    ->options([
                        'docx' => 'DOCX',
                        'xlsx' => 'XLSX',
                    ])
                    ->default('docx')
                    ->required()
                    ->native(false),
                Select::make('template_type')
                    ->label('Tipo de plantilla')
                    ->options([
                        'docx_general' => 'DOCX General',
                        'bank_plan_inversion' => 'Banco F-PE-23',
                        'bank_plan_desarrollo' => 'Banco F-PE-24',
                        'bank_cronograma' => 'Banco F-PE-25',
                        'bank_request_fbs01' => 'Solicitud Banco F-BS-01',
                        'meeting_attendance' => 'Asistencias a reuniones',
                    ])
                    ->default('docx_general')
                    ->required()
                    ->native(false),
                TextInput::make('version')
                    ->label('Versión')
                    ->maxLength(40)
                    ->placeholder('Ejemplo: 07'),
                Toggle::make('is_active')
                    ->label('Plantilla activa')
                    ->default(true),
                DatePicker::make('effective_at')
                    ->label('Vigente desde'),
                KeyValue::make('sheet_config')
                    ->label('Configuración de hojas')
                    ->keyLabel('Modalidad')
                    ->valueLabel('Nombre exacto de hoja')
                    ->helperText('Para F-BS-01 usa: obra = OBRA, inter = INTER y apoyo = APOYO.'),
                FileUpload::make('ruta_archivo')
                    ->label('Archivo')
                    ->required()
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->directory('document_templates')
                    ->disk('local')
                    ->preserveFilenames()
                    ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file): string {
                        $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                        $ext = strtolower((string) $file->getClientOriginalExtension());
                        if (! in_array($ext, ['docx', 'xlsx'], true)) {
                            $ext = 'docx';
                        }
                        $safe = Str::slug((string) $base, '_');
                        if ($safe === '') {
                            $safe = 'plantilla_'.Str::lower(Str::random(8));
                        }

                        return $safe.'.'.$ext;
                    })
                    ->downloadable()
                    ->openable()
                    ->helperText('DOCX o XLSX. DOCX usa marcadores como {{OBJETO}}, {{ID_PROYECTO}}, {{BIPIN}}, {{FORMULADOR}}, {{FECHA}}. El marcador legado {{BPIN}} tambien se llena con el ID proyecto. Para Asistencias a reuniones, carga la plantilla oficial XLSX base que se debe diligenciar y convertir a PDF.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('template_type')
                    ->label('Tipo plantilla')
                    ->badge(),
                TextColumn::make('file_kind')
                    ->label('Tipo archivo')
                    ->badge(),
                TextColumn::make('version')
                    ->label('Versión')
                    ->badge(),
                TextColumn::make('is_active')
                    ->label('Activa')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Sí' : 'No')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('ruta_archivo')
                    ->label('Archivo')
                    ->limit(55),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                //
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
            'index' => Pages\ListDocumentTemplates::route('/'),
            'create' => Pages\CreateDocumentTemplate::route('/create'),
            'edit' => Pages\EditDocumentTemplate::route('/{record}/edit'),
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
        return static::canViewAny();
    }
}
