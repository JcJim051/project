<?php
namespace App\Filament\Resources\OperationalLabelResource\Pages;
use App\Filament\Resources\OperationalLabelResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
class ListOperationalLabels extends ListRecords { protected static string $resource = OperationalLabelResource::class; protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; } }
