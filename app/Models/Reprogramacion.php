<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una reprogramación de sesión (RF61): lo que había, lo nuevo, el motivo y
 * la constancia de que se habló con el docente. Solo se añaden filas.
 */
class Reprogramacion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'reprogramaciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'solicitud_id',
        'fecha_anterior',
        'hora_inicio_anterior',
        'hora_fin_anterior',
        'caso_clinico_anterior_id',
        'fecha_nueva',
        'hora_inicio_nueva',
        'hora_fin_nueva',
        'caso_clinico_nuevo_id',
        'motivo',
        'constancia_comunicacion',
        'reprogramada_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_anterior' => 'date',
            'fecha_nueva' => 'date',
        ];
    }

    /** @return BelongsTo<Solicitud, $this> */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class);
    }

    /** @return BelongsTo<CasoClinico, $this> */
    public function casoClinicoAnterior(): BelongsTo
    {
        return $this->belongsTo(CasoClinico::class, 'caso_clinico_anterior_id');
    }

    /** @return BelongsTo<CasoClinico, $this> */
    public function casoClinicoNuevo(): BelongsTo
    {
        return $this->belongsTo(CasoClinico::class, 'caso_clinico_nuevo_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reprogramadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reprogramada_por');
    }
}
