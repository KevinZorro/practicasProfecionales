<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ImagenInvalida;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Imágenes del contenido público: galería, talleres, eventos,
 * certificaciones, perfiles docentes, casos clínicos (RF10-RF16).
 *
 * Todo lo que sube el ADMIN se guarda ya listo para la web (RNF10): girado
 * según la orientación que anotó la cámara, reducido a un tamaño que una
 * pantalla sí aprovecha y convertido a WebP, que pesa bastante menos que un
 * JPEG a la misma calidad y conserva la transparencia de un PNG (las
 * insignias de las certificaciones). Una foto de celular de 4 MB queda en
 * unos cientos de KB, y así es como la descarga quien entra desde una
 * conexión lenta.
 *
 * Se hace con GD, que ya trae la imagen de PHP: sin dependencias nuevas.
 *
 * Los archivos van al disco "public" y los sirve nginx directamente
 * (docker/nginx/default.conf). Nada con datos personales pasa por aquí: el
 * formato de confidencialidad usa el disco privado.
 */
final class ImagenPublicaService
{
    public const DISCO = 'public';

    /** Tipos que se aceptan al subir. */
    public const TIPOS_ACEPTADOS = ['image/jpeg', 'image/png', 'image/webp'];

    /** Tamaño máximo del archivo subido, en KB (como lo espera la validación). */
    public const TAMANO_MAXIMO_KB = 5 * 1024;

    /** Lado mayor de la imagen guardada: suficiente para una pantalla ancha. */
    public const LADO_MAYOR_MAXIMO = 1600;

    /**
     * Tope de píxeles de la imagen original. Una imagen se descomprime en
     * memoria a 4 bytes por píxel antes de reducirla: 40 megapíxeles son
     * 160 MB, dentro del memory_limit, y ninguna cámara de celular llega.
     */
    public const PIXELES_MAXIMOS = 40_000_000;

    public const CALIDAD_WEBP = 80;

    /** Guarda la imagen lista para la web y devuelve su ruta en el disco. */
    public function guardar(UploadedFile $archivo, string $carpeta): string
    {
        $imagen = $this->abrir($archivo->getRealPath());
        $imagen = $this->enderezar($imagen, $archivo->getRealPath());
        $imagen = $this->reducir($imagen);

        $ruta = trim($carpeta, '/').'/'.Str::uuid().'.webp';
        Storage::disk(self::DISCO)->put($ruta, $this->comoWebp($imagen));

        return $ruta;
    }

    /**
     * Borra una imagen que dejó de usarse, cuando la transacción que la
     * reemplazó o borró su registro ya se confirmó. Si esa transacción se
     * deshace, la imagen sigue en su sitio y el registro sigue apuntándola.
     */
    public function borrarAlConfirmar(?string $ruta): void
    {
        if ($ruta === null || $ruta === '') {
            return;
        }

        DB::afterCommit(static fn () => Storage::disk(self::DISCO)->delete($ruta));
    }

    private function abrir(string $ruta): GdImage
    {
        $medidas = @getimagesize($ruta);

        if ($medidas === false || ! in_array($medidas['mime'], self::TIPOS_ACEPTADOS, true)) {
            throw ImagenInvalida::noSeReconoce();
        }

        [$ancho, $alto] = $medidas;

        // Se mira antes de descomprimir: la cabecera dice cuánto va a ocupar.
        if ($ancho * $alto > self::PIXELES_MAXIMOS) {
            throw ImagenInvalida::demasiadosPixeles($ancho, $alto, self::PIXELES_MAXIMOS);
        }

        $contenido = file_get_contents($ruta);
        $imagen = $contenido === false ? false : @imagecreatefromstring($contenido);

        if ($imagen === false) {
            throw ImagenInvalida::noSeReconoce();
        }

        return $imagen;
    }

    /**
     * Las fotos de celular se guardan de lado y llevan en el EXIF cómo
     * girarlas. El navegador lo respeta con el JPEG, pero el WebP que
     * guardamos no lleva EXIF: si no se gira aquí, sale acostada.
     */
    private function enderezar(GdImage $imagen, string $ruta): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $imagen;
        }

        $exif = @exif_read_data($ruta);
        $orientacion = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $girada = match ($orientacion) {
            3 => imagerotate($imagen, 180, 0),
            6 => imagerotate($imagen, -90, 0),
            8 => imagerotate($imagen, 90, 0),
            default => $imagen,
        };

        return $girada === false ? $imagen : $girada;
    }

    /** Reduce sin deformar; una imagen que ya es pequeña no se agranda. */
    private function reducir(GdImage $imagen): GdImage
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $escala = self::LADO_MAYOR_MAXIMO / max($ancho, $alto);

        if ($escala >= 1) {
            return $imagen;
        }

        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto = max(1, (int) round($alto * $escala));

        $reducida = imagecreatetruecolor($nuevoAncho, $nuevoAlto);

        // Conserva la transparencia de los PNG en vez de rellenarla de negro.
        imagealphablending($reducida, false);
        imagesavealpha($reducida, true);
        imagecopyresampled($reducida, $imagen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        return $reducida;
    }

    private function comoWebp(GdImage $imagen): string
    {
        // Las paletas (PNG de 8 bits, GIF) no se pueden guardar en WebP.
        if (! imageistruecolor($imagen)) {
            imagepalettetotruecolor($imagen);
        }

        imagealphablending($imagen, false);
        imagesavealpha($imagen, true);

        ob_start();
        imagewebp($imagen, null, self::CALIDAD_WEBP);

        return (string) ob_get_clean();
    }
}
