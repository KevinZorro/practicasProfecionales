<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CertificacionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certificacion extends Model
{
    /** @use HasFactory<CertificacionFactory> */
    use HasFactory;

    protected $table = 'certificaciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'entidad',
        'imagen_insignia',
        'descripcion',
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
