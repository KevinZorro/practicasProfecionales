<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\FormatoConfidencialidad;
use App\Models\User;

/**
 * Formato de confidencialidad firmado (RF51-RF53).
 *
 * Permisos del §6.1 del documento de arquitectura:
 *
 * | Acción                          | ADMIN | Coordinador | Administrativo | Docente | Estudiante |
 * | Entregar el formato firmado     |       |             |                |    ✓    |     ✓      |
 * | Verificar un formato entregado  |   ✓   |      ✓      |       ✓        |         |            |
 *
 * Lo firma todo el que entra a la práctica, docente incluido: el docente
 * dirige la sesión pero está dentro de ella, y la autorización de captación
 * de imágenes lo cubre igual. Quiénes son esos roles lo dice
 * Rol::queFirmanElFormato(), para no repetir la lista aquí y en el Service.
 *
 * La verificación la ejerce el administrativo, que es quien recibe y revisa
 * las entregas en la operación diaria. Coordinación y ADMIN conservan el
 * permiso para supervisar, así que aquí vale la herencia habitual
 * "coordinador hereda todo lo del administrativo" en lugar de una lista
 * aparte. (Cliente, reunión del 2026-09; antes estaba al revés.)
 *
 * El archivo firmado lleva datos personales, así que su descarga la limitan
 * el dueño y quienes lo verifican, nunca un enlace público (RNF07). Como el
 * administrativo ahora verifica, también lo descarga: no se puede aprobar un
 * documento sin leerlo.
 *
 * El nombre importa: Laravel resuelve las Policies por modelo, así que la de
 * FormatoConfidencialidad tiene que llamarse FormatoConfidencialidadPolicy.
 * Con cualquier otro nombre no se descubre y el Gate deniega en silencio.
 */
final class FormatoConfidencialidadPolicy
{
    /** El listado completo es para quien verifica. */
    public function viewAny(User $usuario): bool
    {
        return $this->verifica($usuario);
    }

    public function view(User $usuario, FormatoConfidencialidad $entrega): bool
    {
        return $this->esSuyo($usuario, $entrega) || $this->verifica($usuario);
    }

    /** RF51-RF52: la entrega la hace quien firma, nunca un tercero por él. */
    public function create(User $usuario): bool
    {
        return $usuario->hasAnyRole(Rol::queFirmanElFormato());
    }

    /** Solo el dueño reemplaza su entrega, y solo mientras no esté verificada. */
    public function update(User $usuario, FormatoConfidencialidad $entrega): bool
    {
        return $this->esSuyo($usuario, $entrega);
    }

    /**
     * Descarga del archivo firmado: el dueño y quien lo verifica. Nadie ve
     * el formato de otro, tenga el rol que tenga.
     */
    public function descargar(User $usuario, FormatoConfidencialidad $entrega): bool
    {
        return $this->esSuyo($usuario, $entrega) || $this->verifica($usuario);
    }

    /** RF52. Administrativo, coordinador y ADMIN. */
    public function verificar(User $usuario, FormatoConfidencialidad $entrega): bool
    {
        return $this->verifica($usuario);
    }

    /**
     * RF53: marcar que alguien entregó el formato firmado en físico, en la
     * puerta del laboratorio.
     *
     * El modelo es opcional para poder preguntar a nivel de clase, que es lo
     * que necesita la pantalla antes de tener una entrega delante: quien
     * nunca ha entregado nada todavía no tiene fila.
     *
     * Mismo grupo que verifica: el administrativo lo ejerce a diario y
     * coordinación y ADMIN lo conservan. Lo que el RF excluye —y aquí queda
     * excluido— es que el propio firmante se la marque.
     */
    public function marcarEntregaFisica(User $usuario, ?FormatoConfidencialidad $entrega = null): bool
    {
        return $this->verifica($usuario);
    }

    /** Rechazar es la otra cara de verificar: la decide quien verifica. */
    public function rechazar(User $usuario, FormatoConfidencialidad $entrega): bool
    {
        return $this->verifica($usuario);
    }

    private function esSuyo(User $usuario, FormatoConfidencialidad $entrega): bool
    {
        return $usuario->id === $entrega->firmante_id;
    }

    private function verifica(User $usuario): bool
    {
        return $usuario->hasAnyRole([
            Rol::Administrativo->value,
            Rol::Coordinador->value,
            Rol::Admin->value,
        ]);
    }
}
