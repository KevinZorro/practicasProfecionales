<?php

declare(strict_types=1);

namespace App\Filament\Resources\VideoInstitucionalResource\Pages;

use App\Filament\Concerns\BorraLasImagenesReemplazadas;
use App\Filament\Resources\VideoInstitucionalResource;
use Filament\Resources\Pages\EditRecord;

final class EditVideoInstitucional extends EditRecord
{
    use BorraLasImagenesReemplazadas;

    protected static string $resource = VideoInstitucionalResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
