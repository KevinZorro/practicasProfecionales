<?php

declare(strict_types=1);

use App\Enums\EstadoSolicitud;
use App\Enums\TipoSesion;
use App\Models\CasoClinico;
use App\Models\Materia;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\FiltroReporte;
use App\Services\ReporteService;
use Database\Seeders\RolSeeder;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->servicio = app(ReporteService::class);
});

/**
 * Una sesión aprobada con todo lo que el RF54 agrupa. Devuelve la solicitud
 * ya preparada en sala, que es de donde sale la columna "sala".
 */
function sesionAprobada(
    User $docente,
    Materia $materia,
    CasoClinico $caso,
    string $fecha,
    string $inicio,
    string $fin,
    int $estudiantes = 10,
    ?Sala $sala = null,
    TipoSesion $tipo = TipoSesion::Practica,
    EstadoSolicitud $estado = EstadoSolicitud::Aprobada,
): Solicitud {
    $solicitud = Solicitud::factory()->create([
        'docente_id' => $docente->id,
        'materia_id' => $materia->id,
        'caso_clinico_id' => $caso->id,
        'tipo' => $tipo,
        'estado' => $estado,
        'fecha' => $fecha,
        'hora_inicio' => $inicio,
        'hora_fin' => $fin,
        'cantidad_estudiantes' => $estudiantes,
    ]);

    if ($sala instanceof Sala) {
        Preparacion::factory()->create(['solicitud_id' => $solicitud->id, 'sala_id' => $sala->id]);
    }

    return $solicitud;
}

it('agrupa el uso por docente, materia, caso clínico, sala y tipo de sesión', function (): void {
    $docente = User::factory()->docente()->create(['nombre' => 'Ana Gómez']);
    $materia = Materia::factory()->create(['nombre' => 'Cuidado materno', 'semestre' => 5]);
    $caso = CasoClinico::factory()->create(['nombre' => 'Atención de parto']);
    $sala = Sala::factory()->create(['nombre' => 'Sala 1']);

    sesionAprobada($docente, $materia, $caso, '2026-04-01', '07:00:00', '09:00:00', 10, $sala);
    sesionAprobada($docente, $materia, $caso, '2026-04-08', '07:00:00', '10:00:00', 12, $sala);

    $filas = $this->servicio->usoDeEscenarios(new FiltroReporte)->get();

    expect($filas)->toHaveCount(1);
    $fila = $filas->first();
    expect($fila->docente_nombre)->toBe('Ana Gómez')
        ->and($fila->materia_nombre)->toBe('Cuidado materno')
        ->and((int) $fila->materia_semestre)->toBe(5)
        ->and($fila->caso_clinico_nombre)->toBe('Atención de parto')
        ->and($fila->sala_nombre)->toBe('Sala 1')
        ->and($fila->tipo)->toBe(TipoSesion::Practica)
        ->and((int) $fila->sesiones)->toBe(2)
        ->and((float) $fila->horas)->toBe(5.0)
        ->and((int) $fila->estudiantes)->toBe(22);
});

it('separa en renglones distintos las sesiones de práctica y las de evaluación', function (): void {
    $docente = User::factory()->docente()->create();
    $materia = Materia::factory()->create();
    $caso = CasoClinico::factory()->create();

    sesionAprobada($docente, $materia, $caso, '2026-04-01', '07:00:00', '09:00:00');
    sesionAprobada($docente, $materia, $caso, '2026-04-02', '07:00:00', '09:00:00', tipo: TipoSesion::Evaluacion);

    $filas = $this->servicio->usoDeEscenarios(new FiltroReporte)->get();

    expect($filas)->toHaveCount(2)
        ->and($filas->pluck('tipo')->all())->toEqualCanonicalizing([TipoSesion::Practica, TipoSesion::Evaluacion]);
});

it('calcula horas de franjas que no caen en horas enteras', function (string $inicio, string $fin, float $horas): void {
    $solicitud = sesionAprobada(
        User::factory()->docente()->create(),
        Materia::factory()->create(),
        CasoClinico::factory()->create(),
        '2026-04-01',
        $inicio,
        $fin,
    );

    expect((float) $this->servicio->usoDeEscenarios(new FiltroReporte)->get()->first()->horas)->toBe($horas)
        ->and($solicitud->hora_inicio)->toBe($inicio);
})->with([
    'media hora' => ['07:00:00', '07:30:00', 0.5],
    'hora y cuarto' => ['08:00:00', '09:15:00', 1.25],
    'hora y tres cuartos' => ['08:30:00', '10:15:00', 1.75],
    'diez minutos' => ['07:00:00', '07:10:00', 1 / 6],
    'jornada larga' => ['07:00:00', '17:45:00', 10.75],
]);

it('solo cuenta solicitudes aprobadas', function (EstadoSolicitud $estado): void {
    $docente = User::factory()->docente()->create();
    $materia = Materia::factory()->create();
    $caso = CasoClinico::factory()->create();

    sesionAprobada($docente, $materia, $caso, '2026-04-01', '07:00:00', '09:00:00', estado: $estado);

    expect($this->servicio->usoDeEscenarios(new FiltroReporte)->get())->toBeEmpty();
})->with([
    'pendiente' => EstadoSolicitud::Pendiente,
    'revisada' => EstadoSolicitud::Revisada,
    'rechazada' => EstadoSolicitud::Rechazada,
]);

it('marca como "Sin asignar" el uso de una solicitud que nunca recibió sala', function (): void {
    sesionAprobada(
        User::factory()->docente()->create(),
        Materia::factory()->create(),
        CasoClinico::factory()->create(),
        '2026-04-01',
        '07:00:00',
        '09:00:00',
    );

    expect($this->servicio->usoDeEscenarios(new FiltroReporte)->get()->first()->sala_nombre)->toBe('Sin asignar');
});

it('filtra por rango de fechas', function (): void {
    $docente = User::factory()->docente()->create();
    $materia = Materia::factory()->create();
    $caso = CasoClinico::factory()->create();

    sesionAprobada($docente, $materia, $caso, '2026-03-15', '07:00:00', '09:00:00');
    sesionAprobada($docente, $materia, $caso, '2026-04-15', '07:00:00', '10:00:00');
    sesionAprobada($docente, $materia, $caso, '2026-05-15', '07:00:00', '09:00:00');

    $abril = $this->servicio->usoDeEscenarios(new FiltroReporte(desde: '2026-04-01', hasta: '2026-04-30'))->get();

    expect($abril)->toHaveCount(1)
        ->and((float) $abril->first()->horas)->toBe(3.0);
});

it('incluye los días extremos del rango', function (): void {
    $docente = User::factory()->docente()->create();
    $materia = Materia::factory()->create();
    $caso = CasoClinico::factory()->create();

    sesionAprobada($docente, $materia, $caso, '2026-04-01', '07:00:00', '09:00:00');
    sesionAprobada($docente, $materia, $caso, '2026-04-30', '07:00:00', '09:00:00');

    $filas = $this->servicio->usoDeEscenarios(new FiltroReporte(desde: '2026-04-01', hasta: '2026-04-30'))->get();

    expect((int) $filas->first()->sesiones)->toBe(2);
});

it('filtra por docente, por materia y por sala', function (): void {
    $ana = User::factory()->docente()->create(['nombre' => 'Ana']);
    $beto = User::factory()->docente()->create(['nombre' => 'Beto']);
    $materiaUno = Materia::factory()->create(['nombre' => 'Materia uno']);
    $materiaDos = Materia::factory()->create(['nombre' => 'Materia dos']);
    $salaUno = Sala::factory()->create(['nombre' => 'Sala 1']);
    $salaDos = Sala::factory()->create(['nombre' => 'Sala 2']);
    $caso = CasoClinico::factory()->create();

    sesionAprobada($ana, $materiaUno, $caso, '2026-04-01', '07:00:00', '09:00:00', 10, $salaUno);
    sesionAprobada($beto, $materiaDos, $caso, '2026-04-02', '07:00:00', '09:00:00', 10, $salaDos);

    expect($this->servicio->usoDeEscenarios(new FiltroReporte(docenteId: $ana->id))->get())->toHaveCount(1)
        ->and($this->servicio->usoDeEscenarios(new FiltroReporte(docenteId: $ana->id))->get()->first()->docente_nombre)->toBe('Ana')
        ->and($this->servicio->usoDeEscenarios(new FiltroReporte(materiaId: $materiaDos->id))->get()->first()->materia_nombre)->toBe('Materia dos')
        ->and($this->servicio->usoDeEscenarios(new FiltroReporte(salaId: $salaUno->id))->get()->first()->sala_nombre)->toBe('Sala 1');
});

it('devuelve todo el histórico cuando no se pasa ningún filtro', function (): void {
    $docente = User::factory()->docente()->create();
    $materia = Materia::factory()->create();

    sesionAprobada($docente, $materia, CasoClinico::factory()->create(), '2024-01-10', '07:00:00', '09:00:00');
    sesionAprobada($docente, $materia, CasoClinico::factory()->create(), '2026-08-10', '07:00:00', '09:00:00');

    expect($this->servicio->usoDeEscenarios(new FiltroReporte)->get())->toHaveCount(2);
});

it('no funde en un renglón dos casos clínicos que se llaman igual', function (): void {
    // Agrupar por nombre en vez de por id los mezclaría. Dos escenarios
    // distintos con el mismo título son cosas distintas.
    $docente = User::factory()->docente()->create();
    $materia = Materia::factory()->create();

    sesionAprobada($docente, $materia, CasoClinico::factory()->create(['nombre' => 'Herida por arma']), '2026-04-01', '07:00:00', '09:00:00');
    sesionAprobada($docente, $materia, CasoClinico::factory()->create(['nombre' => 'Herida por arma']), '2026-04-02', '07:00:00', '09:00:00');

    expect($this->servicio->usoDeEscenarios(new FiltroReporte)->get())->toHaveCount(2);
});

it('no funde en un renglón dos docentes homónimos', function (): void {
    $materia = Materia::factory()->create();
    $caso = CasoClinico::factory()->create();

    sesionAprobada(User::factory()->docente()->create(['nombre' => 'Ana Gómez']), $materia, $caso, '2026-04-01', '07:00:00', '09:00:00');
    sesionAprobada(User::factory()->docente()->create(['nombre' => 'Ana Gómez']), $materia, $caso, '2026-04-02', '07:00:00', '09:00:00');

    expect($this->servicio->usoDeEscenarios(new FiltroReporte)->get())->toHaveCount(2);
});
