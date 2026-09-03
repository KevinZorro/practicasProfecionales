<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoEvento;
use Database\Factories\EventoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'tipo',
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
            'tipo' => TipoEvento::class,
            'abierto_publico' => 'boolean',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /** @param Builder<$this> $consulta */
    public function scopePublicados(Builder $consulta): void
    {
        $consulta->where('activo', true)->orderBy('orden');
    }
}
