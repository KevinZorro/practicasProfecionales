<?php

declare(strict_types=1);

use App\Exceptions\ImagenInvalida;
use App\Services\ImagenPublicaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 * Imágenes del contenido público: lo que sube el ADMIN se guarda listo para
 * la web (RNF10).
 */

beforeEach(function (): void {
    Storage::fake(ImagenPublicaService::DISCO);
    $this->imagenes = app(ImagenPublicaService::class);
});

/** @return array{0: int, 1: int, 2: string} ancho, alto y tipo de lo guardado */
function medidasGuardadas(string $ruta): array
{
    $medidas = getimagesizefromstring(Storage::disk(ImagenPublicaService::DISCO)->get($ruta));

    return [$medidas[0], $medidas[1], $medidas['mime']];
}

function archivoDePrueba(string $nombre, string $tipo): UploadedFile
{
    return new UploadedFile(base_path("tests/Fixtures/imagenes/{$nombre}"), $nombre, $tipo, null, true);
}

it('reduce una foto grande a tamaño web y la guarda en WebP en su carpeta', function (): void {
    $ruta = $this->imagenes->guardar(UploadedFile::fake()->image('foto.jpg', 4000, 3000), 'galeria');

    expect($ruta)->toStartWith('galeria/')->toEndWith('.webp')
        ->and(medidasGuardadas($ruta))->toBe([1600, 1200, 'image/webp']);
});

it('reduce también una foto vertical por su lado mayor', function (): void {
    $ruta = $this->imagenes->guardar(UploadedFile::fake()->image('retrato.png', 1000, 3200), 'perfiles');

    expect(medidasGuardadas($ruta))->toBe([500, 1600, 'image/webp']);
});

it('no agranda una imagen que ya es pequeña', function (): void {
    $ruta = $this->imagenes->guardar(UploadedFile::fake()->image('insignia.png', 300, 200), 'certificaciones');

    expect(medidasGuardadas($ruta))->toBe([300, 200, 'image/webp']);
});

it('endereza la foto de celular que la cámara guardó de lado', function (): void {
    // 40 × 20 con Orientation = 6 en el EXIF: la cámara pide girarla 90°.
    $ruta = $this->imagenes->guardar(archivoDePrueba('de-lado-orientacion-6.jpg', 'image/jpeg'), 'galeria');

    expect(medidasGuardadas($ruta))->toBe([20, 40, 'image/webp']);
});

it('conserva la transparencia de un PNG al reducirlo', function (): void {
    $png = imagecreatetruecolor(3200, 1600);
    imagealphablending($png, false);
    imagesavealpha($png, true);
    imagefill($png, 0, 0, imagecolorallocatealpha($png, 0, 0, 0, 127));
    $temporal = tempnam(sys_get_temp_dir(), 'png');
    imagepng($png, $temporal);

    $ruta = $this->imagenes->guardar(new UploadedFile($temporal, 'insignia.png', 'image/png', null, true), 'certificaciones');

    $guardada = imagecreatefromstring(Storage::disk(ImagenPublicaService::DISCO)->get($ruta));
    $alfa = (imagecolorat($guardada, 10, 10) >> 24) & 0x7F;

    expect($alfa)->toBe(127);
});

it('rechaza un archivo que no es una imagen', function (): void {
    $archivo = UploadedFile::fake()->createWithContent('foto.jpg', 'esto no es una imagen');

    expect(fn () => $this->imagenes->guardar($archivo, 'galeria'))->toThrow(ImagenInvalida::class);
    expect(Storage::disk(ImagenPublicaService::DISCO)->allFiles())->toBe([]);
});

it('rechaza una imagen de demasiados píxeles sin llegar a descomprimirla', function (): void {
    // La cabecera dice 10 000 × 10 000 y el archivo pesa 45 bytes: si se
    // intentara abrir, fallaría por otra razón. Se rechaza por la cabecera.
    $archivo = archivoDePrueba('cabecera-de-100-megapixeles.png', 'image/png');

    expect(fn () => $this->imagenes->guardar($archivo, 'galeria'))
        ->toThrow(ImagenInvalida::class, 'megapíxeles');
});

it('borra la imagen reemplazada cuando la transacción se confirma', function (): void {
    Storage::disk(ImagenPublicaService::DISCO)->put('galeria/vieja.webp', 'x');

    DB::transaction(fn () => $this->imagenes->borrarAlConfirmar('galeria/vieja.webp'));

    Storage::disk(ImagenPublicaService::DISCO)->assertMissing('galeria/vieja.webp');
});

it('conserva la imagen si la transacción se deshace', function (): void {
    // Si el guardado falla, el registro sigue apuntando a su imagen.
    Storage::disk(ImagenPublicaService::DISCO)->put('galeria/vieja.webp', 'x');

    try {
        DB::transaction(function (): void {
            $this->imagenes->borrarAlConfirmar('galeria/vieja.webp');

            throw new RuntimeException('falla simulada');
        });
    } catch (RuntimeException) {
    }

    Storage::disk(ImagenPublicaService::DISCO)->assertExists('galeria/vieja.webp');
});
