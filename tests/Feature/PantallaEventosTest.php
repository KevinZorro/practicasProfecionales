<?php

declare(strict_types=1);

use App\Filament\Resources\EventoResource;
use App\Filament\Resources\EventoResource\Pages\CreateEvento;
use App\Filament\Resources\EventoResource\Pages\EditEvento;
use App\Filament\Resources\EventoResource\Pages\ListEventos;
use App\Models\Evento;
use App\Models\TipoEvento;
use App\Models\User;
use App\Services\ImagenPublicaService;
use Database\Seeders\RolSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Eventos de la landing (RF05, RF14), con su tipo del catálogo del ADMIN.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    Storage::fake(ImagenPublicaService::DISCO);
    $this->admin = User::factory()->admin()->create();
    $this->congreso = TipoEvento::factory()->create(['nombre' => 'Congreso']);
});

/** @param array<string, mixed> $datos */
function datosDeEvento(array $datos = []): array
{
    return [
        'titulo' => 'Congreso regional de enfermería',
        'descripcion' => 'Dos días de ponencias y talleres.',
        'fecha' => '2026-11-20',
        'tipo_evento_id' => test()->congreso->id,
        'abierto_publico' => true,
        'activo' => true,
        ...$datos,
    ];
}

it('deja al ADMIN gestionar eventos', function (): void {
    $evento = Evento::factory()->create();

    expect($this->admin->can('viewAny', Evento::class))->toBeTrue()
        ->and($this->admin->can('create', Evento::class))->toBeTrue()
        ->and($this->admin->can('update', $evento))->toBeTrue()
        ->and($this->admin->can('delete', $evento))->toBeTrue()
        ->and($this->admin->can('reorder', Evento::class))->toBeTrue();
});

it('no deja gestionar eventos a ningún otro rol', function (string $estado): void {
    $usuario = User::factory()->{$estado}()->create();
    $evento = Evento::factory()->create();

    expect($usuario->can('viewAny', Evento::class))->toBeFalse()
        ->and($usuario->can('create', Evento::class))->toBeFalse()
        ->and($usuario->can('update', $evento))->toBeFalse()
        ->and($usuario->can('delete', $evento))->toBeFalse();
})->with(['coordinador', 'administrativo', 'docente', 'estudiante']);

it('abre el listado, el alta y la edición al ADMIN', function (): void {
    $evento = Evento::factory()->create();
    $this->actingAs($this->admin);

    expect(EventoResource::getUrl('index'))->toEndWith('/admin/eventos');

    $this->get(EventoResource::getUrl('index'))->assertOk();
    $this->get(EventoResource::getUrl('create'))->assertOk();
    $this->get(EventoResource::getUrl('edit', ['record' => $evento]))->assertOk();
});

it('crea un evento con su tipo y su imagen, y lo pone al final', function (): void {
    Evento::factory()->create(['titulo' => 'Evento anterior', 'orden' => 2]);

    Livewire::actingAs($this->admin)
        ->test(CreateEvento::class)
        ->fillForm(datosDeEvento(['imagen' => UploadedFile::fake()->image('congreso.jpg', 1800, 1200)]))
        ->call('create')
        ->assertHasNoFormErrors();

    $evento = Evento::where('titulo', 'Congreso regional de enfermería')->firstOrFail();

    expect($evento->tipoEvento->nombre)->toBe('Congreso')
        ->and($evento->orden)->toBe(3)
        ->and($evento->imagen)->toStartWith('eventos/')->toEndWith('.webp');
});

it('no crea un evento sin sus datos obligatorios', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreateEvento::class)
        ->fillForm(datosDeEvento(['titulo' => '', 'descripcion' => '', 'fecha' => null, 'tipo_evento_id' => null]))
        ->call('create')
        ->assertHasFormErrors(['titulo' => 'required', 'descripcion' => 'required', 'fecha' => 'required', 'tipo_evento_id' => 'required']);

    expect(Evento::count())->toBe(0);
});

it('no deja crear un evento con un tipo desactivado', function (): void {
    // El selector no lo ofrece, y el servidor lo rechaza aunque llegue.
    $retirado = TipoEvento::factory()->inactivo()->create();

    Livewire::actingAs($this->admin)
        ->test(CreateEvento::class)
        ->fillForm(datosDeEvento(['tipo_evento_id' => $retirado->id]))
        ->call('create')
        ->assertHasFormErrors(['tipo_evento_id']);

    expect(Evento::count())->toBe(0);
});

it('conserva el tipo desactivado de un evento que ya lo tenía', function (): void {
    $retirado = TipoEvento::factory()->create(['nombre' => 'Jornada vieja']);
    $evento = Evento::factory()->create(['tipo_evento_id' => $retirado->id]);
    $retirado->update(['activo' => false]);

    Livewire::actingAs($this->admin)
        ->test(EditEvento::class, ['record' => $evento->getRouteKey()])
        ->assertSee('Jornada vieja')
        ->fillForm(['titulo' => 'Otro título'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($evento->fresh()->tipo_evento_id)->toBe($retirado->id);
});

it('borra el evento y su imagen', function (): void {
    Storage::disk(ImagenPublicaService::DISCO)->put('eventos/congreso.webp', 'x');
    $evento = Evento::factory()->create(['imagen' => 'eventos/congreso.webp']);

    Livewire::actingAs($this->admin)
        ->test(ListEventos::class)
        ->callTableAction('delete', $evento);

    expect(Evento::count())->toBe(0);
    Storage::disk(ImagenPublicaService::DISCO)->assertMissing('eventos/congreso.webp');
});

it('borra la imagen anterior al reemplazarla', function (): void {
    Storage::disk(ImagenPublicaService::DISCO)->put('eventos/vieja.webp', 'x');
    $evento = Evento::factory()->create(['imagen' => 'eventos/vieja.webp']);

    Livewire::actingAs($this->admin)
        ->test(EditEvento::class, ['record' => $evento->getRouteKey()])
        ->set('data.imagen', [UploadedFile::fake()->image('nueva.jpg', 800, 600)])
        ->call('save')
        ->assertHasNoFormErrors();

    Storage::disk(ImagenPublicaService::DISCO)->assertMissing('eventos/vieja.webp');
});

it('reordena los eventos arrastrando las filas', function (): void {
    $primero = Evento::factory()->create(['orden' => 1]);
    $segundo = Evento::factory()->create(['orden' => 2]);

    Livewire::actingAs($this->admin)
        ->test(ListEventos::class)
        ->call('reorderTable', [(string) $segundo->id, (string) $primero->id]);

    expect(Evento::orderBy('orden')->pluck('id')->all())->toBe([$segundo->id, $primero->id]);
});
