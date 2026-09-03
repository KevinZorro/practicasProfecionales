<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoPreparacion;
use Database\Factories\PreparacionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Preparacion extends Model
{
    /** @use HasFactory<PreparacionFactory> */
    use HasFactory;

    protected $table = 'preparaciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'solicitud_id',
        'sala_id',
        'estado',
        'preparado_por',
        'preparado_at',
        'observaciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoPreparacion::class,
            'preparado_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Solicitud, $this> */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class);
    }

    /** @return BelongsTo<Sala, $this> */
    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class);
    }

    /** @return BelongsTo<User, $this> */
    public function preparadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preparado_por');
    }

    /** @return BelongsToMany<ItemInventario, $this> */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(ItemInventario::class, 'preparacion_item', 'preparacion_id', 'item_inventario_id')
            ->withPivot('cantidad', 'alistado');
    }

    /** @param Builder<$this> $consulta */
    public function scopeEnEstado(Builder $consulta, EstadoPreparacion $estado): void
    {
        $consulta->where('estado', $estado);
    }

    /** @param Builder<$this> $consulta */
    public function scopeSinSala(Builder $consulta): void
    {
        $consulta->whereNull('sala_id');
    }
}
