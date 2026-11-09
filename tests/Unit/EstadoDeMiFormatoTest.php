<?php

declare(strict_types=1);

use App\Enums\EstadoFormatoConfidencialidad;
use App\Enums\SituacionDeMiFormato;
use App\Models\FormatoConfidencialidad;
use App\Services\EstadoDeMiFormato;

/**
 * El formato en memoria, sin base de datos: la situación solo lee el estado
 * del documento, si lo devolvieron y si se recibió en papel.
 */
function miFormatoEn(EstadoFormatoConfidencialidad $estado, bool $enPapel = false, ?string $motivoDeLaDevolucion = null): EstadoDeMiFormato
{
    return new EstadoDeMiFormato('2026-2', (new FormatoConfidencialidad)->forceFill([
        'estado' => $estado,
        'recibido_fisico_at' => $enPapel ? now() : null,
        'motivo_rechazo' => $motivoDeLaDevolucion,
    ]));
}

it('dice si puede entrar a las prácticas según el documento y el papel (RF53)', function (EstadoDeMiFormato $miFormato, SituacionDeMiFormato $situacion, string $frase): void {
    expect($miFormato->situacion())->toBe($situacion)
        ->and($miFormato->queSignifica())->toContain($frase);
})->with([
    'sin entregar' => [fn () => new EstadoDeMiFormato('2026-2', null), SituacionDeMiFormato::Pendiente, 'Sin él no puedes entrar'],
    'en revisión' => [fn () => miFormatoEn(EstadoFormatoConfidencialidad::Cargado), SituacionDeMiFormato::EnRevision, 'podrás entrar a las prácticas cuando lo verifique'],
    'devuelto' => [fn () => miFormatoEn(EstadoFormatoConfidencialidad::Pendiente, motivoDeLaDevolucion: 'La firma no se lee.'), SituacionDeMiFormato::Devuelto, 'sin él no puedes entrar'],
    'verificado' => [fn () => miFormatoEn(EstadoFormatoConfidencialidad::Verificado), SituacionDeMiFormato::AlDia, 'puedes entrar a las prácticas de este periodo'],
    'en papel' => [fn () => miFormatoEn(EstadoFormatoConfidencialidad::Pendiente, enPapel: true), SituacionDeMiFormato::EntregadoEnPapel, 'ya puedes entrar; falta subir el escaneo'],
    'en papel y en revisión' => [fn () => miFormatoEn(EstadoFormatoConfidencialidad::Cargado, enPapel: true), SituacionDeMiFormato::EnRevision, 'Como lo entregaste en papel, ya puedes entrar'],
    'en papel y devuelto' => [fn () => miFormatoEn(EstadoFormatoConfidencialidad::Pendiente, true, 'La firma no se lee.'), SituacionDeMiFormato::EntregadoEnPapel, 'ya puedes entrar, pero te devolvieron el escaneo'],
    'en papel y verificado' => [fn () => miFormatoEn(EstadoFormatoConfidencialidad::Verificado, enPapel: true), SituacionDeMiFormato::AlDia, 'puedes entrar a las prácticas de este periodo'],
]);

it('no le dice «Devuelto» a quien entregó en papel: con el papel entra', function (): void {
    // La insignia roja haría creer que no puede entrar; la frase dice que
    // le devolvieron el escaneo.
    $miFormato = miFormatoEn(EstadoFormatoConfidencialidad::Pendiente, true, 'La firma no se lee.');

    expect($miFormato->situacion())->not->toBe(SituacionDeMiFormato::Devuelto)
        ->and($miFormato->devuelto())->toBeTrue();
});

it('no habla de «este periodo» si el laboratorio nunca ha abierto uno', function (): void {
    expect((new EstadoDeMiFormato(null, null))->queSignifica())
        ->toBe('Se entrega cuando el laboratorio abra el periodo académico.');
});
