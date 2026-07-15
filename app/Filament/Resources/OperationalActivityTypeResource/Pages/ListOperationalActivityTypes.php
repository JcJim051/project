<?php
namespace App\Filament\Resources\OperationalActivityTypeResource\Pages;
use App\Filament\Resources\OperationalActivityTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
class ListOperationalActivityTypes extends ListRecords { protected static string $resource = OperationalActivityTypeResource::class; protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; } }
