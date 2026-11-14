<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\DatosDeSolicitudDeInformacion;
use Illuminate\Foundation\Http\FormRequest;

/**
 * El formulario público de información por taller (RF09): formato y
 * obligatoriedad. Que el taller reciba solicitudes lo decide el Service.
 *
 * El campo trampa (CAMPO_TRAMPA) no se valida aquí: lo llena un robot, no
 * una persona, y el controlador lo descarta sin decirle nada (D16).
 */
final class SolicitudDeInformacionRequest extends FormRequest
{
    /** Invisible para una persona; un robot que llena todo lo completa. */
    public const CAMPO_TRAMPA = 'sitio_web';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'mensaje' => ['nullable', 'string', 'max:2000'],
            // Ley 1581: sin autorización no se guardan sus datos.
            'acepta_tratamiento' => ['accepted'],
        ];
    }

    public function esDeUnRobot(): bool
    {
        return filled($this->input(self::CAMPO_TRAMPA));
    }

    public function datos(): DatosDeSolicitudDeInformacion
    {
        /** @var array{nombre: string, email: string, telefono?: ?string, mensaje?: ?string} $validados */
        $validados = $this->validated();

        return new DatosDeSolicitudDeInformacion(
            nombre: trim($validados['nombre']),
            email: trim($validados['email']),
            telefono: blank($validados['telefono'] ?? null) ? null : trim((string) $validados['telefono']),
            mensaje: blank($validados['mensaje'] ?? null) ? null : trim((string) $validados['mensaje']),
        );
    }
}
