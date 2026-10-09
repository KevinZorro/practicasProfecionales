<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AjusteDelLaboratorio;
use App\Models\AjusteLaboratorio;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Ajustes del laboratorio que el ADMIN cambia sin desplegar.
 */
final class AjustesService
{
    public const DIAS_DE_AVISO_MAXIMOS = 60;

    public function valor(AjusteDelLaboratorio $clave): string
    {
        return AjusteLaboratorio::query()->where('clave', $clave)->value('valor') ?? $clave->valorPorDefecto();
    }

    /** Días de antelación del aviso de sesiones sin formato intramural (RF60). */
    public function diasDeAvisoIntramural(): int
    {
        return (int) $this->valor(AjusteDelLaboratorio::DiasDeAvisoIntramural);
    }

    /**
     * @param  array<string, string|int>  $valores  clave => valor
     */
    public function guardar(array $valores, User $admin): void
    {
        if ($admin->cannot('update', AjusteLaboratorio::class)) {
            throw new AuthorizationException('Solo el ADMIN cambia los ajustes del laboratorio.');
        }

        DB::transaction(static function () use ($valores): void {
            foreach ($valores as $clave => $valor) {
                AjusteLaboratorio::query()->updateOrCreate(
                    ['clave' => AjusteDelLaboratorio::from($clave)],
                    ['valor' => (string) $valor],
                );
            }
        });
    }
}
