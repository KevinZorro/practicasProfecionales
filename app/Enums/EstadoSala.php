<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado del espacio físico de una sala (RF30): lo que el administrativo
 * verifica al revisar una solicitud. Es el estado de hoy, sin historial.
 *
 * No es lo mismo que salas.activo: «activo» lo decide el ADMIN y dice si la
 * sala sigue en el catálogo; el estado lo anota el administrativo y dice si
 * hoy se puede usar (el aire está dañado, hay una filtración).
 */
enum EstadoSala: string
{
    /** Se usa sin reparos. */
    case Operativa = 'operativa';

    /** Se puede usar, pero con algo que avisar: el aire no enfría, una mancha de humedad. */
    case ConNovedad = 'con_novedad';

    /** No se asigna a ninguna sesión hasta que vuelva a estar operativa. */
    case FueraDeServicio = 'fuera_de_servicio';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Operativa => 'Operativa',
            self::ConNovedad => 'Con novedad',
            self::FueraDeServicio => 'Fuera de servicio',
        };
    }

    /** Lo que no es operativo lleva una nota que diga qué pasa. */
    public function exigeNovedad(): bool
    {
        return $this !== self::Operativa;
    }
}
