<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CasoClinicoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CasoClinico extends Model
{
    /** @use HasFactory<CasoClinicoFactory> */
    use HasFactory;

    protected $table = 'casos_clinicos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'descripcion',
        'imagen',
        'visible_publico',
        'orden',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visible_publico' => 'boolean',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsToMany<Materia, $this> */
    public function materias(): BelongsToMany
    {
        return $this->belongsToMany(Materia::class, 'caso_clinico_materia');
    }

    /** @return BelongsToMany<Capacidad, $this> */
    public function capacidades(): BelongsToMany
    {
        return $this->belongsToMany(Capacidad::class, 'caso_clinico_capacidad');
    }

    /**
     * Inventario que el caso necesita, con la cantidad de cada ítem. Es la
     * relación que alimenta la precarga de equipos al crear una solicitud.
     *
     * @return BelongsToMany<ItemInventario, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(ItemInventario::class, 'caso_clinico_item', 'caso_clinico_id', 'item_inventario_id')
            ->withPivot('cantidad');
    }

    /** @return HasMany<Solicitud, $this> */
    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class);
    }

    /** @param Builder<$this> $consulta */
    public function scopeActivos(Builder $consulta): void
    {
        $consulta->where('activo', true);
    }

    /** @param Builder<$this> $consulta */
    public function scopeVisiblesEnPublico(Builder $consulta): void
    {
        $consulta->where('visible_publico', true)->where('activo', true)->orderBy('orden');
    }
}
