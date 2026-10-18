<?php

declare(strict_types=1);

namespace App\Enums;

/** Lo que queda en la bitácora de auditoría (RF62). */
enum AccionAuditada: string
{
    case SolicitudAprobada = 'solicitud_aprobada';
    case SolicitudRechazada = 'solicitud_rechazada';
    case SesionApartadaRegistrada = 'sesion_apartada_registrada';
    case SesionReprogramada = 'sesion_reprogramada';
    case DocenteSustituido = 'docente_sustituido';
    case EstudianteRetirado = 'estudiante_retirado';
    case UnidadesRetiradas = 'unidades_retiradas';
    case UnidadesDadasDeBaja = 'unidades_dadas_de_baja';
    case RolAsignado = 'rol_asignado';
    case RolRevocado = 'rol_revocado';
    case BloqueoRegistrado = 'bloqueo_registrado';
    case BloqueoLevantado = 'bloqueo_levantado';
    case UsuarioDeshabilitado = 'usuario_deshabilitado';
    case UsuarioHabilitado = 'usuario_habilitado';
    case DocenteSupervisadoAsignado = 'docente_supervisado_asignado';
    case DocenteSupervisadoRetirado = 'docente_supervisado_retirado';
    case FormatoVerificado = 'formato_verificado';
    case FormatoRechazado = 'formato_rechazado';
    case EntregaFisicaRecibida = 'entrega_fisica_recibida';
    case PeriodoAbierto = 'periodo_abierto';
    case PeriodoCerrado = 'periodo_cerrado';
    case PeriodoReabierto = 'periodo_reabierto';
    case EvaluacionFinalizada = 'evaluacion_finalizada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::SolicitudAprobada => 'Aprobación',
            self::SolicitudRechazada => 'Rechazo',
            self::SesionApartadaRegistrada => 'Sesión apartada registrada',
            self::SesionReprogramada => 'Reprogramación',
            self::DocenteSustituido => 'Sustitución de docente',
            self::EstudianteRetirado => 'Retiro de participante',
            self::UnidadesRetiradas => 'Retiro de unidades',
            self::UnidadesDadasDeBaja => 'Baja de unidades',
            self::RolAsignado => 'Rol asignado',
            self::RolRevocado => 'Rol revocado',
            self::BloqueoRegistrado => 'Bloqueo',
            self::BloqueoLevantado => 'Bloqueo levantado',
            self::UsuarioDeshabilitado => 'Cuenta deshabilitada',
            self::UsuarioHabilitado => 'Cuenta habilitada',
            self::DocenteSupervisadoAsignado => 'Docente asignado a un supervisor',
            self::DocenteSupervisadoRetirado => 'Docente retirado de un supervisor',
            self::FormatoVerificado => 'Formato de confidencialidad verificado',
            self::FormatoRechazado => 'Formato de confidencialidad rechazado',
            self::EntregaFisicaRecibida => 'Formato de confidencialidad recibido en físico',
            self::PeriodoAbierto => 'Periodo académico abierto',
            self::PeriodoCerrado => 'Periodo académico cerrado',
            self::PeriodoReabierto => 'Periodo académico reabierto',
            self::EvaluacionFinalizada => 'Evaluación finalizada',
        };
    }
}
