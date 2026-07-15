<?php
namespace App\Filament\Resources\OperationalCycleResource\Pages;
use App\Filament\Resources\OperationalCycleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
class ListOperationalCycles extends ListRecords { protected static string $resource = OperationalCycleResource::class; protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; } }
