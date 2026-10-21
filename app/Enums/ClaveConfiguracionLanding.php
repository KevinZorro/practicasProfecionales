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
    /** Un ModoCifraDeEstudiantes; sin valor guardado rige el automático. */
    case EstudiantesModo = 'estudiantes_modo';
    /** Cifra escrita por el ADMIN; solo se publica en modo manual. */
    case EstudiantesManual = 'estudiantes_manual';
}
