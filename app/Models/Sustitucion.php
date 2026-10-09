<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una sustitución del docente de una sesión (RF73). Solo se añaden filas.
 */
class Sustitucion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'sustituciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'solicitud_id',
        'docente_anterior_id',
        'docente_nuevo_id',
        'motivo',
        'registrada_por',
    ];

    /** @return BelongsTo<Solicitud, $this> */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class);
    }

    /** @return BelongsTo<User, $this> */
    public function docenteAnterior(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docente_anterior_id');
    }

    /** @return BelongsTo<User, $this> */
    public function docenteNuevo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docente_nuevo_id');
    }

    /** @return BelongsTo<User, $this> */
    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por');
    }
}
