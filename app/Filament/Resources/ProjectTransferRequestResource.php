<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectTransferRequestResource\Pages;
use App\Models\ProjectTransferRequest;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectTransferRequestResource extends Resource
{
    protected static ?string $model = ProjectTransferRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Proyectos';

    protected static ?string $modelLabel = 'Autorización MGA';

    protected static ?string $pluralModelLabel = 'Autorizaciones MGA';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('project.id_proyecto')->label('ID proyecto')->searchable(),
                TextColumn::make('project.nombre')
                    ->label('Proyecto')
                    ->searchable()
                    ->limit(42)
                    ->url(fn (ProjectTransferRequest $record): string => static::getUrl('review', ['record' => $record]))
                    ->openUrlInNewTab(false)
                    ->color('primary')
                    ->weight('semibold'),
                TextColumn::make('status')->label('Estado general')->badge(),
                TextColumn::make('director_status')->label('Dirección')->badge()->toggleable(),
                TextColumn::make('planning_status')->label('Planeación AIM')->badge()->toggleable(),
                TextColumn::make('requestedBy.name')->label('Solicitó')->toggleable(),
                TextColumn::make('requested_at')->label('Enviado')->dateTime('Y-m-d H:i'),
                TextColumn::make('directorDecidedBy.name')->label('Dir. decidió')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('planningDecidedBy.name')->label('Plan. decidió')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('decision_note')->label('Comentario')->limit(60)->wrap(),
                TextColumn::make('receipts_count')->label('Acuses'),
            ])
            ->defaultSort('requested_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('evaluar')
                    ->label('Evaluar')
                    ->icon('heroicon-o-eye')
                    ->url(fn (ProjectTransferRequest $record): string => static::getUrl('review', ['record' => $record])),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                        'cancelled' => 'Cancelado',
                    ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['project', 'requestedBy', 'decidedBy', 'directorDecidedBy', 'planningDecidedBy'])
            ->withCount('receipts');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjectTransferRequests::route('/'),
            'review' => Pages\ReviewProjectTransferRequest::route('/{record}/review'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->canAuthorizeMgaTransfer());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canView($record): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->canAuthorizeMgaTransfer());
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
