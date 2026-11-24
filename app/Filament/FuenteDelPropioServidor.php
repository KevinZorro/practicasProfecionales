<?php

declare(strict_types=1);

namespace App\Filament;

use Filament\FontProviders\Contracts\FontProvider;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * La hoja de la fuente del panel, servida desde el propio servidor, con su
 * dirección armada al pintar la página y no al arrancar la aplicación.
 *
 * El LocalFontProvider de Filament recibe la dirección ya armada en
 * AdminPanelProvider, que corre antes que UsarLaDireccionConfigurada: con
 * un túnel a localhost:8080 salía http://localhost/fonts/..., sin el
 * puerto, y la CSP la bloqueaba. Aquí llega la ruta relativa a public/ y
 * asset() la completa con APP_URL ya aplicada.
 */
final class FuenteDelPropioServidor implements FontProvider
{
    public function getHtml(string $family, ?string $url = null): Htmlable
    {
        if (blank($url)) {
            return new HtmlString('');
        }

        return new HtmlString('<link href="'.e(asset($url)).'" rel="stylesheet" />');
    }
}
