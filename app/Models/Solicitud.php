<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoSolicitud;
use App\Enums\TipoSesion;
use Database\Factories\SolicitudFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Solicitud extends Model
{
    /** @use HasFactory<SolicitudFactory> */
    use HasFactory;

    protected $table = 'solicitudes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'docente_id',
        'materia_id',
        'caso_clinico_id',
        'tipo',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'cantidad_estudiantes',
        'estado',
        'revisada_por',
        'revisada_at',
        'resuelta_por',
        'resuelta_at',
        'motivo_rechazo',
        'observaciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoSesion::class,
            'estado' => EstadoSolicitud::class,
            'fecha' => 'date',
            'cantidad_estudiantes' => 'integer',
            'revisada_at' => 'datetime',
            'resuelta_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function docente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docente_id');
    }

    /** @return BelongsTo<Materia, $this> */
    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class);
    }

    /** @return BelongsTo<CasoClinico, $this> */
    public function casoClinico(): BelongsTo
    {
        return $this->belongsTo(CasoClinico::class);
    }

    /** @return BelongsTo<User, $this> */
    public function revisadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisada_por');
    }

    /** @return BelongsTo<User, $this> */
    public function resueltaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelta_por');
    }

    /** @return BelongsToMany<ItemInventario, $this> */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(ItemInventario::class, 'solicitud_item', 'solicitud_id', 'item_inventario_id')
            ->withPivot('cantidad');
    }

    /** @return HasOne<Preparacion, $this> */
    public function preparacion(): HasOne
    {
        return $this->hasOne(Preparacion::class);
    }

    /** @return HasOne<Evaluacion, $this> */
    public function evaluacion(): HasOne
    {
        return $this->hasOne(Evaluacion::class);
    }

    /** @param Builder<$this> $consulta */
    public function scopeEnEstado(Builder $consulta, EstadoSolicitud $estado): void
    {
        $consulta->where('estado', $estado);
    }

    /** @param Builder<$this> $consulta */
    public function scopeAprobadas(Builder $consulta): void
    {
        $consulta->where('estado', EstadoSolicitud::Aprobada);
    }

    /** @param Builder<$this> $consulta */
    public function scopePendientes(Builder $consulta): void
    {
        $consulta->where('estado', EstadoSolicitud::Pendiente);
    }

    /** @param Builder<$this> $consulta */
    public function scopeDeEvaluacion(Builder $consulta): void
    {
        $consulta->where('tipo', TipoSesion::Evaluacion);
    }

    /** @param Builder<$this> $consulta */
    public function scopeDelDia(Builder $consulta, string $fecha): void
    {
        $consulta->whereDate('fecha', $fecha);
    }
}
