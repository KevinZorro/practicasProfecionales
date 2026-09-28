<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerfilDocenteResource\Pages;

use App\Filament\Concerns\EntraAlFinalDelOrden;
use App\Filament\Resources\PerfilDocenteResource;
use Filament\Resources\Pages\CreateRecord;

final class CreatePerfilDocente extends CreateRecord
{
    use EntraAlFinalDelOrden;

    protected static string $resource = PerfilDocenteResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
