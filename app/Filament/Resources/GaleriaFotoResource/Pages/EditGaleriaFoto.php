<?php

declare(strict_types=1);

namespace App\Filament\Resources\GaleriaFotoResource\Pages;

use App\Filament\Concerns\BorraLasImagenesReemplazadas;
use App\Filament\Resources\GaleriaFotoResource;
use Filament\Resources\Pages\EditRecord;

final class EditGaleriaFoto extends EditRecord
{
    use BorraLasImagenesReemplazadas;

    protected static string $resource = GaleriaFotoResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
