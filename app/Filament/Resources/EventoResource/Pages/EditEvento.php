<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventoResource\Pages;

use App\Filament\Concerns\BorraLasImagenesReemplazadas;
use App\Filament\Resources\EventoResource;
use Filament\Resources\Pages\EditRecord;

final class EditEvento extends EditRecord
{
    use BorraLasImagenesReemplazadas;

    protected static string $resource = EventoResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
