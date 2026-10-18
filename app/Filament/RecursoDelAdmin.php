<?php

declare(strict_types=1);

namespace App\Filament;

use App\Services\ImagenPublicaService;
use App\Services\VideoPublicoService;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Base de todas las pantallas del ADMIN en Filament.
 *
 * Filament consulta las Policies de Laravel, pero por defecto hace al revés
 * que Laravel: si el modelo no tiene Policy, o la Policy no define el
 * método (deleteAny, restore, replicate...), PERMITE la acción. Con eso,
 * olvidar una Policy o nombrarla mal —que ya nos pasó dos veces— dejaría la
 * pantalla abierta en vez de cerrada.
 *
 * Con la comprobación de existencia apagada, Filament pregunta al Gate
 * siempre, y el Gate deniega lo que no está definido. Cada recurso tiene
 * que heredar de aquí; RecursosDelAdminTest falla si alguno no lo hace o si
 * su modelo no tiene Policy.
 */
abstract class RecursoDelAdmin extends Resource
{
    protected static bool $shouldCheckPolicyExistence = false;

    /**
     * Columnas que guardan una imagen del disco público. Las usa
     * BorraLasImagenesReemplazadas para no dejar archivos huérfanos.
     *
     * @return list<string>
     */
    public static function camposDeImagen(): array
    {
        return [];
    }

    /**
     * Columnas que guardan un video del disco público (RF76). Las usa
     * BorraLasImagenesReemplazadas, igual que con las imágenes.
     *
     * @return list<string>
     */
    public static function camposDeVideo(): array
    {
        return [];
    }

    /**
     * Borra del disco, al confirmar, la imagen y el video de un registro que
     * se acaba de eliminar.
     */
    protected static function borrarArchivosDe(Model $registro): void
    {
        foreach (static::camposDeImagen() as $campo) {
            app(ImagenPublicaService::class)->borrarAlConfirmar($registro->getAttribute($campo));
        }

        foreach (static::camposDeVideo() as $campo) {
            app(VideoPublicoService::class)->borrarAlConfirmar($registro->getAttribute($campo));
        }
    }
}
