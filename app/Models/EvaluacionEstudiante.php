<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ResultadoEvaluacion;
use Database\Factories\EvaluacionEstudianteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EvaluacionEstudiante extends Model
{
    /** @use HasFactory<EvaluacionEstudianteFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'evaluacion_id',
        'estudiante_id',
        'resultado',
        'intento',
        'observaciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resultado' => ResultadoEvaluacion::class,
            'intento' => 'integer',
        ];
    }

    /** @return BelongsTo<Evaluacion, $this> */
    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(Evaluacion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    /** @return BelongsToMany<EvaluacionItem, $this> */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(
            EvaluacionItem::class,
            'evaluacion_estudiante_item',
            'evaluacion_estudiante_id',
            'evaluacion_item_id',
        )->withPivot('cumplido');
    }

    /** @param Builder<$this> $consulta */
    public function scopeAprobados(Builder $consulta): void
    {
        $consulta->where('resultado', ResultadoEvaluacion::Aprobado);
    }

    /** @param Builder<$this> $consulta */
    public function scopeNoAprobados(Builder $consulta): void
    {
        $consulta->where('resultado', ResultadoEvaluacion::NoAprobado);
    }

    /** @param Builder<$this> $consulta */
    public function scopeDelEstudiante(Builder $consulta, int $estudianteId): void
    {
        $consulta->where('estudiante_id', $estudianteId);
    }
}
