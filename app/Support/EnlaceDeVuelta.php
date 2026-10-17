<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * El enlace «volver» de una pantalla: a dónde lleva y qué dice.
 */
final readonly class EnlaceDeVuelta
{
    public function __construct(
        public string $url,
        public string $etiqueta,
    ) {}

    /**
     * Vuelta a la lista de la que cuelga una pantalla (el inventario, para
     * la edición de un ítem), con los filtros que tenía puestos.
     *
     * Las listas guardan sus filtros en la URL. Si se llegó desde ella, se
     * vuelve a esa misma URL; si no, a la lista sin filtros. Solo se acepta
     * una URL de este mismo sitio y de esa misma lista: el encabezado lo
     * manda el navegador y no puede llevar a ninguna otra parte.
     */
    public static function aLaLista(Request $peticion, string $ruta, string $etiqueta): self
    {
        $lista = route($ruta);
        $referer = $peticion->headers->get('referer');

        if (self::esLaMismaPantalla($peticion, $referer, $lista)) {
            return new self((string) $referer, $etiqueta);
        }

        return new self($lista, $etiqueta);
    }

    /**
     * Si la URL es de este sitio y apunta a la misma pantalla que $pantalla,
     * sin mirar la consulta: la bandeja filtrada sigue siendo la bandeja.
     */
    public static function esLaMismaPantalla(Request $peticion, ?string $url, string $pantalla): bool
    {
        if ($url === null || ! str_starts_with($url, $peticion->getSchemeAndHttpHost().'/')) {
            return false;
        }

        return parse_url($url, PHP_URL_PATH) === parse_url($pantalla, PHP_URL_PATH);
    }
}
