<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class RequirementsImport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Requisitos';

    protected static ?string $title = 'Importar Requisitos';

    protected static ?string $slug = 'requirements-import';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.requirements-import';
}

