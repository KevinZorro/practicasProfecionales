<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\VideoInstitucionalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'url',
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
