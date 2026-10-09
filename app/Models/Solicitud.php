<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoSolicitud;
use App\Enums\OrigenSolicitud;
use App\Enums\TipoSesion;
use Database\Factories\SolicitudFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Solicitud extends Model
{
    /** @use HasFactory<SolicitudFactory> */
    use HasFactory;

    protected $table = 'solicitudes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'docente_id',
        'materia_id',
        'caso_clinico_id',
        'tipo',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'cantidad_estudiantes',
        'estado',
        'revisada_por',
        'revisada_at',
        'resuelta_por',
        'resuelta_at',
        'motivo_rechazo',
        'observaciones',
        'grupo',
        'origen',
        'registrada_por',
        'formato_intramural_at',
        'formato_intramural_por',
        'docente_que_dicta_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoSesion::class,
            'estado' => EstadoSolicitud::class,
            'fecha' => 'date',
            'cantidad_estudiantes' => 'integer',
            'revisada_at' => 'datetime',
            'resuelta_at' => 'datetime',
            'origen' => OrigenSolicitud::class,
            'formato_intramural_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function docente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docente_id');
    }

    /**
     * Quién registró la sesión apartada (RF57). Nulo si la pidió el docente.
     *
     * @return BelongsTo<User, $this>
     */
    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por');
    }

    /**
     * El reemplazo vigente (RF73). Nulo mientras dicte el titular.
     *
     * @return BelongsTo<User, $this>
     */
    public function docenteQueDicta(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docente_que_dicta_id');
    }

    /** Quién dicta la sesión: el reemplazo si lo hay, si no el titular. */
    public function idDelDocenteQueDicta(): int
    {
        return $this->docente_que_dicta_id ?? $this->docente_id;
    }

    /** @return HasMany<Reprogramacion, $this> */
    public function reprogramaciones(): HasMany
    {
        return $this->hasMany(Reprogramacion::class)->latest('created_at')->latest('id');
    }

    /** @return HasMany<Sustitucion, $this> */
    public function sustituciones(): HasMany
    {
        return $this->hasMany(Sustitucion::class)->latest('created_at')->latest('id');
    }

    /** @return BelongsTo<User, $this> */
    public function formatoIntramuralPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'formato_intramural_por');
    }

    /** Si ya tiene los insumos, equipos y simuladores de la sesión (RF59). */
    public function tieneFormatoIntramural(): bool
    {
        return $this->formato_intramural_at !== null;
    }

    /** @param Builder<$this> $consulta */
    public function scopeSinFormatoIntramural(Builder $consulta): void
    {
        $consulta->whereNull('formato_intramural_at');
    }

    /** @param Builder<$this> $consulta */
    public function scopeApartadas(Builder $consulta): void
    {
        $consulta->where('origen', OrigenSolicitud::RegistroPrevio);
    }

    /** @return BelongsTo<Materia, $this> */
    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class);
    }

    /** @return BelongsTo<CasoClinico, $this> */
    public function casoClinico(): BelongsTo
    {
        return $this->belongsTo(CasoClinico::class);
    }

    /** @return BelongsTo<User, $this> */
    public function revisadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisada_por');
    }

    /** @return BelongsTo<User, $this> */
    public function resueltaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelta_por');
    }

    /**
     * Los estudiantes que el docente puso en esta sesión (RF28), también los
     * retirados (RF69): se marcan, no se quitan de la lista.
     *
     * @return BelongsToMany<User, $this, EstudianteDeLaSesion, 'participacion'>
     */
    public function estudiantes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'estudiante_solicitud', 'solicitud_id', 'estudiante_id')
            ->using(EstudianteDeLaSesion::class)
            ->as('participacion')
            ->withPivot(['retirado_at', 'retirado_por', 'motivo_retiro'])
            ->withTimestamps();
    }

    /**
     * Los que siguen en la sesión.
     *
     * @return BelongsToMany<User, $this, EstudianteDeLaSesion, 'participacion'>
     */
    public function estudiantesPresentes(): BelongsToMany
    {
        return $this->estudiantes()->wherePivotNull('retirado_at');
    }

    /** @return BelongsToMany<ItemInventario, $this> */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(ItemInventario::class, 'solicitud_item', 'solicitud_id', 'item_inventario_id')
            ->withPivot('cantidad');
    }

    /** @return HasOne<Preparacion, $this> */
    public function preparacion(): HasOne
    {
        return $this->hasOne(Preparacion::class);
    }

    /** @return HasOne<Evaluacion, $this> */
    public function evaluacion(): HasOne
    {
        return $this->hasOne(Evaluacion::class);
    }

    /** @param Builder<$this> $consulta */
    public function scopeEnEstado(Builder $consulta, EstadoSolicitud $estado): void
    {
        $consulta->where('estado', $estado);
    }

    /** @param Builder<$this> $consulta */
    public function scopeAprobadas(Builder $consulta): void
    {
        $consulta->where('estado', EstadoSolicitud::Aprobada);
    }

    /**
     * Esperando que un administrativo las revise.
     *
     * @param  Builder<$this>  $consulta
     */
    public function scopePendientesDeRevision(Builder $consulta): void
    {
        $consulta->where('estado', EstadoSolicitud::Pendiente);
    }

    /**
     * Ya revisadas, esperando que el coordinador las resuelva.
     *
     * @param  Builder<$this>  $consulta
     */
    public function scopePendientesDeAprobacion(Builder $consulta): void
    {
        $consulta->where('estado', EstadoSolicitud::Revisada);
    }

    /** @param Builder<$this> $consulta */
    public function scopeDelDocente(Builder $consulta, User $docente): void
    {
        $consulta->where('docente_id', $docente->id);
    }

    /**
     * Las que dicta este docente: las suyas sin reemplazo y las que le
     * pasaron por sustitución (RF73).
     *
     * @param  Builder<$this>  $consulta
     */
    public function scopeQueDicta(Builder $consulta, User $docente): void
    {
        $consulta->whereRaw('COALESCE(docente_que_dicta_id, docente_id) = ?', [$docente->id]);
    }

    /** @param Builder<$this> $consulta */
    public function scopeAprobadasEntre(Builder $consulta, string $desde, string $hasta): void
    {
        $consulta->where('estado', EstadoSolicitud::Aprobada)
            ->whereBetween('fecha', [$desde, $hasta]);
    }

    /**
     * Solicitudes cuya práctica se pisa con la franja dada.
     *
     * Dos franjas se solapan cuando cada una empieza antes de que la otra
     * termine, así que dos prácticas contiguas no se solapan. Es el único
     * sitio donde vive este criterio: lo usan la asignación de sala y el
     * cálculo de disponibilidad de inventario.
     *
     * @param  Builder<$this>  $consulta
     */
    public function scopeQueSeSolapanCon(Builder $consulta, string $fecha, string $horaInicio, string $horaFin): void
    {
        $consulta->whereDate($consulta->qualifyColumn('fecha'), $fecha)
            ->where($consulta->qualifyColumn('hora_inicio'), '<', $horaFin)
            ->where($consulta->qualifyColumn('hora_fin'), '>', $horaInicio);
    }

    /** @param Builder<$this> $consulta */
    public function scopeDeEvaluacion(Builder $consulta): void
    {
        $consulta->where('tipo', TipoSesion::Evaluacion);
    }

    /** @param Builder<$this> $consulta */
    public function scopeDelDia(Builder $consulta, string $fecha): void
    {
        $consulta->whereDate('fecha', $fecha);
    }
}
