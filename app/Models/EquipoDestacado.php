<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EquipoDestacadoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Un equipo que la portada presenta como protagonista (RF01, RF10): un
 * simulador, la sala inmersiva, la mesa de anatomía virtual. El primero en
 * el orden es el principal.
 */
class EquipoDestacado extends Model
{
    /** @use HasFactory<EquipoDestacadoFactory> */
    use HasFactory;

    protected $table = 'equipos_destacados';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'resumen',
        'imagen',
        'caracteristicas',
        'orden',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'caracteristicas' => 'array',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /** @param Builder<$this> $consulta */
    public function scopePublicados(Builder $consulta): void
    {
        $consulta->where('activo', true)->orderBy('orden');
    }
}
