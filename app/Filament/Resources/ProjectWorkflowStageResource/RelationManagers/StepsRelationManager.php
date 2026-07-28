<?php

namespace App\Filament\Resources\ProjectWorkflowStageResource\RelationManagers;

use App\Filament\Resources\ProjectWorkflowStepResource;
use App\Models\ProjectWorkflowStep;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class StepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';

    protected static ?string $title = 'Elementos del flujo';

    protected static ?string $modelLabel = 'Elemento del flujo';

    protected static ?string $pluralModelLabel = 'Elementos del flujo';

    public function form(Form $form): Form
    {
        return $form->schema(ProjectWorkflowStepResource::elementFormSchema(includeStage: false));
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Elementos del flujo')
            ->description('Cree los elementos de esta macroetapa y vincule requisitos existentes.')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(60)
                    ->placeholder('Sin descripción'),
                TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable(),
                TextColumn::make('requirement_links_count')
                    ->counts('requirementLinks')
                    ->label('Requisitos'),
                ToggleColumn::make('is_active')
                    ->label('Activo'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nuevo elemento')
                    ->modalHeading('Nuevo elemento del flujo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalHeading('Editar elemento del flujo'),
                Tables\Actions\Action::make('toggleActive')
                    ->label(fn (ProjectWorkflowStep $record): string => $record->is_active ? 'Desactivar' : 'Activar')
                    ->icon(fn (ProjectWorkflowStep $record): string => $record->is_active
                        ? 'heroicon-o-pause-circle'
                        : 'heroicon-o-play-circle')
                    ->color(fn (ProjectWorkflowStep $record): string => $record->is_active ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (ProjectWorkflowStep $record) => $record->update([
                        'is_active' => ! $record->is_active,
                    ])),
                Tables\Actions\DeleteAction::make()
                    ->disabled(fn (ProjectWorkflowStep $record): bool => ! $record->canBeDeletedSafely())
                    ->tooltip(fn (ProjectWorkflowStep $record): ?string => $record->canBeDeletedSafely()
                        ? null
                        : 'Tiene estados históricos y no puede eliminarse. Desactívelo en su lugar.'),
            ])
            ->emptyStateHeading('Esta macroetapa aún no tiene elementos')
            ->emptyStateDescription('Use “Nuevo elemento” para completar el flujo sin salir de esta pantalla.');
    }
}
