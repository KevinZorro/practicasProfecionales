<?php

declare(strict_types=1);

namespace App\Filament\Resources\CertificacionResource\Pages;

use App\Filament\Concerns\BorraLasImagenesReemplazadas;
use App\Filament\Resources\CertificacionResource;
use Filament\Resources\Pages\EditRecord;

final class EditCertificacion extends EditRecord
{
    use BorraLasImagenesReemplazadas;

    protected static string $resource = CertificacionResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
