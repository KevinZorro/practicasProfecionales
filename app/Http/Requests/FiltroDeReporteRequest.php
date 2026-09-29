<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Reporte;
use App\Services\FiltroReporte;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtros de las descargas de reportes (RF54-RF56).
 *
 * La pantalla valida con estas mismas reglas y arma el filtro con este mismo
 * método: el PDF y el Excel se piden con los filtros que se ven en pantalla,
 * en la URL, y no pueden interpretarlos de otra manera.
 */
final class FiltroDeReporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('generarReportes') ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return self::reglas();
    }

    /** @return array<string, list<string>> */
    public static function reglas(): array
    {
        return [
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'docente' => ['nullable', 'integer', 'exists:users,id'],
            'materia' => ['nullable', 'integer', 'exists:materias,id'],
            'sala' => ['nullable', 'integer', 'exists:salas,id'],
        ];
    }

    public function filtroPara(Reporte $reporte): FiltroReporte
    {
        return self::filtro($reporte, $this->validated());
    }

    /**
     * La sala solo se aplica al reporte que sabe de salas; en los demás se
     * descarta para que la cabecera del PDF no anuncie un filtro que no se
     * aplicó.
     *
     * @param  array<string, mixed>  $datos  ya validados
     */
    public static function filtro(Reporte $reporte, array $datos): FiltroReporte
    {
        $entero = static fn (mixed $valor): ?int => $valor === null || $valor === '' ? null : (int) $valor;
        $fecha = static fn (mixed $valor): ?string => $valor === null || $valor === '' ? null : (string) $valor;

        return new FiltroReporte(
            desde: $fecha($datos['desde'] ?? null),
            hasta: $fecha($datos['hasta'] ?? null),
            docenteId: $entero($datos['docente'] ?? null),
            materiaId: $entero($datos['materia'] ?? null),
            salaId: $reporte->filtraPorSala() ? $entero($datos['sala'] ?? null) : null,
        );
    }
}
