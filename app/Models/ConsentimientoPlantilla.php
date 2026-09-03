<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ConsentimientoPlantillaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsentimientoPlantilla extends Model
{
    /** @use HasFactory<ConsentimientoPlantillaFactory> */
    use HasFactory;

    protected $table = 'consentimientos_plantilla';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'archivo_path',
        'version',
        'activo',
        'subido_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    /** @return HasMany<ConsentimientoEstudiante, $this> */
    public function consentimientos(): HasMany
    {
        return $this->hasMany(ConsentimientoEstudiante::class, 'plantilla_id');
    }

    /** @param Builder<$this> $consulta */
    public function scopeActivas(Builder $consulta): void
    {
        $consulta->where('activo', true);
    }
}
