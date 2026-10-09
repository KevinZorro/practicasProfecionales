<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BloqueoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bloqueo de un estudiante o docente para el uso del laboratorio (RF68).
 *
 * Se levanta, no se borra: quién y por qué quedan en la misma fila. Las
 * escribe BloqueoService.
 */
class Bloqueo extends Model
{
    /** @use HasFactory<BloqueoFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'motivo',
        'bloqueado_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'levantado_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function bloqueadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bloqueado_por');
    }

    /** @return BelongsTo<User, $this> */
    public function levantadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'levantado_por');
    }

    public function estaVigente(): bool
    {
        return $this->levantado_at === null;
    }

    /** @param Builder<$this> $consulta */
    public function scopeVigentes(Builder $consulta): void
    {
        $consulta->whereNull('levantado_at');
    }
}
