<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CapacidadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Capacidad extends Model
{
    /** @use HasFactory<CapacidadFactory> */
    use HasFactory;

    protected $table = 'capacidades';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'icono',
    ];

    /** @return BelongsToMany<CasoClinico, $this> */
    public function casosClinicos(): BelongsToMany
    {
        return $this->belongsToMany(CasoClinico::class, 'caso_clinico_capacidad');
    }
}
