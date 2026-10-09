<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalaResource\Pages;

use App\Filament\Resources\SalaResource;
use App\Models\Sala;
use App\Models\User;
use App\Services\SalaService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

/** Sin acción de borrar en la cabecera: las salas se desactivan. */
final class EditSala extends EditRecord
{
    protected static string $resource = SalaResource::class;

    /** Deja el rastro de la ubicación (RF65). */
    protected function afterSave(): void
    {
        $sala = $this->getRecord();
        $actor = Auth::user();

        if ($sala instanceof Sala && $actor instanceof User) {
            app(SalaService::class)->registrarUbicacion($sala, $actor);
        }
    }

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
