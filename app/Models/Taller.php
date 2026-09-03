<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModalidadTaller;
use Database\Factories\TallerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Taller extends Model
{
    /** @use HasFactory<TallerFactory> */
    use HasFactory;

    protected $table = 'talleres';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'titulo',
        'descripcion',
        'imagen',
        'tema',
        'fecha',
        'modalidad',
        'muestra_formulario',
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
            'modalidad' => ModalidadTaller::class,
            'muestra_formulario' => 'boolean',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /** @return HasMany<SolicitudInformacion, $this> */
    public function solicitudesInformacion(): HasMany
    {
        return $this->hasMany(SolicitudInformacion::class);
    }

    /** @param Builder<$this> $consulta */
    public function scopePublicados(Builder $consulta): void
    {
        $consulta->where('activo', true)->orderBy('orden');
    }
}
