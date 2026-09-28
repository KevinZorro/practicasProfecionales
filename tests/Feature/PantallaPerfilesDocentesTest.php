<?php

declare(strict_types=1);

use App\Enums\Rol;
use App\Filament\Resources\PerfilDocenteResource;
use App\Filament\Resources\PerfilDocenteResource\Pages\CreatePerfilDocente;
use App\Filament\Resources\PerfilDocenteResource\Pages\EditPerfilDocente;
use App\Filament\Resources\PerfilDocenteResource\Pages\ListPerfilesDocentes;
use App\Models\PerfilDocente;
use App\Models\TituloDocente;
use App\Models\User;
use App\Services\AsignacionDeRolService;
use App\Services\ImagenPublicaService;
use Database\Seeders\RolSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Perfiles docentes de la landing (RF07, RF16), con sus títulos.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    Storage::fake(ImagenPublicaService::DISCO);
    $this->admin = User::factory()->admin()->create();
});

it('deja al ADMIN gestionar perfiles docentes', function (): void {
    $perfil = PerfilDocente::factory()->create();

    expect($this->admin->can('viewAny', PerfilDocente::class))->toBeTrue()
        ->and($this->admin->can('create', PerfilDocente::class))->toBeTrue()
        ->and($this->admin->can('update', $perfil))->toBeTrue()
        ->and($this->admin->can('delete', $perfil))->toBeTrue()
        ->and($this->admin->can('reorder', PerfilDocente::class))->toBeTrue();
});

it('no deja gestionar perfiles docentes a ningún otro rol, ni al propio docente', function (string $estado): void {
    $usuario = User::factory()->{$estado}()->create();
    $perfil = PerfilDocente::factory()->create(['user_id' => $usuario->id]);

    expect($usuario->can('viewAny', PerfilDocente::class))->toBeFalse()
        ->and($usuario->can('update', $perfil))->toBeFalse()
        ->and($usuario->can('delete', $perfil))->toBeFalse();
})->with(['coordinador', 'administrativo', 'docente', 'estudiante']);

it('abre el listado, el alta y la edición al ADMIN', function (): void {
    $perfil = PerfilDocente::factory()->create();
    $this->actingAs($this->admin);

    expect(PerfilDocenteResource::getUrl('index'))->toEndWith('/admin/perfiles-docentes');

    $this->get(PerfilDocenteResource::getUrl('index'))->assertOk();
    $this->get(PerfilDocenteResource::getUrl('create'))->assertOk();
    $this->get(PerfilDocenteResource::getUrl('edit', ['record' => $perfil]))->assertOk();
});

it('no abre los perfiles a otro rol', function (): void {
    $this->actingAs(User::factory()->coordinador()->create())
        ->get(PerfilDocenteResource::getUrl('index'))
        ->assertForbidden();
});

it('crea un perfil con foto, cuenta enlazada y títulos en orden', function (): void {
    $docente = User::factory()->docente()->create();

    Livewire::actingAs($this->admin)
        ->test(CreatePerfilDocente::class)
        ->fillForm([
            'nombre' => 'Laura Pérez',
            'cargo' => 'Docente de simulación',
            'user_id' => $docente->id,
            'foto' => UploadedFile::fake()->image('laura.jpg', 1200, 1600),
            'activo' => true,
            'titulos' => [
                ['titulo' => 'Enfermera', 'institucion' => 'Universidad Francisco de Paula Santander'],
                ['titulo' => 'Especialista en cuidado crítico', 'institucion' => 'Universidad de Pamplona'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $perfil = PerfilDocente::where('nombre', 'Laura Pérez')->firstOrFail();

    expect($perfil->user_id)->toBe($docente->id)
        ->and($perfil->foto)->toStartWith('perfiles/')->toEndWith('.webp')
        ->and($perfil->titulos->pluck('titulo')->all())->toBe(['Enfermera', 'Especialista en cuidado crítico'])
        ->and($perfil->titulos->pluck('orden')->all())->toBe([1, 2]);
});

it('ofrece para enlazar solo cuentas con rol docente', function (): void {
    User::factory()->docente()->create(['nombre' => 'Andrea Docente']);
    User::factory()->estudiante()->create(['nombre' => 'Andrea Estudiante']);

    $opciones = Livewire::actingAs($this->admin)
        ->test(CreatePerfilDocente::class)
        ->instance()
        ->form
        ->getComponent('data.user_id')
        ->getSearchResults('Andrea');

    expect(array_values($opciones))->toBe(['Andrea Docente']);
});

it('no ofrece a quien tuvo el rol docente y ya le venció', function (): void {
    // Regla 13: roles() filtra por vigencia, y el selector se apoya en ella.
    $pasante = User::factory()->create(['nombre' => 'Andrea Pasante']);
    app(AsignacionDeRolService::class)->asignar($this->admin, $pasante, Rol::Docente, hasta: now()->toDateString());

    $buscar = fn () => Livewire::actingAs($this->admin)
        ->test(CreatePerfilDocente::class)
        ->instance()
        ->form
        ->getComponent('data.user_id')
        ->getSearchResults('Andrea');

    expect(array_values($buscar()))->toBe(['Andrea Pasante']);

    $this->travel(1)->days();

    expect($buscar())->toBe([]);
});

it('crea un perfil sin cuenta ni títulos', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreatePerfilDocente::class)
        ->fillForm(['nombre' => 'Invitada externa', 'cargo' => 'Instructora'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(PerfilDocente::firstOrFail()->user_id)->toBeNull();
});

it('no guarda un título sin institución', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreatePerfilDocente::class)
        ->fillForm([
            'nombre' => 'Laura Pérez',
            'cargo' => 'Docente',
            'titulos' => [['titulo' => 'Enfermera', 'institucion' => '']],
        ])
        ->call('create')
        ->assertHasFormErrors();

    expect(PerfilDocente::count())->toBe(0)
        ->and(TituloDocente::count())->toBe(0);
});

it('borra la foto anterior al reemplazarla', function (): void {
    Storage::disk(ImagenPublicaService::DISCO)->put('perfiles/vieja.webp', 'x');
    $perfil = PerfilDocente::factory()->create(['foto' => 'perfiles/vieja.webp']);

    Livewire::actingAs($this->admin)
        ->test(EditPerfilDocente::class, ['record' => $perfil->getRouteKey()])
        ->set('data.foto', [UploadedFile::fake()->image('nueva.jpg', 600, 800)])
        ->call('save')
        ->assertHasNoFormErrors();

    Storage::disk(ImagenPublicaService::DISCO)->assertMissing('perfiles/vieja.webp');
});

it('borra el perfil con sus títulos y su foto', function (): void {
    Storage::disk(ImagenPublicaService::DISCO)->put('perfiles/laura.webp', 'x');
    $perfil = PerfilDocente::factory()->create(['foto' => 'perfiles/laura.webp']);
    TituloDocente::factory()->create(['perfil_docente_id' => $perfil->id]);

    Livewire::actingAs($this->admin)
        ->test(ListPerfilesDocentes::class)
        ->callTableAction('delete', $perfil);

    expect(PerfilDocente::count())->toBe(0)
        ->and(TituloDocente::count())->toBe(0);
    Storage::disk(ImagenPublicaService::DISCO)->assertMissing('perfiles/laura.webp');
});
