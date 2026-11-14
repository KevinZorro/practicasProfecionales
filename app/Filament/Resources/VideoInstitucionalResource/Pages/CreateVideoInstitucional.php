<?php

declare(strict_types=1);

namespace App\Filament\Resources\VideoInstitucionalResource\Pages;

use App\Filament\Concerns\EntraAlFinalDelOrden;
use App\Filament\Resources\VideoInstitucionalResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateVideoInstitucional extends CreateRecord
{
    use EntraAlFinalDelOrden;

    protected static string $resource = VideoInstitucionalResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
