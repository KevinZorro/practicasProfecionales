<?php

declare(strict_types=1);

namespace App\Filament\Resources\MateriaResource\Pages;

use App\Filament\Resources\MateriaResource;
use Filament\Resources\Pages\EditRecord;

/** Sin acción de borrar en la cabecera: las materias se desactivan. */
final class EditMateria extends EditRecord
{
    protected static string $resource = MateriaResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
