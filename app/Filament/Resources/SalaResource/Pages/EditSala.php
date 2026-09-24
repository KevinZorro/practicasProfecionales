<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalaResource\Pages;

use App\Filament\Resources\SalaResource;
use Filament\Resources\Pages\EditRecord;

/** Sin acción de borrar en la cabecera: las salas se desactivan. */
final class EditSala extends EditRecord
{
    protected static string $resource = SalaResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
