<?php

declare(strict_types=1);

use App\Enums\Rol;
use App\Livewire\Concerns\AutorizaEnCadaPeticion;
use App\Models\ItemInventario;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Facades\File;

/*
 * Una pantalla del panel ya abierta no puede seguir enseñando lo que el rol
 * activo no ve (RF21).
 *
 * El caso: la persona tiene dos roles, abre una pantalla con uno y en otra
 * pestaña cambia al otro. El rol nuevo sigue asignado, así que
 * EstablecerRolActivo no corta: lo aplica. Lo que corta es que la pantalla
 * vuelva a pedir su permiso en cada petición (AutorizaEnCadaPeticion).
 *
 * Todo con peticiones HTTP reales: Livewire::test() se salta los middleware
 * y no aplicaría el rol activo.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
});

it('hace que cada componente de Livewire del panel autorice en cada petición', function (): void {
    $sinTrait = collect(File::allFiles(app_path('Livewire')))
        ->reject(fn ($archivo): bool => str_contains($archivo->getRelativePath(), 'Concerns'))
        ->map(fn ($archivo): string => 'App\\Livewire\\'.str_replace(['/', '.php'], ['\\', ''], $archivo->getRelativePathname()))
        ->reject(fn (string $clase): bool => in_array(AutorizaEnCadaPeticion::class, class_uses_recursive($clase), true))
        ->values()
        ->all();

    expect($sinTrait)->toBe([]);
});

/**
 * Cada pantalla, con un rol que la ve y otro que la persona también tiene y
 * no la ve.
 *
 * @return array<string, array{0: Closure(): string, 1: Rol, 2: Rol}>
 */
function pantallasDelPanel(): array
{
    return [
        'bandeja de solicitudes' => [fn (): string => route('panel.solicitudes'), Rol::Administrativo, Rol::Docente],
        'mis solicitudes' => [fn (): string => route('panel.mis-solicitudes'), Rol::Docente, Rol::Administrativo],
        'nueva solicitud' => [fn (): string => route('panel.solicitudes.nueva'), Rol::Docente, Rol::Administrativo],
        'preparaciones' => [fn (): string => route('panel.preparaciones'), Rol::Administrativo, Rol::Docente],
        'inventario' => [fn (): string => route('panel.inventario'), Rol::Administrativo, Rol::Docente],
        'disponibilidad de inventario' => [fn (): string => route('panel.inventario.disponibilidad'), Rol::Administrativo, Rol::Docente],
        'nuevo ítem de inventario' => [fn (): string => route('panel.inventario.nuevo'), Rol::Administrativo, Rol::Docente],
        'editar ítem de inventario' => [fn (): string => route('panel.inventario.editar', ItemInventario::factory()->create()), Rol::Administrativo, Rol::Docente],
        'insumos por pedir' => [fn (): string => route('panel.reposicion'), Rol::Coordinador, Rol::Docente],
        'verificación de formatos' => [fn (): string => route('panel.formatos-confidencialidad'), Rol::Administrativo, Rol::Docente],
        'estado de los firmantes' => [fn (): string => route('panel.formatos-confidencialidad.estado'), Rol::Administrativo, Rol::Docente],
        'mi formato' => [fn (): string => route('panel.mi-formato'), Rol::Docente, Rol::Administrativo],
        'plantillas del formato' => [fn (): string => route('panel.plantillas-confidencialidad'), Rol::Admin, Rol::Docente],
        'roles de usuarios' => [fn (): string => route('panel.usuarios'), Rol::Admin, Rol::Docente],
        'reportes' => [fn (): string => route('panel.reportes'), Rol::Coordinador, Rol::Docente],
    ];
}

function conLosDosRoles(Rol $uno, Rol $otro): User
{
    $usuario = User::factory()->create();
    $usuario->assignRole($uno->value, $otro->value);

    return $usuario->fresh();
}

it('deja de responder a la pantalla abierta cuando se cambia a un rol que no la ve', function (Closure $ruta, Rol $conPermiso, Rol $sinPermiso): void {
    $this->actingAs(conLosDosRoles($conPermiso, $sinPermiso))
        ->post(route('panel.rol-activo'), ['rol' => $conPermiso->value]);
    $instantanea = instantaneaDeLaPantalla($ruta());

    // En otra pestaña.
    $this->post(route('panel.rol-activo'), ['rol' => $sinPermiso->value]);
    app()->forgetScopedInstances();

    // Lo que manda el navegador al refrescar el componente o cambiar un
    // filtro: sin acción propia que autorice nada.
    accionDeLivewire($instantanea)->assertForbidden();
})->with(pantallasDelPanel());

it('sigue respondiendo a la pantalla abierta si el rol activo no cambia', function (Closure $ruta, Rol $conPermiso, Rol $sinPermiso): void {
    // El control del test anterior: la misma petición, sin cambiar de rol.
    $this->actingAs(conLosDosRoles($conPermiso, $sinPermiso))
        ->post(route('panel.rol-activo'), ['rol' => $conPermiso->value]);
    $instantanea = instantaneaDeLaPantalla($ruta());
    app()->forgetScopedInstances();

    accionDeLivewire($instantanea)->assertOk();
})->with(pantallasDelPanel());
