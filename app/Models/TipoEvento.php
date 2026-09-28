<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TipoEventoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de tipos de evento (RF05, RF14): congreso, seminario, jornada…
 * Lo define el ADMIN desde su panel; no es una lista fija del código.
 */
class TipoEvento extends Model
{
    /** @use HasFactory<TipoEventoFactory> */
    use HasFactory;

    protected $table = 'tipos_evento';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /** @return HasMany<Evento, $this> */
    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class);
    }

    /** @param Builder<$this> $consulta */
    public function scopeActivos(Builder $consulta): void
    {
        $consulta->where('activo', true);
    }
}
