<?php

declare(strict_types=1);

namespace App\Filament\Resources\CertificacionResource\Pages;

use App\Filament\Concerns\EntraAlFinalDelOrden;
use App\Filament\Resources\CertificacionResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateCertificacion extends CreateRecord
{
    use EntraAlFinalDelOrden;

    protected static string $resource = CertificacionResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
