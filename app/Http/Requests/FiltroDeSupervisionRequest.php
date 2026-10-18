<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Evaluacion;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtros de las descargas del docente supervisor (RF77): periodo y docente.
 *
 * Solo dicen qué mirar dentro de lo que el supervisor ya puede ver. Qué
 * docentes tiene a su cargo no viene en la petición: lo decide
 * SupervisionService desde la base.
 */
final class FiltroDeSupervisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('supervisar', Evaluacion::class) ?? false;
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
            'periodo' => ['nullable', 'string', 'max:20'],
            'docente' => ['nullable', 'integer'],
        ];
    }
}
