<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Solicitud;
use App\Services\SolicitudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Calendario de escenarios aprobados (RF34). Cada rol ve solo lo suyo,
 * salvo quien gestiona las solicitudes, que ve todo (D19).
 */
final class CalendarioController extends Controller
{
    public function index(Request $peticion): View
    {
        abort_unless($peticion->user()->can('verCalendario', Solicitud::class), 403);

        return view('panel.calendario');
    }

    /**
     * Eventos del rango que pide FullCalendar. La consulta es la del Service,
     * que ya trae docente, materia, caso clínico y sala sin N+1.
     */
    public function eventos(Request $peticion, SolicitudService $solicitudes): JsonResponse
    {
        abort_unless($peticion->user()->can('verCalendario', Solicitud::class), 403);

        $datos = $peticion->validate([
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
        ]);

        return response()->json(
            $solicitudes->paraCalendario($datos['desde'], $datos['hasta'], $peticion->user())
                ->map(fn (Solicitud $solicitud): array => $this->comoEvento($solicitud))
                ->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function comoEvento(Solicitud $solicitud): array
    {
        $sala = $solicitud->preparacion?->sala?->nombre;
        $colores = $solicitud->tipo->coloresEnElCalendario();
        $fecha = $solicitud->fecha->format('Y-m-d');
        $dicta = $solicitud->docenteQueDicta ?? $solicitud->docente;
        $hora = substr($solicitud->hora_inicio, 0, 5).'–'.substr($solicitud->hora_fin, 0, 5);

        return [
            'id' => (string) $solicitud->id,
            'title' => $solicitud->casoClinico->nombre,
            'start' => "{$fecha}T{$solicitud->hora_inicio}",
            'end' => "{$fecha}T{$solicitud->hora_fin}",
            // La práctica y la evaluación se distinguen por relleno y por
            // clase, para que no dependa solo del color.
            'classNames' => ['evento-'.$solicitud->tipo->value],
            'backgroundColor' => $colores['fondo'],
            'borderColor' => $colores['borde'],
            'textColor' => $colores['texto'],
            'extendedProps' => [
                'tipo' => $solicitud->tipo->etiqueta(),
                'fecha' => $solicitud->fecha->format('d/m/Y'),
                'hora' => $hora,
                'docente' => $dicta->nombre,
                'enReemplazoDe' => $solicitud->docenteQueDicta !== null ? $solicitud->docente->nombre : null,
                'grupo' => $solicitud->grupo,
                'materia' => $solicitud->materia->nombre,
                'casoClinico' => $solicitud->casoClinico->nombre,
                'estudiantes' => (string) $solicitud->cantidad_estudiantes,
                'sala' => $sala ?? 'Aún sin asignar',
            ],
        ];
    }
}
