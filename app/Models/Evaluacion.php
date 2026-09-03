<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoEvaluacion;
use Database\Factories\EvaluacionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluacion extends Model
{
    /** @use HasFactory<EvaluacionFactory> */
    use HasFactory;

    protected $table = 'evaluaciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'solicitud_id',
        'tipo_evaluacion_id',
        'docente_id',
        'estado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoEvaluacion::class,
        ];
    }

    /** @return BelongsTo<Solicitud, $this> */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class);
    }

    /** @return BelongsTo<TipoEvaluacion, $this> */
    public function tipoEvaluacion(): BelongsTo
    {
        return $this->belongsTo(TipoEvaluacion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function docente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docente_id');
    }

    /**
     * Copia congelada del checklist con el que realmente se evaluó.
     *
     * @return HasMany<EvaluacionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(EvaluacionItem::class)->orderBy('orden');
    }

    /** @return HasMany<EvaluacionEstudiante, $this> */
    public function estudiantes(): HasMany
    {
        return $this->hasMany(EvaluacionEstudiante::class);
    }

    /** @param Builder<$this> $consulta */
    public function scopeFinalizadas(Builder $consulta): void
    {
        $consulta->where('estado', EstadoEvaluacion::Finalizada);
    }

    /** @param Builder<$this> $consulta */
    public function scopeBorradores(Builder $consulta): void
    {
        $consulta->where('estado', EstadoEvaluacion::Borrador);
    }
}
