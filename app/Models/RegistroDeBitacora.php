<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccionAuditada;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Una entrada de la bitácora de auditoría (RF62). Solo se añaden filas: las
 * escribe BitacoraService.
 */
class RegistroDeBitacora extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'bitacora';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'accion',
        'user_id',
        'auditable_type',
        'auditable_id',
        'descripcion',
        'motivo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accion' => AccionAuditada::class,
        ];
    }

    /**
     * Quién hizo la acción.
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Sobre qué: la solicitud, el ítem, el usuario o el bloqueo.
     *
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
