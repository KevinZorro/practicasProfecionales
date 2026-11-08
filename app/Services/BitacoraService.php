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
     * Lo que no apunta a un registro: una acción de Livewire negada, de la
     * que solo se sabe qué se pidió, o la exportación de un reporte, que es
     * una consulta con filtros (B4.2, B4.5).
     */
    public function registrarSinSujeto(AccionAuditada $accion, User $actor, string $descripcion): RegistroDeBitacora
    {
        return RegistroDeBitacora::create([
            'accion' => $accion,
            'user_id' => $actor->id,
            'descripcion' => $descripcion,
        ]);
    }

    /**
     * Un 403 a alguien con sesión (B4.5): quién, qué pidió y, si la ruta lo
     * resolvió, sobre qué registro. Lo escribe el middleware
     * RegistrarAccesosNegados, después de que la acción ya se negó.
     */
    public function registrarAccesoNegado(User $actor, ?Model $sujeto, string $descripcion): RegistroDeBitacora
    {
        if ($sujeto instanceof Model) {
            return $this->registrar(AccionAuditada::AccesoNegado, $actor, $sujeto, $descripcion);
        }

        return $this->registrarSinSujeto(AccionAuditada::AccesoNegado, $actor, $descripcion);
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
