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

class ItemInventario extends Model
{
    /** @use HasFactory<ItemInventarioFactory> */
    use HasFactory;

    protected $table = 'items_inventario';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'tipo',
        'nivel_fidelidad',
        'cantidad_total',
        'descripcion',
        'estado',
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

    /** @param Builder<$this> $consulta */
    public function scopeActivos(Builder $consulta): void
    {
        $consulta->where('activo', true);
    }

    /** @param Builder<$this> $consulta */
    public function scopeDisponibles(Builder $consulta): void
    {
        $consulta->where('activo', true)->where('estado', EstadoItemInventario::Disponible);
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
