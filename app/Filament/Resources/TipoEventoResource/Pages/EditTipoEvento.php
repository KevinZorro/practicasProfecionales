<?php

declare(strict_types=1);

namespace App\Filament\Resources\TipoEventoResource\Pages;

use App\Filament\Resources\TipoEventoResource;
use Filament\Resources\Pages\EditRecord;

/** Sin acción de borrar en la cabecera: los tipos de evento se desactivan. */
final class EditTipoEvento extends EditRecord
{
    protected static string $resource = TipoEventoResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
