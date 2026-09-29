<?php

declare(strict_types=1);

namespace App\Filament;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * El avatar del menú de Filament, dibujado aquí: un círculo con las
 * iniciales, en SVG, dentro de la propia página.
 *
 * El de fábrica lo pide a ui-avatars.com, lo que manda las iniciales de cada
 * usuario a un tercero en cada carga del panel, y la política de contenido
 * (CabecerasDeSeguridad) no deja cargar imágenes de otros sitios.
 */
final class AvatarConIniciales implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $iniciales = collect(explode(' ', trim(Filament::getNameForDefaultAvatar($record))))
            ->filter()
            ->take(2)
            ->map(static fn (string $palabra): string => mb_strtoupper(mb_substr($palabra, 0, 1)))
            ->join('');

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<circle cx="32" cy="32" r="32" fill="#09090b"/>'
            .'<text x="50%%" y="50%%" dy=".35em" text-anchor="middle" fill="#ffffff" font-family="sans-serif" font-size="26">%s</text>'
            .'</svg>',
            htmlspecialchars($iniciales, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
        );

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
