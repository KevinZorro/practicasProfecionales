<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dónde estuvo una sala desde cierta fecha (RF65). Solo se añaden filas: las
 * escribe SalaService al crear o reubicar la sala.
 */
class UbicacionDeSala extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ubicaciones_sala';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sala_id',
        'bloque',
        'piso',
        'numero',
        'registrada_por',
    ];

    /** @return BelongsTo<Sala, $this> */
    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class);
    }

    /** @return BelongsTo<User, $this> */
    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por');
    }

    public function descripcion(): string
    {
        return Sala::describirUbicacion($this->bloque, $this->piso, $this->numero) ?? '';
    }
}
