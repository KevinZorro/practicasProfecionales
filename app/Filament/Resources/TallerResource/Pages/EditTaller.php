<?php

declare(strict_types=1);

namespace App\Filament\Resources\TallerResource\Pages;

use App\Filament\Concerns\BorraLasImagenesReemplazadas;
use App\Filament\Resources\TallerResource;
use Filament\Resources\Pages\EditRecord;

final class EditTaller extends EditRecord
{
    use BorraLasImagenesReemplazadas;

    protected static string $resource = TallerResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
