<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Las claves de configuracion_landing (RF02, RF11). La tabla es de pares
 * clave-valor; qué claves existen lo dice este enum, no la tabla.
 */
enum ClaveConfiguracionLanding: string
{
    case HeroTitulo = 'hero_titulo';
    case HeroSubtitulo = 'hero_subtitulo';
    /** Ruta del video del hero en el disco público. */
    case HeroVideo = 'hero_video';
    case ContactoEmail = 'contacto_email';
    case ContactoTelefono = 'contacto_telefono';
    case ContactoDireccion = 'contacto_direccion';
}
