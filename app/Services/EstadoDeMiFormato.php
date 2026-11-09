<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoFormatoConfidencialidad;
use App\Enums\SituacionDeMiFormato;
use App\Models\FormatoConfidencialidad;

/**
 * El formato de confidencialidad de quien firma, en el periodo vigente
 * (RF51-RF53, RF75): lo que le dicen «Hoy» y «Mi formato».
 *
 * Las dos pantallas pintan la misma insignia y la misma frase, y las dos
 * salen de aquí: cuando cada una decidía por su cuenta, «Mi formato» decía
 * «Pendiente» a quien «Hoy» le decía que ya podía entrar.
 *
 * La entrega en físico no es un estado del documento (CLAUDE.md, regla 7):
 * convive con él y se pregunta aparte. Con ella se entra aunque el escaneo
 * esté en revisión o lo hayan devuelto (RF53).
 */
final readonly class EstadoDeMiFormato
{
    public function __construct(
        public ?string $periodo,
        public ?FormatoConfidencialidad $entrega,
    ) {}

    public function estado(): EstadoFormatoConfidencialidad
    {
        return $this->entrega->estado ?? EstadoFormatoConfidencialidad::Pendiente;
    }

    public function alDia(): bool
    {
        return $this->estado() === EstadoFormatoConfidencialidad::Verificado;
    }

    public function entregadoEnFisico(): bool
    {
        return $this->entrega?->entregadoEnFisico() ?? false;
    }

    /** Lo devolvieron para que lo vuelva a subir. */
    public function devuelto(): bool
    {
        return $this->estado() === EstadoFormatoConfidencialidad::Pendiente && $this->entrega?->motivo_rechazo !== null;
    }

    /**
     * La insignia. El papel va antes que la devolución: con él se entra, y
     * una insignia roja haría creer lo contrario; la frase dice lo demás.
     */
    public function situacion(): SituacionDeMiFormato
    {
        return match (true) {
            $this->alDia() => SituacionDeMiFormato::AlDia,
            $this->estado() === EstadoFormatoConfidencialidad::Cargado => SituacionDeMiFormato::EnRevision,
            $this->entregadoEnFisico() => SituacionDeMiFormato::EntregadoEnPapel,
            $this->devuelto() => SituacionDeMiFormato::Devuelto,
            default => SituacionDeMiFormato::Pendiente,
        };
    }

    /** La frase bajo la insignia: si puede entrar a las prácticas y qué le falta. */
    public function queSignifica(): string
    {
        $enPapel = $this->entregadoEnFisico();

        return match ($this->situacion()) {
            SituacionDeMiFormato::AlDia => 'Está verificado: puedes entrar a las prácticas de este periodo.',
            SituacionDeMiFormato::EnRevision => $enPapel
                ? 'Ya lo subiste y el laboratorio lo está revisando. Como lo entregaste en papel, ya puedes entrar.'
                : 'Ya lo subiste. El laboratorio lo está revisando; podrás entrar a las prácticas cuando lo verifique.',
            SituacionDeMiFormato::EntregadoEnPapel => $this->devuelto()
                ? 'Lo entregaste en papel y ya puedes entrar, pero te devolvieron el escaneo para corregirlo: vuelve a subirlo.'
                : 'Lo entregaste en papel y ya puedes entrar; falta subir el escaneo.',
            SituacionDeMiFormato::Devuelto => 'Te lo devolvieron para corregirlo. Vuelve a subirlo: sin él no puedes entrar a las prácticas.',
            SituacionDeMiFormato::Pendiente => $this->periodo === null
                ? 'Se entrega cuando el laboratorio abra el periodo académico.'
                : 'Todavía no lo has entregado en este periodo. Sin él no puedes entrar a las prácticas.',
        };
    }
}
