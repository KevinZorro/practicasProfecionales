<?php

declare(strict_types=1);

namespace App\Filament\Resources\MateriaResource\Pages;

use App\Filament\Resources\MateriaResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateMateria extends CreateRecord
{
    protected static string $resource = MateriaResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
