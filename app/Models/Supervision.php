<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un docente que un docente supervisor tiene a su cargo (RF77). Quién
 * escribe estas filas lo decide SupervisionService: por eso nada de esto
 * está en $fillable.
 */
class Supervision extends Model
{
    protected $table = 'docente_supervisor';

    /** @return BelongsTo<User, $this> */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function docente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docente_id');
    }

    /** @return BelongsTo<User, $this> */
    public function asignadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_por');
    }
}
