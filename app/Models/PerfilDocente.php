<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PerfilDocenteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerfilDocente extends Model
{
    /** @use HasFactory<PerfilDocenteFactory> */
    use HasFactory;

    protected $table = 'perfiles_docentes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'nombre',
        'cargo',
        'foto',
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<TituloDocente, $this> */
    public function titulos(): HasMany
    {
        return $this->hasMany(TituloDocente::class)->orderBy('orden');
    }

    /** @param Builder<$this> $consulta */
    public function scopePublicados(Builder $consulta): void
    {
        $consulta->where('activo', true)->orderBy('orden');
    }
}
