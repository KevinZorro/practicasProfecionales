<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\TipoSesion;
use App\Mail\AvisoFormatoIntramuralMail;
use App\Mail\ContrasenaCambiadaMail;
use App\Mail\RecuperarContrasenaMail;
use App\Mail\SalaAsignadaMail;
use App\Mail\SesionReprogramadaMail;
use App\Mail\SincronizacionDetenidaMail;
use App\Mail\SolicitudAprobadaMail;
use App\Mail\SolicitudRechazadaMail;
use App\Mail\SustitucionDocenteMail;
use App\Models\CasoClinico;
use App\Models\Materia;
use App\Models\Preparacion;
use App\Models\Reprogramacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\Sustitucion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;

/**
 * Un ejemplar de cada correo de la aplicación, armado con modelos en memoria
 * que nunca se guardan: sirve para ver en un buzón real cómo llegan sin
 * montar solicitudes, salas ni docentes de prueba (correos:muestra).
 *
 * Todos los datos son inventados salvo el nombre de quien los recibe.
 */
final class MuestrasDeCorreo
{
    /** @return array<string, Mailable> nombre de la clase => correo */
    public function para(string $nombre): array
    {
        $destinatario = $this->persona($nombre);
        $docente = $this->persona('Docente de ejemplo');
        $reemplazo = $this->persona('Docente reemplazo de ejemplo');
        $sesion = $this->sesion($destinatario);

        return [
            'RecuperarContrasenaMail' => new RecuperarContrasenaMail($destinatario, route('acceso.recuperar'), 60),
            'ContrasenaCambiadaMail' => new ContrasenaCambiadaMail($destinatario),
            'SolicitudAprobadaMail' => new SolicitudAprobadaMail($sesion),
            'SolicitudRechazadaMail' => new SolicitudRechazadaMail($sesion),
            'SalaAsignadaMail' => new SalaAsignadaMail($this->preparacion($sesion), esCambio: false),
            'SesionReprogramadaMail' => new SesionReprogramadaMail($this->reprogramacion($sesion)),
            'SustitucionDocenteMail' => new SustitucionDocenteMail($this->sustitucion($sesion, $docente, $destinatario)),
            'AvisoFormatoIntramuralMail' => new AvisoFormatoIntramuralMail($destinatario, new Collection([$sesion, $this->sesion($reemplazo)]), paraDocente: false),
            'SincronizacionDetenidaMail' => new SincronizacionDetenidaMail(porDesactivar: 42, activas: 120, umbral: 10),
        ];
    }

    private function persona(string $nombre): User
    {
        return (new User)->forceFill(['nombre' => $nombre]);
    }

    private function sesion(User $docente): Solicitud
    {
        $sesion = (new Solicitud)->forceFill([
            'tipo' => TipoSesion::Practica,
            'fecha' => now()->addDays(3)->toDateString(),
            'hora_inicio' => '08:00:00',
            'hora_fin' => '10:00:00',
            'cantidad_estudiantes' => 12,
            'observaciones_aprobacion' => 'Ejemplo: falta un monitor de signos vitales en esa franja.',
            'motivo_rechazo' => 'Ejemplo: el escenario ya está reservado a esa hora.',
        ]);

        return $sesion
            ->setRelation('docente', $docente)
            ->setRelation('docenteQueDicta', null)
            ->setRelation('materia', (new Materia)->forceFill(['nombre' => 'Materia de ejemplo']))
            ->setRelation('casoClinico', $this->caso('Caso clínico de ejemplo'));
    }

    private function caso(string $nombre): CasoClinico
    {
        return (new CasoClinico)->forceFill(['nombre' => $nombre]);
    }

    private function preparacion(Solicitud $sesion): Preparacion
    {
        $sala = (new Sala)->forceFill(['nombre' => 'Sala de ejemplo', 'bloque' => 'A', 'piso' => '2', 'numero' => '3']);

        return (new Preparacion)
            ->setRelation('solicitud', $sesion)
            ->setRelation('salas', new Collection([$sala]));
    }

    private function reprogramacion(Solicitud $sesion): Reprogramacion
    {
        $reprogramacion = (new Reprogramacion)->forceFill([
            'fecha_anterior' => now()->addDays(3)->toDateString(),
            'hora_inicio_anterior' => '08:00:00',
            'hora_fin_anterior' => '10:00:00',
            'fecha_nueva' => now()->addDays(5)->toDateString(),
            'hora_inicio_nueva' => '14:00:00',
            'hora_fin_nueva' => '16:00:00',
            'motivo' => 'Ejemplo: mantenimiento del simulador.',
        ]);

        return $reprogramacion
            ->setRelation('solicitud', $sesion)
            ->setRelation('casoClinicoAnterior', $this->caso('Caso clínico de ejemplo'))
            ->setRelation('casoClinicoNuevo', $this->caso('Otro caso clínico de ejemplo'));
    }

    private function sustitucion(Solicitud $sesion, User $anterior, User $nuevo): Sustitucion
    {
        return (new Sustitucion)->forceFill(['motivo' => 'Ejemplo: incapacidad médica del docente titular.'])
            ->setRelation('solicitud', $sesion)
            ->setRelation('docenteAnterior', $anterior)
            ->setRelation('docenteNuevo', $nuevo);
    }
}
