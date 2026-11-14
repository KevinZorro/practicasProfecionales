<?php

declare(strict_types=1);

namespace App\Filament\Resources\VideoInstitucionalResource\Pages;

use App\Filament\Resources\VideoInstitucionalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListVideosInstitucionales extends ListRecords
{
    protected static string $resource = VideoInstitucionalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
