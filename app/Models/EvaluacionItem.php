<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EvaluacionItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EvaluacionItem extends Model
{
    /** @use HasFactory<EvaluacionItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'evaluacion_id',
        'descripcion',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
        ];
    }

    /** @return BelongsTo<Evaluacion, $this> */
    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(Evaluacion::class);
    }

    /** @return BelongsToMany<EvaluacionEstudiante, $this> */
    public function evaluacionEstudiantes(): BelongsToMany
    {
        return $this->belongsToMany(
            EvaluacionEstudiante::class,
            'evaluacion_estudiante_item',
            'evaluacion_item_id',
            'evaluacion_estudiante_id',
        )->withPivot('cumplido');
    }
}
