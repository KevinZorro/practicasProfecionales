<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SolicitudInformacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudInformacion extends Model
{
    /** @use HasFactory<SolicitudInformacionFactory> */
    use HasFactory;

    protected $table = 'solicitudes_informacion';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'taller_id',
        'nombre',
        'email',
        'telefono',
        'mensaje',
        'enviado_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enviado_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Taller, $this> */
    public function taller(): BelongsTo
    {
        return $this->belongsTo(Taller::class);
    }
}
