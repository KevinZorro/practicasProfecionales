<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoItemInventario;
use App\Enums\NivelFidelidad;
use App\Enums\TipoItemInventario;
use Database\Factories\ItemInventarioFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemInventario extends Model
{
    /** @use HasFactory<ItemInventarioFactory> */
    use HasFactory;

    protected $table = 'items_inventario';

    /**
     * nivel_fidelidad y estado NO están aquí a propósito.
     *
     * El RF39 reserva el nivel de fidelidad al ADMIN, y el RF66 exige que
     * todo cambio de estado funcional lleve motivo y responsable. Al quedar
     * fuera de fillable, ninguna asignación masiva puede tocarlos —ni
     * create(), ni update(), ni fill() con lo que llegue de un formulario—,
     * así que las dos reglas no se pueden saltar por descuido. Solo
     * InventarioService los asigna, y antes consulta la Policy.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'tipo',
        'cantidad_total',
        'descripcion',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoItemInventario::class,
            'nivel_fidelidad' => NivelFidelidad::class,
            'cantidad_total' => 'integer',
            'estado' => EstadoItemInventario::class,
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsToMany<CasoClinico, $this> */
    public function casosClinicos(): BelongsToMany
    {
        return $this->belongsToMany(CasoClinico::class, 'caso_clinico_item', 'item_inventario_id', 'caso_clinico_id')
            ->withPivot('cantidad');
    }

    /** @return BelongsToMany<Solicitud, $this> */
    public function solicitudes(): BelongsToMany
    {
        return $this->belongsToMany(Solicitud::class, 'solicitud_item', 'item_inventario_id', 'solicitud_id')
            ->withPivot('cantidad');
    }

    /** @return BelongsToMany<Preparacion, $this> */
    public function preparaciones(): BelongsToMany
    {
        return $this->belongsToMany(Preparacion::class, 'preparacion_item', 'item_inventario_id', 'preparacion_id')
            ->withPivot('cantidad', 'alistado');
    }

    /**
     * Historial de estado funcional, del cambio más reciente al más antiguo
     * (RF66).
     *
     * @return HasMany<CambioEstadoItem, $this>
     */
    public function cambiosDeEstado(): HasMany
    {
        return $this->hasMany(CambioEstadoItem::class, 'item_inventario_id')->masRecientesPrimero();
    }

    /** @param Builder<$this> $consulta */
    public function scopeActivos(Builder $consulta): void
    {
        $consulta->where('activo', true);
    }

    /**
     * Los que pueden comprometerse en una sesión: activos y operativos. Un
     * ítem en revisión, defectuoso o de baja no cuenta (RF66).
     *
     * @param  Builder<$this>  $consulta
     */
    public function scopeDisponibles(Builder $consulta): void
    {
        $consulta->where('activo', true)->where('estado', EstadoItemInventario::Operativo);
    }

    /** @param Builder<$this> $consulta */
    public function scopeDelTipo(Builder $consulta, TipoItemInventario $tipo): void
    {
        $consulta->where('tipo', $tipo);
    }

    /** @param Builder<$this> $consulta */
    public function scopeSimuladores(Builder $consulta): void
    {
        $consulta->where('tipo', TipoItemInventario::Simulador);
    }
}
