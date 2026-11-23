<?php

declare(strict_types=1);

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/*
 * produccion.sh es lo que se corre el día de la instalación, con el tiempo
 * contado y lejos del equipo de desarrollo. Lo que más daño puede hacer es
 * el .env: una APP_KEY nueva sobre una base con datos pierde los documentos
 * cifrados, y una contraseña nueva del dueño deja la base sin abrir. Estas
 * pruebas corren su paso "configurar", que no toca Docker, sobre un .env de
 * prueba. El resto —construir, migrar, levantar— lo recorre el job de
 * producción de la CI con el mismo script.
 */

beforeEach(function (): void {
    $this->carpeta = sys_get_temp_dir().'/instalador-'.Str::random(10);
    File::ensureDirectoryExists($this->carpeta);
    $this->env = $this->carpeta.'/instalador.env';
});

afterEach(function (): void {
    File::deleteDirectory($this->carpeta);
});

/**
 * Corre "produccion.sh configurar" sin preguntas. Las respuestas que no se
 * dan van vacías: el proceso de las pruebas hereda las variables del .env de
 * desarrollo, y APP_URL o GOOGLE_CLIENT_ID de ahí no deben colarse.
 *
 * @param  array<string, string>  $respuestas
 */
function configurarConElInstalador(string $archivo, array $respuestas = []): ProcessResult
{
    $sinResponder = array_fill_keys([
        'APP_URL', 'APP_PORT', 'DIRECTORIO_COPIAS', 'PROXIES_DE_CONFIANZA',
        'GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'GOOGLE_DOMINIO_INSTITUCIONAL',
        'CORREO_LABORATORIO', 'CORREO_CONTRASENA_DE_APLICACION',
        'BASE_EXTERNA_HOST', 'BASE_EXTERNA_PUERTO', 'BASE_EXTERNA_NOMBRE', 'BASE_EXTERNA_USUARIO', 'BASE_EXTERNA_CONTRASENA',
    ], '');

    return Process::env(['ARCHIVO_ENV' => $archivo, 'NO_INTERACTIVO' => '1'] + $respuestas + $sinResponder)
        ->run(['bash', base_path('produccion.sh'), 'configurar']);
}

/** @return array<string, string> */
function variablesDe(string $archivo): array
{
    preg_match_all('/^([A-Z_]+)=(.*)$/m', (string) file_get_contents($archivo), $coincidencias);

    return array_combine($coincidencias[1], $coincidencias[2]);
}

const DIRECCION_CON_HTTPS = 'https://laboratorio.ejemplo.edu.co';

it('prepara un .env completo, con secretos aleatorios y que solo lee su dueño', function (): void {
    $resultado = configurarConElInstalador($this->env, ['APP_URL' => DIRECCION_CON_HTTPS]);
    $variables = variablesDe($this->env);

    expect($resultado->successful())->toBeTrue($resultado->output().$resultado->errorOutput())
        ->and((string) file_get_contents($this->env))->not->toContain('=<')
        ->and(fileperms($this->env) & 0777)->toBe(0600)
        ->and($variables['APP_KEY'])->toStartWith('base64:')
        ->and(strlen((string) base64_decode(substr($variables['APP_KEY'], 7), true)))->toBe(32)
        ->and($variables['DB_PASSWORD'])->toMatch('/^[0-9a-f]{48}$/')
        ->and($variables['DB_DUENO_CONTRASENA'])->toMatch('/^[0-9a-f]{48}$/')
        ->and($variables['DB_DUENO_CONTRASENA'])->not->toBe($variables['DB_PASSWORD'])
        ->and($variables['APP_URL'])->toBe(DIRECCION_CON_HTTPS)
        ->and($variables['APP_ENV'])->toBe('production')
        ->and($variables['APP_DEBUG'])->toBe('false')
        ->and($variables['SESSION_SECURE_COOKIE'])->toBe('true')
        ->and($variables['SESSION_ENCRYPT'])->toBe('true');
});

it('genera otros secretos en cada instalación', function (): void {
    $otro = $this->carpeta.'/otro.env';

    configurarConElInstalador($this->env, ['APP_URL' => DIRECCION_CON_HTTPS]);
    configurarConElInstalador($otro, ['APP_URL' => DIRECCION_CON_HTTPS]);

    expect(variablesDe($otro)['APP_KEY'])->not->toBe(variablesDe($this->env)['APP_KEY'])
        ->and(variablesDe($otro)['DB_DUENO_CONTRASENA'])->not->toBe(variablesDe($this->env)['DB_DUENO_CONTRASENA']);
});

it('no cambia los secretos ni la dirección de un .env que ya existe al volver a correrlo', function (): void {
    configurarConElInstalador($this->env, ['APP_URL' => DIRECCION_CON_HTTPS, 'APP_PORT' => '8080']);
    $antes = variablesDe($this->env);

    $resultado = configurarConElInstalador($this->env, ['APP_URL' => 'https://otra.ejemplo.edu.co', 'APP_PORT' => '9090']);
    $despues = variablesDe($this->env);

    expect($resultado->successful())->toBeTrue()
        ->and($resultado->output())->toContain('Usa el .env que ya existe')
        ->and($despues['APP_KEY'])->toBe($antes['APP_KEY'])
        ->and($despues['DB_PASSWORD'])->toBe($antes['DB_PASSWORD'])
        ->and($despues['DB_DUENO_CONTRASENA'])->toBe($antes['DB_DUENO_CONTRASENA'])
        ->and($despues['APP_URL'])->toBe(DIRECCION_CON_HTTPS)
        ->and($despues['APP_PORT'])->toBe('8080');
});

it('completa un .env viejo con las variables nuevas del ejemplo sin tocar las que tiene', function (): void {
    file_put_contents($this->env, "APP_KEY=base64:clave-de-antes\nAPP_URL=".DIRECCION_CON_HTTPS);

    $resultado = configurarConElInstalador($this->env);
    $variables = variablesDe($this->env);

    expect($resultado->successful())->toBeTrue($resultado->output().$resultado->errorOutput())
        ->and($variables['APP_KEY'])->toBe('base64:clave-de-antes')
        ->and($variables)->toHaveKeys(['DB_DUENO_CONTRASENA', 'DIRECTORIO_COPIAS', 'POLITICA_DE_CONTENIDO'])
        ->and($variables['DB_DUENO_CONTRASENA'])->toMatch('/^[0-9a-f]{48}$/')
        ->and((string) file_get_contents($this->env))->not->toContain('=<');
});

it('apaga la cookie segura sin HTTPS, porque no viajaría, y la vuelve a exigir cuando llega', function (): void {
    $resultado = configurarConElInstalador($this->env, ['APP_URL' => 'http://10.0.0.5:8080']);

    expect($resultado->output())->toContain('Sin HTTPS')
        ->and(variablesDe($this->env)['SESSION_SECURE_COOKIE'])->toBe('false');

    // El ingeniero pone el proxy con HTTPS y se cambia la dirección a mano.
    file_put_contents($this->env, str_replace('APP_URL=http://10.0.0.5:8080', 'APP_URL='.DIRECCION_CON_HTTPS, (string) file_get_contents($this->env)));
    configurarConElInstalador($this->env);

    expect(variablesDe($this->env)['SESSION_SECURE_COOKIE'])->toBe('true');
});

it('sin correo del laboratorio deja los avisos en el registro, con un remitente bien escrito', function (): void {
    configurarConElInstalador($this->env, ['APP_URL' => DIRECCION_CON_HTTPS]);
    $variables = variablesDe($this->env);

    expect($variables['MAIL_MAILER'])->toBe('log')
        ->and(filter_var($variables['MAIL_FROM_ADDRESS'], FILTER_VALIDATE_EMAIL))->not->toBeFalse();
});

it('con el correo del laboratorio usa el SMTP de Google y la contraseña de aplicación sin espacios', function (): void {
    configurarConElInstalador($this->env, [
        'APP_URL' => DIRECCION_CON_HTTPS,
        'CORREO_LABORATORIO' => 'laboratorio@ejemplo.edu.co',
        'CORREO_CONTRASENA_DE_APLICACION' => 'abcd efgh ijkl mnop',
    ]);
    $variables = variablesDe($this->env);

    expect($variables['MAIL_MAILER'])->toBe('smtp')
        ->and($variables['MAIL_HOST'])->toBe('smtp.gmail.com')
        ->and($variables['MAIL_PORT'])->toBe('587')
        ->and($variables['MAIL_USERNAME'])->toBe('laboratorio@ejemplo.edu.co')
        ->and($variables['MAIL_PASSWORD'])->toBe('abcdefghijklmnop')
        ->and($variables['MAIL_FROM_ADDRESS'])->toBe('laboratorio@ejemplo.edu.co');
});

it('con las credenciales de Google exige el dominio institucional, sin el que nadie entraría', function (): void {
    $sinDominio = configurarConElInstalador($this->env, [
        'APP_URL' => DIRECCION_CON_HTTPS,
        'GOOGLE_CLIENT_ID' => '1234-abc.apps.googleusercontent.com',
        'GOOGLE_CLIENT_SECRET' => 'GOCSPX-secreto',
    ]);

    expect($sinDominio->failed())->toBeTrue()
        ->and($sinDominio->errorOutput())->toContain('GOOGLE_DOMINIO_INSTITUCIONAL');

    configurarConElInstalador($this->env, [
        'GOOGLE_CLIENT_ID' => '1234-abc.apps.googleusercontent.com',
        'GOOGLE_CLIENT_SECRET' => 'GOCSPX-secreto',
        'GOOGLE_DOMINIO_INSTITUCIONAL' => 'ejemplo.edu.co',
    ]);

    expect(variablesDe($this->env))->toMatchArray([
        'GOOGLE_CLIENT_ID' => '1234-abc.apps.googleusercontent.com',
        'GOOGLE_CLIENT_SECRET' => 'GOCSPX-secreto',
        'GOOGLE_DOMINIO_INSTITUCIONAL' => 'ejemplo.edu.co',
    ]);
});

it('no inventa una dirección si no se la dan y no puede preguntar', function (): void {
    $resultado = configurarConElInstalador($this->env);

    expect($resultado->failed())->toBeTrue()
        ->and($resultado->errorOutput())->toContain('Falta APP_URL');
});

it('no acepta datos mal escritos', function (array $respuestas, string $menciona): void {
    $resultado = configurarConElInstalador($this->env, $respuestas + ['APP_URL' => DIRECCION_CON_HTTPS]);

    expect($resultado->failed())->toBeTrue()
        ->and($resultado->errorOutput())->toContain($menciona);
})->with([
    'una dirección sin http' => [['APP_URL' => 'laboratorio.ejemplo.edu.co'], 'APP_URL'],
    'una dirección con carpeta' => [['APP_URL' => 'https://ejemplo.edu.co/laboratorio'], 'APP_URL'],
    'un proxy que no es una IP' => [['PROXIES_DE_CONFIANZA' => 'proxy.ejemplo.edu.co'], 'PROXIES_DE_CONFIANZA'],
    'un puerto que no es un número' => [['APP_PORT' => 'ochenta'], 'APP_PORT'],
    'un correo sin arroba' => [['CORREO_LABORATORIO' => 'laboratorio', 'CORREO_CONTRASENA_DE_APLICACION' => 'abcd'], 'no es un correo'],
]);

it('toma del entorno el puerto y la carpeta de copias de una instalación nueva', function (): void {
    configurarConElInstalador($this->env, [
        'APP_URL' => DIRECCION_CON_HTTPS,
        'APP_PORT' => '8080',
        'DIRECTORIO_COPIAS' => '/srv/otra/copias',
        'PROXIES_DE_CONFIANZA' => '10.20.0.5, 10.30.0.0/24',
    ]);

    expect(variablesDe($this->env))->toMatchArray([
        'APP_PORT' => '8080',
        'DIRECTORIO_COPIAS' => '/srv/otra/copias',
        'PROXIES_DE_CONFIANZA' => '10.20.0.5, 10.30.0.0/24',
    ]);
});

// ---------------------------------------------------------------------
// Lo que el script tiene que compartir con el resto de producción
// ---------------------------------------------------------------------

it('es ejecutable y bash lo entiende', function (): void {
    expect(is_executable(base_path('produccion.sh')))->toBeTrue()
        ->and(Process::run(['bash', '-n', base_path('produccion.sh')])->successful())->toBeTrue();
});

it('usa el mismo proyecto y las mismas imágenes que el compose de producción', function (): void {
    // "empaquetar" guarda estas imágenes e "instalar --imagenes" las carga;
    // con otro nombre, el compose las construiría o las bajaría de nuevo.
    $script = (string) file_get_contents(base_path('produccion.sh'));
    $compose = (string) file_get_contents(base_path('docker-compose.produccion.yml'));

    preg_match_all('/^(PROYECTO|IMAGEN_[A-Z]+)="([^"]+)"$/m', $script, $declaradas);
    $delScript = array_combine($declaradas[1], $declaradas[2]);

    // Las de copias y restauración llevan su valor por defecto: ${VARIABLE:-imagen}.
    preg_match_all('/^\s+image: (\S+)$/m', $compose, $imagenes);
    $imagenes[1] = array_map(static fn (string $imagen): string => preg_replace('/^\$\{[A-Z_]+:-(.+)\}$/', '$1', $imagen), $imagenes[1]);

    expect($compose)->toContain("name: {$delScript['PROYECTO']}\n")
        ->and(array_values(array_unique($imagenes[1])))->toEqualCanonicalizing([
            $delScript['IMAGEN_APP'], $delScript['IMAGEN_WEB'], $delScript['IMAGEN_BASE'], $delScript['IMAGEN_COPIAS'],
        ]);
});

it('deja fuera de las imágenes y de git lo que empaqueta', function (): void {
    expect((string) file_get_contents(base_path('.dockerignore')))->toMatch('/^produccion\.sh$/m')
        ->toMatch('/^imagenes-docker$/m')
        ->and((string) file_get_contents(base_path('.gitignore')))->toMatch('#^/imagenes-docker$#m');
});

// ---------------------------------------------------------------------
// Base de datos ajena (ADR 0010)
// ---------------------------------------------------------------------

const BASE_AJENA = [
    'BASE_EXTERNA_HOST' => '10.20.30.40',
    'BASE_EXTERNA_PUERTO' => '5432',
    'BASE_EXTERNA_NOMBRE' => 'laboratorio',
    'BASE_EXTERNA_USUARIO' => 'testlab',
    'BASE_EXTERNA_CONTRASENA' => 'clave-de-prueba',
];

it('por defecto usa la base del compose: perfil base-propia, DB_HOST=db y el cliente de la 16', function (): void {
    configurarConElInstalador($this->env, ['APP_URL' => DIRECCION_CON_HTTPS]);
    $variables = variablesDe($this->env);

    expect($variables['COMPOSE_PROFILES'])->toBe('base-propia')
        ->and($variables['DB_HOST'])->toBe('db')
        ->and($variables['IMAGEN_CLIENTE_POSTGRES'])->toBe('postgres:16-alpine')
        ->and($variables['DB_USUARIO_UNICO'])->toBe('false');
});

it('con una base ajena apunta a ella y apaga el perfil del servicio db', function (): void {
    $resultado = configurarConElInstalador($this->env, ['APP_URL' => DIRECCION_CON_HTTPS] + BASE_AJENA);
    $variables = variablesDe($this->env);

    expect($resultado->successful())->toBeTrue($resultado->output().$resultado->errorOutput())
        ->and($variables)->toMatchArray([
            'COMPOSE_PROFILES' => '',
            'DB_HOST' => '10.20.30.40',
            'DB_PORT' => '5432',
            'DB_DATABASE' => 'laboratorio',
            'DB_DUENO_USUARIO' => 'testlab',
            'DB_DUENO_CONTRASENA' => 'clave-de-prueba',
        ])
        // La aplicación entra con su propio usuario y su propia contraseña.
        ->and($variables['DB_USERNAME'])->toBe('laboratorio_app')
        ->and($variables['DB_PASSWORD'])->toMatch('/^[0-9a-f]{48}$/')
        ->and((string) file_get_contents($this->env))->not->toContain('=<');
});

it('no acepta una base ajena mal descrita', function (array $cambio, string $menciona): void {
    $resultado = configurarConElInstalador($this->env, $cambio + BASE_AJENA + ['APP_URL' => DIRECCION_CON_HTTPS]);

    expect($resultado->failed())->toBeTrue()
        ->and($resultado->errorOutput())->toContain($menciona)
        ->and(file_exists($this->env))->toBeFalse();
})->with([
    'localhost, que dentro de Docker es el contenedor' => [['BASE_EXTERNA_HOST' => 'localhost'], 'propio contenedor'],
    '127.0.0.1' => [['BASE_EXTERNA_HOST' => '127.0.0.1'], 'propio contenedor'],
    'una dirección con http' => [['BASE_EXTERNA_HOST' => 'http://10.0.0.1'], 'no es una dirección'],
    'un puerto que no es un número' => [['BASE_EXTERNA_PUERTO' => 'cinco'], 'puerto'],
    'sin el nombre de la base' => [['BASE_EXTERNA_NOMBRE' => ''], 'nombre de la base'],
    'sin usuario' => [['BASE_EXTERNA_USUARIO' => ''], 'usuario'],
    'sin contraseña' => [['BASE_EXTERNA_CONTRASENA' => ''], 'contraseña'],
    'una contraseña que el .env no maneja' => [['BASE_EXTERNA_CONTRASENA' => 'tiene un espacio y $'], 'contraseña'],
]);

it('no cambia la base de un .env que ya existe aunque se le den otros datos', function (): void {
    configurarConElInstalador($this->env, ['APP_URL' => DIRECCION_CON_HTTPS] + BASE_AJENA);

    configurarConElInstalador($this->env, ['BASE_EXTERNA_HOST' => '10.99.99.99', 'BASE_EXTERNA_NOMBRE' => 'otra']);

    expect(variablesDe($this->env))->toMatchArray(['DB_HOST' => '10.20.30.40', 'DB_DATABASE' => 'laboratorio', 'COMPOSE_PROFILES' => '']);
});

it('completa un .env sin perfil que usa la base del compose, para que db siga levantándose', function (): void {
    // Un .env de antes de que db fuera opcional: sin COMPOSE_PROFILES el
    // compose dejaría de levantar la base. Lo corrige la orden "configurar".
    file_put_contents($this->env, "DB_HOST=db\nAPP_URL=".DIRECCION_CON_HTTPS."\n");

    configurarConElInstalador($this->env);

    expect(variablesDe($this->env)['COMPOSE_PROFILES'])->toBe('base-propia');
});

it('deja la carpeta de las copias dentro del clon si no se dice otra, sin pedir sudo', function (): void {
    configurarConElInstalador($this->env, ['APP_URL' => DIRECCION_CON_HTTPS]);

    expect(variablesDe($this->env)['DIRECTORIO_COPIAS'])->toBe(base_path('copias'));
});

it('hace opcional el servicio db en el compose: perfil base-propia y dependencias con required: false', function (): void {
    $compose = (string) file_get_contents(base_path('docker-compose.produccion.yml'));

    preg_match('/\n  db:\n((?:    .*\n|\n)+)/', $compose, $bloque);

    expect($bloque[1] ?? '')->toContain('profiles: ["base-propia"]')
        // Sin required: false, Compose rechaza el proyecto cuando db no está activo.
        ->and(substr_count($compose, "condition: service_healthy\n        required: false"))
        ->toBe(substr_count($compose, 'condition: service_healthy'))
        ->and($compose)->toContain('PGHOST: ${DB_HOST:-db}')
        ->toContain('PGPORT: ${DB_PORT:-5432}');
});
