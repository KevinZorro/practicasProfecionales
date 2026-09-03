<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EstadisticaLandingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstadisticaLanding extends Model
{
    /** @use HasFactory<EstadisticaLandingFactory> */
    use HasFactory;

    protected $table = 'estadisticas_landing';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'etiqueta',
        'valor',
        'orden',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /** @param Builder<$this> $consulta */
    public function scopePublicadas(Builder $consulta): void
    {
        $consulta->where('activo', true)->orderBy('orden');
    }
}
