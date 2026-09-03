<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MateriaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Materia extends Model
{
    /** @use HasFactory<MateriaFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'codigo',
        'nombre',
        'semestre',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'semestre' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsToMany<CasoClinico, $this> */
    public function casosClinicos(): BelongsToMany
    {
        return $this->belongsToMany(CasoClinico::class, 'caso_clinico_materia');
    }

    /** @return BelongsToMany<TipoEvaluacion, $this> */
    public function tiposEvaluacion(): BelongsToMany
    {
        return $this->belongsToMany(TipoEvaluacion::class, 'materia_tipo_evaluacion');
    }

    /** @return HasMany<Solicitud, $this> */
    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class);
    }

    /** @param Builder<$this> $consulta */
    public function scopeActivas(Builder $consulta): void
    {
        $consulta->where('activo', true);
    }

    /** @param Builder<$this> $consulta */
    public function scopeDelSemestre(Builder $consulta, int $semestre): void
    {
        $consulta->where('semestre', $semestre);
    }
}
