<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PeriodoAcademicoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Periodo académico que el laboratorio abre y cierra a mano (RF75).
 *
 * Quién y cuándo quedan fuera de $fillable: los escribe PeriodoAcademicoService.
 */
class PeriodoAcademico extends Model
{
    /** @use HasFactory<PeriodoAcademicoFactory> */
    use HasFactory;

    protected $table = 'periodos_academicos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'abierto_at' => 'datetime',
            'cerrado_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function abiertoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'abierto_por');
    }

    /** @return BelongsTo<User, $this> */
    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    public function estaAbierto(): bool
    {
        return $this->cerrado_at === null;
    }

    /** @param Builder<$this> $consulta */
    public function scopeAbiertos(Builder $consulta): void
    {
        $consulta->whereNull('cerrado_at');
    }

    /** @param Builder<$this> $consulta */
    public function scopeCerrados(Builder $consulta): void
    {
        $consulta->whereNotNull('cerrado_at');
    }
}
