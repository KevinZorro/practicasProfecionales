<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CasoClinico;
use App\Models\Certificacion;
use App\Models\EquipoDestacado;
use App\Models\EstadisticaLanding;
use App\Models\Evento;
use App\Models\GaleriaFoto;
use App\Models\PerfilDocente;
use App\Models\Taller;
use Illuminate\Support\Collection;

/**
 * Todo lo que la portada pública muestra, ya filtrado: solo lo publicado, y
 * sin imágenes cuyo archivo no está en el disco (RF01–RF07).
 */
final readonly class ContenidoDePortada
{
    /**
     * @param  Collection<int, EstadisticaLanding>  $cifras
     * @param  Collection<int, EquipoDestacado>  $equipos  el primero es el principal
     * @param  Collection<int, CasoClinico>  $escenarios
     * @param  Collection<int, Taller>  $talleres
     * @param  Collection<int, Evento>  $eventos
     * @param  Collection<int, GaleriaFoto>  $fotos
     * @param  Collection<int, Certificacion>  $certificaciones
     * @param  Collection<int, PerfilDocente>  $docentes
     */
    public function __construct(
        public string $titulo,
        public ?string $subtitulo,
        public ?string $video,
        public ?string $tipoDeVideo,
        public Collection $cifras,
        public Collection $equipos,
        public Collection $escenarios,
        public Collection $talleres,
        public Collection $eventos,
        public Collection $fotos,
        public Collection $certificaciones,
        public Collection $docentes,
        public ?string $correo,
        public ?string $telefono,
        public ?string $direccion,
    ) {}

    public function hayOfertaAcademica(): bool
    {
        return $this->talleres->isNotEmpty() || $this->eventos->isNotEmpty();
    }

    public function hayContacto(): bool
    {
        return $this->correo !== null || $this->telefono !== null || $this->direccion !== null;
    }
}
