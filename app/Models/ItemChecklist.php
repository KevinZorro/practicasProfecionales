<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ItemChecklistFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemChecklist extends Model
{
    /** @use HasFactory<ItemChecklistFactory> */
    use HasFactory;

    protected $table = 'items_checklist';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tipo_evaluacion_id',
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

    /** @return BelongsTo<TipoEvaluacion, $this> */
    public function tipoEvaluacion(): BelongsTo
    {
        return $this->belongsTo(TipoEvaluacion::class);
    }
}
