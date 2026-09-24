<?php

declare(strict_types=1);

namespace App\Filament\Resources\CasoClinicoResource\Pages;

use App\Filament\Resources\CasoClinicoResource;
use Filament\Resources\Pages\EditRecord;

/** Sin acción de borrar en la cabecera: los casos clínicos se desactivan. */
final class EditCasoClinico extends EditRecord
{
    protected static string $resource = CasoClinicoResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
