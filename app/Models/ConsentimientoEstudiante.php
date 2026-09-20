<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoConsentimiento;
use Database\Factories\ConsentimientoEstudianteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsentimientoEstudiante extends Model
{
    /** @use HasFactory<ConsentimientoEstudianteFactory> */
    use HasFactory;

    protected $table = 'consentimientos_estudiante';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'estudiante_id',
        'plantilla_id',
        'periodo_academico',
        'archivo_firmado_path',
        'recibido_fisico_at',
        'recibido_fisico_por',
        'estado',
        'motivo_rechazo',
        'verificado_por',
        'verificado_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoConsentimiento::class,
            'recibido_fisico_at' => 'datetime',
            'verificado_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    /** @return BelongsTo<ConsentimientoPlantilla, $this> */
    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(ConsentimientoPlantilla::class, 'plantilla_id');
    }

    /** @return BelongsTo<User, $this> */
    public function verificadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verificado_por');
    }

    /** Quién recibió el formato firmado en la puerta del laboratorio (RF53). */
    public function recibidoFisicoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibido_fisico_por');
    }

    /**
     * Si hubo entrega en físico. Convive con cualquier estado del documento
     * escaneado, así que se pregunta aparte y no por el estado.
     */
    public function entregadoEnFisico(): bool
    {
        return $this->recibido_fisico_at !== null;
    }

    /** @param Builder<$this> $consulta */
    public function scopeDelPeriodo(Builder $consulta, string $periodo): void
    {
        $consulta->where('periodo_academico', $periodo);
    }

    /** @param Builder<$this> $consulta */
    public function scopeVerificados(Builder $consulta): void
    {
        $consulta->where('estado', EstadoConsentimiento::Verificado);
    }

    /** @param Builder<$this> $consulta */
    public function scopePendientes(Builder $consulta): void
    {
        $consulta->where('estado', EstadoConsentimiento::Pendiente);
    }

    /** @param Builder<$this> $consulta */
    public function scopeConEntregaFisica(Builder $consulta): void
    {
        $consulta->whereNotNull('recibido_fisico_at');
    }

    /**
     * Lo que habilita el ingreso a prácticas (RF53): el documento verificado
     * o el formato entregado en físico en la puerta.
     *
     * @param  Builder<$this>  $consulta
     */
    public function scopeQueHabilitanPracticas(Builder $consulta): void
    {
        $consulta->where(static function (Builder $o): void {
            $o->where('estado', EstadoConsentimiento::Verificado)
                ->orWhereNotNull('recibido_fisico_at');
        });
    }
}
