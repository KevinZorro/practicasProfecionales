<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ClaveConfiguracionLanding;
use App\Models\CasoClinico;
use App\Models\Certificacion;
use App\Models\EquipoDestacado;
use App\Models\EstadisticaLanding;
use App\Models\Evento;
use App\Models\GaleriaFoto;
use App\Models\PerfilDocente;
use App\Models\Taller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Lo que muestra la portada pública (RF01–RF07). Solo lee: todo lo gestiona
 * el ADMIN desde /admin (RF10–RF17).
 *
 * Una imagen registrada cuyo archivo ya no está en el disco se trata como
 * si no hubiera foto: la portada tiene un estado sin foto para cada bloque,
 * y una imagen rota se ve peor que ninguna.
 */
final class PortadaService
{
    /** Los otros escenarios que se sugieren al final del detalle de uno. */
    public const OTROS_ESCENARIOS = 3;

    public function __construct(private readonly ConfiguracionLandingService $configuracion) {}

    public function contenido(): ContenidoDePortada
    {
        $valores = $this->configuracion->valores();
        $video = $this->siExiste($valores[ClaveConfiguracionLanding::HeroVideo->value]);

        return new ContenidoDePortada(
            titulo: $valores[ClaveConfiguracionLanding::HeroTitulo->value] ?? (string) config('app.name'),
            subtitulo: $valores[ClaveConfiguracionLanding::HeroSubtitulo->value],
            video: $video,
            tipoDeVideo: $video === null ? null : $this->tipoDeVideo($video),
            cifras: EstadisticaLanding::query()->publicadas()->get(),
            equipos: $this->sinImagenesPerdidas(EquipoDestacado::query()->publicados()->get(), 'imagen'),
            escenarios: $this->sinImagenesPerdidas(
                CasoClinico::query()->visiblesEnPublico()->with('capacidades:id,nombre')->get(),
                'imagen',
            ),
            talleres: $this->sinImagenesPerdidas($this->vigentes(Taller::query()->publicados())->get(), 'imagen'),
            eventos: $this->sinImagenesPerdidas(
                $this->vigentes(Evento::query()->publicados())->with('tipoEvento:id,nombre')->get(),
                'imagen',
            ),
            fotos: GaleriaFoto::query()->publicadas()->get()
                ->filter(fn (GaleriaFoto $foto): bool => $this->siExiste($foto->imagen_path) !== null)
                ->values(),
            certificaciones: $this->sinImagenesPerdidas(Certificacion::query()->publicadas()->get(), 'imagen_insignia'),
            docentes: $this->sinImagenesPerdidas(PerfilDocente::query()->publicados()->with('titulos')->get(), 'foto'),
            correo: $valores[ClaveConfiguracionLanding::ContactoEmail->value],
            telefono: $valores[ClaveConfiguracionLanding::ContactoTelefono->value],
            direccion: $valores[ClaveConfiguracionLanding::ContactoDireccion->value],
        );
    }

    /**
     * El detalle ampliado de un escenario (RF03). Uno que no está publicado
     * no existe para el público: 404.
     */
    public function escenarioPublicado(int $id): CasoClinico
    {
        $escenario = CasoClinico::query()
            ->visiblesEnPublico()
            ->with(['capacidades:id,nombre', 'items:id,nombre'])
            ->findOrFail($id);

        return $this->sinImagenesPerdidas(new Collection([$escenario]), 'imagen')->first() ?? $escenario;
    }

    /** @return Collection<int, CasoClinico> */
    public function otrosEscenarios(CasoClinico $escenario): Collection
    {
        return $this->sinImagenesPerdidas(
            CasoClinico::query()
                ->visiblesEnPublico()
                ->whereKeyNot($escenario->getKey())
                ->limit(self::OTROS_ESCENARIOS)
                ->get(),
            'imagen',
        );
    }

    /**
     * Un taller o un evento con la fecha ya pasada no es oferta: se queda en
     * /admin, pero la portada no lo anuncia. Hoy se cuenta en la zona de la
     * aplicación.
     *
     * @template TModelo of Taller|Evento
     *
     * @param  Builder<TModelo>  $consulta
     * @return Builder<TModelo>
     */
    private function vigentes(Builder $consulta): Builder
    {
        return $consulta->whereDate('fecha', '>=', now()->toDateString());
    }

    /**
     * Solo en memoria: la columna queda en null para que la vista pinte el
     * estado sin foto. No se guarda nada.
     *
     * @template TModelo of Model
     *
     * @param  Collection<int, TModelo>  $modelos
     * @return Collection<int, TModelo>
     */
    private function sinImagenesPerdidas(Collection $modelos, string $columna): Collection
    {
        foreach ($modelos as $modelo) {
            $ruta = $modelo->getAttribute($columna);

            if ($this->siExiste(is_string($ruta) ? $ruta : null) === null) {
                $modelo->setAttribute($columna, null);
            }
        }

        return $modelos;
    }

    private function siExiste(?string $ruta): ?string
    {
        if ($ruta === null || $ruta === '') {
            return null;
        }

        return Storage::disk(ImagenPublicaService::DISCO)->exists($ruta) ? $ruta : null;
    }

    private function tipoDeVideo(string $ruta): string
    {
        return str_ends_with(mb_strtolower($ruta), '.webm') ? 'video/webm' : 'video/mp4';
    }
}
