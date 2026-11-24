<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Routing\UrlGenerator;

/**
 * La dirección de APP_URL como raíz de todo enlace que arma la aplicación,
 * cuando APP_URL_FIJA está activa.
 *
 * Se aplica en dos momentos, y hacen falta los dos:
 *
 * - Al crear el generador de enlaces (AppServiceProvider), antes de que
 *   arranquen los proveedores. Lo que un paquete arma al arrancar —el panel
 *   de Filament con su fuente y su favicon— ya sale con APP_URL. Antes solo
 *   se aplicaba en el middleware, que corre después, y esas direcciones
 *   salían con el Host de la petición: http://localhost, sin el :8080.
 * - En cada petición (UsarLaDireccionConfigurada), que además corrige la
 *   propia petición para url.intended y back().
 */
final class DireccionFija
{
    public static function activa(): bool
    {
        return (bool) config('app.url_fija') && self::direccion() !== '';
    }

    public static function direccion(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public static function aplicarA(UrlGenerator $generador): void
    {
        if (! self::activa()) {
            return;
        }

        $direccion = self::direccion();
        $generador->forceRootUrl($direccion);
        $generador->forceScheme(str_starts_with($direccion, 'https://') ? 'https' : 'http');
    }
}
