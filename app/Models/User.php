<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoUsuario;
use App\Enums\OrigenUsuario;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'google_id',
        'nombre',
        'email',
        'documento',
        'codigo_institucional',
        'estado',
        'origen',
        'ultima_sincronizacion',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultima_sincronizacion' => 'datetime',
            'password' => 'hashed',
            'estado' => EstadoUsuario::class,
            'origen' => OrigenUsuario::class,
        ];
    }

    /** @return HasMany<Solicitud, $this> */
    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'docente_id');
    }

    /** @return HasMany<Solicitud, $this> */
    public function solicitudesRevisadas(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'revisada_por');
    }

    /** @return HasMany<Solicitud, $this> */
    public function solicitudesResueltas(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'resuelta_por');
    }

    /** @return HasMany<Preparacion, $this> */
    public function preparaciones(): HasMany
    {
        return $this->hasMany(Preparacion::class, 'preparado_por');
    }

    /** @return HasMany<Evaluacion, $this> */
    public function evaluaciones(): HasMany
    {
        return $this->hasMany(Evaluacion::class, 'docente_id');
    }

    /** @return HasMany<EvaluacionEstudiante, $this> */
    public function resultadosEvaluacion(): HasMany
    {
        return $this->hasMany(EvaluacionEstudiante::class, 'estudiante_id');
    }

    /** @return HasMany<ConsentimientoEstudiante, $this> */
    public function consentimientos(): HasMany
    {
        return $this->hasMany(ConsentimientoEstudiante::class, 'estudiante_id');
    }

    /** @return HasOne<PerfilDocente, $this> */
    public function perfilDocente(): HasOne
    {
        return $this->hasOne(PerfilDocente::class);
    }

    /** @param Builder<$this> $consulta */
    public function scopeActivos(Builder $consulta): void
    {
        $consulta->where('estado', EstadoUsuario::Activo);
    }

    /** @param Builder<$this> $consulta */
    public function scopeInactivos(Builder $consulta): void
    {
        $consulta->where('estado', EstadoUsuario::Inactivo);
    }
}
