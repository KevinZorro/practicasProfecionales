<?php

declare(strict_types=1);

namespace App\Filament\Resources\CertificacionResource\Pages;

use App\Filament\Resources\CertificacionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListCertificaciones extends ListRecords
{
    protected static string $resource = CertificacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
