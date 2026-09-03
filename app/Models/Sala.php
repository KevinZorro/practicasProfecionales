<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SalaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    /** @param Builder<$this> $consulta */
    public function scopeActivas(Builder $consulta): void
    {
        $consulta->where('activo', true);
    }
}
