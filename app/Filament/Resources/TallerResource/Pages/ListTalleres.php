<?php

declare(strict_types=1);

namespace App\Filament\Resources\TallerResource\Pages;

use App\Filament\Resources\TallerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListTalleres extends ListRecords
{
    protected static string $resource = TallerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
