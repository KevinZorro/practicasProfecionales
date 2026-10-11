<?php

declare(strict_types=1);

use App\Enums\EstadoPreparacion;
use App\Enums\EstadoSolicitud;
use App\Enums\OrigenSolicitud;
use App\Models\Preparacion;
use App\Models\Solicitud;
use App\Support\LineaDeEstados;
use App\Support\PasoDeLaLinea;

/**
 * Una solicitud en memoria, sin base de datos: la línea solo lee su estado,
 * su origen, quién la revisó y su preparación.
 *
 * @param  array<string, mixed>  $atributos
 */
function solicitudEn(EstadoSolicitud $estado, array $atributos = [], ?EstadoPreparacion $montaje = null): Solicitud
{
    $solicitud = (new Solicitud)->forceFill([
        'estado' => $estado,
        'origen' => OrigenSolicitud::Docente,
        ...$atributos,
    ]);

    return $solicitud->setRelation('preparacion', $montaje === null ? null : (new Preparacion)->forceFill(['estado' => $montaje]));
}

/** @return array<string, string> fase => cómo se dibuja */
function dibujoDe(Solicitud $solicitud): array
{
    return collect(LineaDeEstados::deSolicitud($solicitud))
        ->mapWithKeys(fn (PasoDeLaLinea $paso): array => [$paso->fase => $paso->estado->value])
        ->all();
}

it('marca como actual la revisión de una solicitud pendiente', function (): void {
    $solicitud = solicitudEn(EstadoSolicitud::Pendiente);

    expect(dibujoDe($solicitud))->toBe([
        'Solicitud' => 'hecho',
        'Revisión' => 'actual',
        'Aprobación' => 'pendiente',
        'Montaje' => 'pendiente',
    ])->and(LineaDeEstados::situacion(LineaDeEstados::deSolicitud($solicitud)))->toBe('En revisión');
});

it('pasa a la aprobación cuando ya está revisada', function (): void {
    $solicitud = solicitudEn(EstadoSolicitud::Revisada, ['revisada_por' => 7]);

    expect(dibujoDe($solicitud))->toBe([
        'Solicitud' => 'hecho',
        'Revisión' => 'hecho',
        'Aprobación' => 'actual',
        'Montaje' => 'pendiente',
    ])->and(LineaDeEstados::situacion(LineaDeEstados::deSolicitud($solicitud)))->toBe('Por aprobar');
});

it('sigue con el montaje de una aprobada mientras el escenario no esté listo', function (EstadoPreparacion $montaje, string $situacion): void {
    $solicitud = solicitudEn(EstadoSolicitud::Aprobada, ['revisada_por' => 7], $montaje);

    expect(dibujoDe($solicitud)['Montaje'])->toBe('actual')
        ->and(LineaDeEstados::situacion(LineaDeEstados::deSolicitud($solicitud)))->toBe($situacion);
})->with([
    'sin empezar' => [EstadoPreparacion::Pendiente, 'Por montar'],
    'a medias' => [EstadoPreparacion::EnPreparacion, 'Montando'],
]);

it('da la línea por terminada cuando el escenario queda listo: toda en tinta, sin fase actual', function (): void {
    // El rojo dice dónde está en juego la solicitud; terminada, ya no lo está.
    $solicitud = solicitudEn(EstadoSolicitud::Aprobada, ['revisada_por' => 7], EstadoPreparacion::Preparado);

    expect(dibujoDe($solicitud))->toBe([
        'Solicitud' => 'hecho',
        'Revisión' => 'hecho',
        'Aprobación' => 'hecho',
        'Montaje' => 'hecho',
    ])->and(LineaDeEstados::situacion(LineaDeEstados::deSolicitud($solicitud)))->toBe('Escenario listo');
});

it('corta la línea en la revisión cuando la rechazan sin haberla revisado', function (): void {
    $solicitud = solicitudEn(EstadoSolicitud::Rechazada, ['revisada_por' => null]);

    expect(dibujoDe($solicitud))->toBe([
        'Solicitud' => 'hecho',
        'Revisión' => 'cortado',
    ])->and(LineaDeEstados::situacion(LineaDeEstados::deSolicitud($solicitud)))->toBe('Rechazada');
});

it('corta la línea en la aprobación cuando la rechaza coordinación', function (): void {
    expect(dibujoDe(solicitudEn(EstadoSolicitud::Rechazada, ['revisada_por' => 7])))->toBe([
        'Solicitud' => 'hecho',
        'Revisión' => 'hecho',
        'Aprobación' => 'cortado',
    ]);
});

it('empieza en el registro una sesión apartada antes del semestre, que nace aprobada', function (): void {
    $apartada = solicitudEn(EstadoSolicitud::Aprobada, ['origen' => OrigenSolicitud::RegistroPrevio], EstadoPreparacion::Pendiente);

    expect(dibujoDe($apartada))->toBe([
        'Registro' => 'hecho',
        'Aprobación' => 'hecho',
        'Montaje' => 'actual',
    ]);
});
