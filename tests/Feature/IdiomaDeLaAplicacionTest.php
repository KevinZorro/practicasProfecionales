<?php

declare(strict_types=1);

use App\Enums\Rol;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

/*
 * La aplicación habla en español: validación, paginación y páginas de
 * error. Las traducciones están en lang/, escritas a mano y sin paquetes.
 */

it('arranca en español aunque el entorno no diga nada', function (): void {
    // phpunit.xml fija APP_LOCALE=es para los tests. Lo que rige en un
    // servidor cuyo .env no trae la variable es el valor por defecto del
    // archivo de configuración.
    expect(config('app.locale'))->toBe('es')
        ->and((string) file_get_contents(config_path('app.php')))->toContain("'locale' => env('APP_LOCALE', 'es')");
});

it('traduce todas las reglas de validación que trae Laravel', function (): void {
    // Si una versión nueva de Laravel añade una regla, su mensaje saldría en
    // inglés hasta que alguien lo note en pantalla. Aquí se nota antes.
    $delFramework = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
    $nuestras = require lang_path('es/validation.php');

    $claves = fn (array $mensajes): array => array_keys(Arr::dot(Arr::except($mensajes, ['custom', 'attributes'])));

    expect(array_values(array_diff($claves($delFramework), $claves($nuestras))))->toBe([]);
});

it('dice en español qué campo falla, con su nombre legible', function (): void {
    $errores = Validator::make(['casoClinicoId' => null], ['casoClinicoId' => 'required'])->errors();

    expect($errores->first('casoClinicoId'))->toBe('El campo caso clínico es obligatorio.');
});

it('pagina en español', function (): void {
    expect(__('Showing'))->toBe('Mostrando')
        ->and(__('results'))->toBe('resultados')
        ->and(__('pagination.next'))->toBe('Siguiente &raquo;');
});

it('responde en español cuando no hay permiso', function (): void {
    $this->seed(RolSeeder::class);
    $docente = User::factory()->create();
    $docente->assignRole(Rol::Docente->value);

    $this->actingAs($docente->fresh())
        ->get(route('panel.reportes'))
        ->assertForbidden()
        ->assertSee('Prohibido')
        ->assertDontSee('Forbidden');
});
