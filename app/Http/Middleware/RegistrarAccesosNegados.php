<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\BitacoraService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deja en la bitácora cada 403 a alguien con sesión (B4.5): quién, qué pidió
 * y, si la ruta lo resolvió, sobre qué registro. Es lo que pregunta un
 * incidente o una consulta de la Ley 1581, y lo que delata a quien prueba
 * IDs ajenos.
 *
 * Es global y no está en cada Policy porque el 403 sale de muchos sitios
 * —abort_unless en los controladores, authorize() en los componentes, el
 * Form Request de los reportes, el rol activo, el panel del ADMIN— y aquí
 * pasan todos, también las acciones de Livewire (/livewire/update).
 *
 * Solo registra lo negado: un ID que no existe responde 404, y las
 * comprobaciones con las que una vista decide qué botones pintar no son
 * peticiones negadas.
 */
final class RegistrarAccesosNegados
{
    /** Lo que se copia de la petición a la bitácora: nombres, nunca texto libre. */
    private const NOMBRE_SEGURO = '/^[A-Za-z0-9._-]{1,80}$/';

    public function __construct(private readonly BitacoraService $bitacora) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $peticion, Closure $next): Response
    {
        $respuesta = $next($peticion);

        // Quien se desactivó con la sesión abierta llega aquí sin usuario:
        // VerificarUsuarioActivo cierra la sesión antes de responder 403.
        $usuario = $peticion->user();

        if ($respuesta->getStatusCode() !== Response::HTTP_FORBIDDEN || ! $usuario instanceof User) {
            return $respuesta;
        }

        $this->bitacora->registrarAccesoNegado($usuario, $this->registroPedido($peticion), $this->describir($peticion));

        return $respuesta;
    }

    private function registroPedido(Request $peticion): ?Model
    {
        foreach ($peticion->route()?->parameters() ?? [] as $parametro) {
            if ($parametro instanceof Model) {
                return $parametro;
            }
        }

        return null;
    }

    private function describir(Request $peticion): string
    {
        if (str_ends_with((string) $peticion->route()?->getName(), 'livewire.update')) {
            return 'Intentó '.implode('; ', $this->accionesDeLivewire($peticion)).' sin permiso.';
        }

        return sprintf('Pidió %s /%s sin permiso.', $peticion->method(), ltrim($peticion->path(), '/'));
    }

    /**
     * «registrar(15) en evaluacion.evaluaciones» por cada llamada, o
     * «actualizar …» si solo se refrescó la pantalla. De los parámetros solo
     * se copian los enteros, que son los IDs: un texto podría traer datos
     * personales. La instantánea puede no estar verificada todavía (el rol
     * activo niega antes que Livewire la lea), así que tampoco se copia un
     * nombre que no lo parezca.
     *
     * @return list<string>
     */
    private function accionesDeLivewire(Request $peticion): array
    {
        $acciones = [];

        foreach ((array) $peticion->input('components', []) as $componente) {
            if (! is_array($componente)) {
                continue;
            }

            $instantanea = json_decode(is_string($componente['snapshot'] ?? null) ? $componente['snapshot'] : '', true);
            $pantalla = $this->nombreSeguro(is_array($instantanea) ? ($instantanea['memo']['name'] ?? null) : null);
            $llamadas = is_array($componente['calls'] ?? null) ? $componente['calls'] : [];

            if ($llamadas === []) {
                $acciones[] = "actualizar {$pantalla}";
            }

            foreach ($llamadas as $llamada) {
                $metodo = $this->nombreSeguro(is_array($llamada) ? ($llamada['method'] ?? null) : null);
                $parametros = is_array($llamada) && is_array($llamada['params'] ?? null) ? $llamada['params'] : [];
                $ids = array_filter($parametros, static fn (mixed $valor): bool => is_int($valor) || (is_string($valor) && ctype_digit($valor)));

                $acciones[] = sprintf('%s(%s) en %s', $metodo, implode(', ', $ids), $pantalla);
            }
        }

        return $acciones === [] ? ['una acción de Livewire'] : $acciones;
    }

    private function nombreSeguro(mixed $valor): string
    {
        return is_string($valor) && preg_match(self::NOMBRE_SEGURO, $valor) === 1 ? $valor : '?';
    }
}
