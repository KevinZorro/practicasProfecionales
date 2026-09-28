<?php

declare(strict_types=1);

namespace App\Filament\Resources\TallerResource\Pages;

use App\Filament\Concerns\EntraAlFinalDelOrden;
use App\Filament\Resources\TallerResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateTaller extends CreateRecord
{
    use EntraAlFinalDelOrden;

    protected static string $resource = TallerResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
