<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccionAuditada;
use App\Models\RegistroDeBitacora;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Bitácora de auditoría (RF62).
 *
 * Cada Service que aprueba, rechaza, reprograma, sustituye, retira, bloquea o
 * cambia roles llama a registrar() dentro de su propia transacción: si la
 * acción se deshace, la entrada también. No se escribe desde los
 * componentes, para que valga igual en comandos y colas.
 */
final class BitacoraService
{
    public const POR_PAGINA = 25;

    public function registrar(
        AccionAuditada $accion,
        User $actor,
        Model $sujeto,
        string $descripcion,
        ?string $motivo = null,
    ): RegistroDeBitacora {
        return RegistroDeBitacora::create([
            'accion' => $accion,
            'user_id' => $actor->id,
            'auditable_type' => $sujeto->getMorphClass(),
            'auditable_id' => $sujeto->getKey(),
            'descripcion' => $descripcion,
            'motivo' => $motivo,
        ]);
    }

    /**
     * La más reciente primero, con filtros por acción, por quien la hizo y
     * por rango de fechas (días completos, en la zona de la aplicación).
     *
     * @return LengthAwarePaginator<int, RegistroDeBitacora>
     */
    public function listado(
        ?AccionAuditada $accion = null,
        ?string $persona = null,
        ?string $desde = null,
        ?string $hasta = null,
        int $porPagina = self::POR_PAGINA,
    ): LengthAwarePaginator {
        return RegistroDeBitacora::query()
            ->when($accion !== null, static fn (Builder $c) => $c->where('accion', $accion))
            ->when(
                is_string($persona) && trim($persona) !== '',
                static fn (Builder $c) => $c->whereHas('usuario', static fn (Builder $u) => $u
                    ->whereRaw('LOWER(nombre) LIKE ?', ['%'.mb_strtolower(trim((string) $persona)).'%'])),
            )
            ->when($desde !== null && $desde !== '', static fn (Builder $c) => $c->whereDate('created_at', '>=', $desde))
            ->when($hasta !== null && $hasta !== '', static fn (Builder $c) => $c->whereDate('created_at', '<=', $hasta))
            ->with('usuario:id,nombre')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($porPagina);
    }
}
