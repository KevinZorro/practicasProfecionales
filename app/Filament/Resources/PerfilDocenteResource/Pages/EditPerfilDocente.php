<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerfilDocenteResource\Pages;

use App\Filament\Concerns\BorraLasImagenesReemplazadas;
use App\Filament\Resources\PerfilDocenteResource;
use Filament\Resources\Pages\EditRecord;

final class EditPerfilDocente extends EditRecord
{
    use BorraLasImagenesReemplazadas;

    protected static string $resource = PerfilDocenteResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
