<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Rol;
use Database\Factories\AsignacionDeRolFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

/**
 * Rastro de una asignación de rol (RF63, RF64).
 *
 * Es de solo añadir: al revocar no se borra la fila, se le pone fecha y
 * responsable. Lo que sí desaparece es la fila del pivote de spatie, que es
 * la que da permisos; aquí queda la constancia de que existió.
 *
 * Una asignación vencida o revocada sigue teniendo fila: el RF63 pide poder
 * responder quién delegó en quién, cuándo y por qué, meses después.
 */
class AsignacionDeRol extends Model
{
    /** @use HasFactory<AsignacionDeRolFactory> */
    use HasFactory;

    protected $table = 'asignaciones_de_rol';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'role_id',
        'desde',
        'hasta',
        'motivo',
        'asignado_por',
        'revocada_at',
        'revocada_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'desde' => 'date',
            'hasta' => 'date',
            'revocada_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /** @return BelongsTo<User, $this> */
    public function asignadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_por');
    }

    /** @return BelongsTo<User, $this> */
    public function revocadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revocada_por');
    }

    /** El rol como enum del dominio, no como fila de spatie. */
    public function rol(): ?Rol
    {
        return Rol::tryFrom((string) $this->role?->name);
    }

    public function esTemporal(): bool
    {
        return $this->hasta !== null;
    }

    public function estaRevocada(): bool
    {
        return $this->revocada_at !== null;
    }

    /**
     * Si esta asignación da permisos hoy. No la consulta el sistema de
     * permisos —eso lo hace el filtro de User::roles() en SQL—, sirve para
     * pintar la pantalla del ADMIN.
     */
    public function estaVigente(): bool
    {
        if ($this->estaRevocada()) {
            return false;
        }

        $hoy = now()->toDateString();

        return $this->desde->toDateString() <= $hoy
            && ($this->hasta === null || $this->hasta->toDateString() >= $hoy);
    }

    /** @param Builder<$this> $consulta */
    public function scopeVigentes(Builder $consulta): void
    {
        $hoy = now()->toDateString();

        $consulta->whereNull('revocada_at')
            ->whereDate('desde', '<=', $hoy)
            ->where(static function (Builder $o) use ($hoy): void {
                $o->whereNull('hasta')->orWhereDate('hasta', '>=', $hoy);
            });
    }

    /** @param Builder<$this> $consulta */
    public function scopeTemporales(Builder $consulta): void
    {
        $consulta->whereNotNull('hasta');
    }
}
