<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GaleriaFotoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GaleriaFoto extends Model
{
    /** @use HasFactory<GaleriaFotoFactory> */
    use HasFactory;

    protected $table = 'galeria_fotos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'titulo',
        'imagen_path',
        'orden',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /** @param Builder<$this> $consulta */
    public function scopePublicadas(Builder $consulta): void
    {
        $consulta->where('activo', true)->orderBy('orden');
    }
}
