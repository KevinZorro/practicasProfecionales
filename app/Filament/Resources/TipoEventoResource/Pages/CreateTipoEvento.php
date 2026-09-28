<?php

declare(strict_types=1);

namespace App\Filament\Resources\TipoEventoResource\Pages;

use App\Filament\Resources\TipoEventoResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateTipoEvento extends CreateRecord
{
    protected static string $resource = TipoEventoResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
