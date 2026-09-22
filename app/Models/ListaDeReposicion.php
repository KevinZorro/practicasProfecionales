<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ListaDeReposicionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lista de insumos por pedir o reponer de un periodo (RF67).
 *
 * En borrador no tiene líneas: la pantalla calcula la previsualización desde
 * el historial de inventario y las necesidades registradas. Al cerrarla, las
 * líneas se congelan y ya no cambian, porque es el soporte de una carta que
 * se entrega una sola vez.
 */
class ListaDeReposicion extends Model
{
    /** @use HasFactory<ListaDeReposicionFactory> */
    use HasFactory;

    protected $table = 'listas_reposicion';

    /**
     * cerrada_por y cerrada_at quedan fuera de fillable: cerrar es un acto
     * con responsable, y solo ReposicionService lo hace.
     *
     * @var list<string>
     */
    protected $fillable = [
        'desde',
        'hasta',
        'observaciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'desde' => 'date',
            'hasta' => 'date',
            'cerrada_at' => 'datetime',
        ];
    }

    /** @return HasMany<LineaDeReposicion, $this> */
    public function lineas(): HasMany
    {
        return $this->hasMany(LineaDeReposicion::class, 'lista_reposicion_id');
    }

    /**
     * Necesidades que esta lista incluyó cuando se cerró.
     *
     * @return BelongsToMany<NecesidadDeReposicion, $this>
     */
    public function necesidades(): BelongsToMany
    {
        return $this->belongsToMany(
            NecesidadDeReposicion::class,
            'lista_reposicion_necesidad',
            'lista_reposicion_id',
            'necesidad_reposicion_id',
        );
    }

    /** @return BelongsTo<User, $this> */
    public function cerradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrada_por');
    }

    public function estaCerrada(): bool
    {
        return $this->cerrada_at !== null;
    }

    /** @param Builder<$this> $consulta */
    public function scopeCerradas(Builder $consulta): void
    {
        $consulta->whereNotNull('cerrada_at');
    }

    /** @param Builder<$this> $consulta */
    public function scopeEnBorrador(Builder $consulta): void
    {
        $consulta->whereNull('cerrada_at');
    }
}
