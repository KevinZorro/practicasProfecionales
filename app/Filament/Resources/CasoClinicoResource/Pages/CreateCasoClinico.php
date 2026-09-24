<?php

declare(strict_types=1);

namespace App\Filament\Resources\CasoClinicoResource\Pages;

use App\Filament\Resources\CasoClinicoResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateCasoClinico extends CreateRecord
{
    protected static string $resource = CasoClinicoResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
