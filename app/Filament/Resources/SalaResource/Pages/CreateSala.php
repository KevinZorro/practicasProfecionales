<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalaResource\Pages;

use App\Filament\Resources\SalaResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateSala extends CreateRecord
{
    protected static string $resource = SalaResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
