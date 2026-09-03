<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TipoEvaluacionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoEvaluacion extends Model
{
    /** @use HasFactory<TipoEvaluacionFactory> */
    use HasFactory;

    protected $table = 'tipos_evaluacion';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'descripcion',
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

    /** @return BelongsToMany<Materia, $this> */
    public function materias(): BelongsToMany
    {
        return $this->belongsToMany(Materia::class, 'materia_tipo_evaluacion');
    }

    /**
     * Plantilla maestra del checklist. Al crear una evaluación estos ítems se
     * copian a evaluacion_items.
     *
     * @return HasMany<ItemChecklist, $this>
     */
    public function itemsChecklist(): HasMany
    {
        return $this->hasMany(ItemChecklist::class)->orderBy('orden');
    }

    /** @return HasMany<Evaluacion, $this> */
    public function evaluaciones(): HasMany
    {
        return $this->hasMany(Evaluacion::class);
    }

    /** @param Builder<$this> $consulta */
    public function scopeActivos(Builder $consulta): void
    {
        $consulta->where('activo', true);
    }
}
