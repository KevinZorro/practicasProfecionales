<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TituloDocenteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TituloDocente extends Model
{
    /** @use HasFactory<TituloDocenteFactory> */
    use HasFactory;

    protected $table = 'titulos_docente';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'perfil_docente_id',
        'titulo',
        'institucion',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
        ];
    }

    /** @return BelongsTo<PerfilDocente, $this> */
    public function perfilDocente(): BelongsTo
    {
        return $this->belongsTo(PerfilDocente::class);
    }
}
