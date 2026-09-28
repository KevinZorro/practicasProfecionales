<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventoResource\Pages;

use App\Filament\Concerns\EntraAlFinalDelOrden;
use App\Filament\Resources\EventoResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateEvento extends CreateRecord
{
    use EntraAlFinalDelOrden;

    protected static string $resource = EventoResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
