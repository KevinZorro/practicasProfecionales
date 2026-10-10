<?php

declare(strict_types=1);

namespace App\Filament\Resources\EquipoDestacadoResource\Pages;

use App\Filament\Concerns\BorraLasImagenesReemplazadas;
use App\Filament\Resources\EquipoDestacadoResource;
use Filament\Resources\Pages\EditRecord;

final class EditEquipoDestacado extends EditRecord
{
    use BorraLasImagenesReemplazadas;

    protected static string $resource = EquipoDestacadoResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
