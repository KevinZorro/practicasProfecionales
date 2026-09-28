<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EventoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evento extends Model
{
    /** @use HasFactory<EventoFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'titulo',
        'descripcion',
        'imagen',
        'fecha',
        'tipo_evento_id',
        'abierto_publico',
        'orden',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'abierto_publico' => 'boolean',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<TipoEvento, $this> */
    public function tipoEvento(): BelongsTo
    {
        return $this->belongsTo(TipoEvento::class);
    }

    /** @param Builder<$this> $consulta */
    public function scopePublicados(Builder $consulta): void
    {
        $consulta->where('activo', true)->orderBy('orden');
    }
}
