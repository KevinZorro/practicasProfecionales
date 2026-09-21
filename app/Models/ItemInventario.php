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
     * Ni nivel_fidelidad ni las cantidades están aquí, a propósito.
     *
     * El RF39 reserva el nivel de fidelidad al ADMIN, y el RF66 exige que
     * toda unidad que cambie de estado o entre o salga del inventario lleve
     * motivo y responsable. Al quedar fuera de fillable, ninguna asignación
     * masiva puede tocarlos —ni create(), ni update(), ni fill() con lo que
     * llegue de un formulario—, así que las reglas no se pueden saltar por
     * descuido. Solo InventarioService los asigna, y antes consulta la
     * Policy.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'tipo',
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
            'cantidad_operativa' => 'integer',
            'cantidad_en_revision' => 'integer',
            'cantidad_defectuosa' => 'integer',
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
     * Los que tienen algo que ofrecer: activos y con alguna unidad
     * operativa. Las unidades en revisión, defectuosas o dadas de baja no
     * cuentan (RF66).
     *
     * @param  Builder<$this>  $consulta
     */
    public function scopeDisponibles(Builder $consulta): void
    {
        $consulta->where('activo', true)->where('cantidad_operativa', '>', 0);
    }

    /**
     * Los que tienen unidades esperando una decisión: lo que la
     * administrativa busca cuando entra a revisar qué hay que mirar.
     *
     * @param  Builder<$this>  $consulta
     */
    public function scopeConUnidadesNoOperativas(Builder $consulta): void
    {
        $consulta->where(static function (Builder $o): void {
            $o->where('cantidad_en_revision', '>', 0)->orWhere('cantidad_defectuosa', '>', 0);
        });
    }

    /**
     * Unidades que el ítem tiene en el estado dado. La baja no cuenta: esas
     * unidades salieron del total.
     *
     * @param  Builder<$this>  $consulta
     */
    public function scopeConUnidadesEn(Builder $consulta, EstadoItemInventario $estado): void
    {
        $columna = $estado->columnaDeCantidad();

        $columna === null
            ? $consulta->where('cantidad_total', 0)
            : $consulta->where($columna, '>', 0);
    }

    /** Unidades en un estado concreto. */
    public function cantidadEn(EstadoItemInventario $estado): int
    {
        $columna = $estado->columnaDeCantidad();

        return $columna === null ? 0 : (int) $this->{$columna};
    }

    /**
     * Desglose para pintar, sin los estados vacíos.
     *
     * @return array<string, int>
     */
    public function desgloseDeUnidades(): array
    {
        $desglose = [];

        foreach (EstadoItemInventario::conContador() as $estado) {
            if ($this->cantidadEn($estado) > 0) {
                $desglose[$estado->value] = $this->cantidadEn($estado);
            }
        }

        return $desglose;
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
