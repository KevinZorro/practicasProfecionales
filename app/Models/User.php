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
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /*
     * roles() se sobrescribe abajo para filtrar por vigencia. El alias deja
     * a mano la relación original de spatie, sin filtro: es sobre ella que
     * se construye la filtrada.
     */
    use HasRoles {
        roles as rolesSinFiltrarPorVigencia;
    }

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
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
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
            'estado' => EstadoUsuario::class,
            'origen' => OrigenUsuario::class,
        ];
    }

    /**
     * Roles vigentes hoy (RF63, RF64).
     *
     * El filtro va aquí, en la relación, y no en el middleware ni en un job,
     * y esa decisión es el requisito, no una preferencia: así el vencimiento
     * se aplica en SQL a TODOS los caminos de lectura —hasRole(), can(), las
     * Policies, el scope role() de spatie, whereHas('roles') y el
     * loadMissing('roles') que el paquete hace por dentro—, en peticiones
     * HTTP, comandos, colas y tinker por igual. Si el rol vence a medianoche,
     * la comparación cambia sola en la consulta siguiente.
     *
     * spatie no cachea model_has_roles —su caché guarda permisos y roles, no
     * quién tiene cuál—, así que no hay nada que pueda quedar obsoleto.
     *
     * Nulo quiere decir "sin límite por ese lado": "hasta" nulo es un rol
     * permanente y "desde" nulo es un rol que siempre ha valido —los que
     * reparten los seeders, que no pasan por el Service—. "hasta" incluye el
     * día completo, y el corte se hace por fecha en la zona de la aplicación
     * (America/Bogota), igual que la frontera de las listas de reposición.
     *
     * Cuidado al asignar: assignRole() de spatie calcula lo que ya tiene
     * leyendo esta relación filtrada, así que con un rol vencido en la tabla
     * intentaría insertar una fila que ya existe y reventaría contra la
     * llave primaria. Toda escritura pasa por AsignacionDeRolService.
     *
     * @return MorphToMany<Role, $this>
     */
    public function roles(): MorphToMany
    {
        $pivote = config('permission.table_names.model_has_roles');
        $hoy = now()->toDateString();

        return $this->rolesSinFiltrarPorVigencia()
            ->using(VigenciaDeRol::class)
            ->withPivot(['desde', 'hasta'])
            ->where(static function (Builder $consulta) use ($pivote, $hoy): void {
                $consulta->whereNull("{$pivote}.desde")
                    ->orWhere("{$pivote}.desde", '<=', $hoy);
            })
            ->where(static function (Builder $consulta) use ($pivote, $hoy): void {
                $consulta->whereNull("{$pivote}.hasta")
                    ->orWhere("{$pivote}.hasta", '>=', $hoy);
            });
    }

    /**
     * Historial de asignaciones, vigentes y vencidas (RF63, RF64).
     *
     * @return HasMany<AsignacionDeRol, $this>
     */
    public function asignacionesDeRol(): HasMany
    {
        return $this->hasMany(AsignacionDeRol::class);
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

    /**
     * Los formatos de confidencialidad que ha firmado, uno por periodo.
     *
     * Lo firma todo el que entra a la práctica, así que esta relación vale
     * igual para un estudiante y para un docente (RF51-RF52).
     *
     * @return HasMany<FormatoConfidencialidad, $this>
     */
    public function formatosDeConfidencialidad(): HasMany
    {
        return $this->hasMany(FormatoConfidencialidad::class, 'firmante_id');
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
