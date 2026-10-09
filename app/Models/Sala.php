<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SalaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sala extends Model
{
    /** @use HasFactory<SalaFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'codigo',
        'capacidad',
        'activo',
        'bloque',
        'piso',
        'numero',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacidad' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /** @return HasMany<Preparacion, $this> */
    public function preparaciones(): HasMany
    {
        return $this->hasMany(Preparacion::class);
    }

    /**
     * Escenarios que se montan en esta sala (RF65).
     *
     * @return BelongsToMany<CasoClinico, $this>
     */
    public function casosClinicos(): BelongsToMany
    {
        return $this->belongsToMany(CasoClinico::class, 'caso_clinico_sala');
    }

    /**
     * Historial de ubicaciones, la más reciente primero.
     *
     * @return HasMany<UbicacionDeSala, $this>
     */
    public function ubicaciones(): HasMany
    {
        return $this->hasMany(UbicacionDeSala::class)->latest('created_at')->latest('id');
    }

    /** "Bloque A · piso 2 · sala 3", o nulo si la sala aún no tiene ubicación. */
    public function ubicacion(): ?string
    {
        return self::describirUbicacion($this->bloque, $this->piso, $this->numero);
    }

    /** Nombre y ubicación, como se ofrece la sala al prepararla y en los correos. */
    public function nombreCompleto(): string
    {
        $ubicacion = $this->ubicacion();

        return $ubicacion === null ? $this->nombre : $this->nombre.' ('.$ubicacion.')';
    }

    public static function describirUbicacion(?string $bloque, ?string $piso, ?string $numero): ?string
    {
        if ($bloque === null || $piso === null || $numero === null) {
            return null;
        }

        return sprintf('Bloque %s · piso %s · sala %s', $bloque, $piso, $numero);
    }

    /** @param Builder<$this> $consulta */
    public function scopeActivas(Builder $consulta): void
    {
        $consulta->where('activo', true);
    }
}
