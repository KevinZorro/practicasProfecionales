<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\VideoInstitucionalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Un video de la galería institucional de la portada (RF08, RF17): un
 * archivo MP4 o WebM en el disco público, con su portada. Nunca un enlace
 * a otro sitio (D20).
 */
class VideoInstitucional extends Model
{
    /** @use HasFactory<VideoInstitucionalFactory> */
    use HasFactory;

    protected $table = 'videos_institucionales';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'titulo',
        'video',
        'portada',
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
    public function scopePublicados(Builder $consulta): void
    {
        $consulta->where('activo', true)->orderBy('orden');
    }
}
