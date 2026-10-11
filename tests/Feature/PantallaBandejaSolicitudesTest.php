<?php

declare(strict_types=1);

use App\Enums\EstadoSolicitud;
use App\Enums\Rol;
use App\Livewire\Solicitud\BandejaRevision;
use App\Models\Preparacion;
use App\Models\Solicitud;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

/*
 * La bandeja es la lista: cada fila lleva al expediente de la solicitud, y
 * revisar, aprobar y rechazar se hace allí (ExpedienteDeSolicitudTest).
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->administrativo = User::factory()->administrativo()->create();
});

afterEach(function (): void {
    Model::preventLazyLoading(false);
});

it('lleva desde cada fila al expediente de la solicitud', function (): void {
    $solicitud = Solicitud::factory()->create();

    Livewire::actingAs($this->administrativo)
        ->test(BandejaRevision::class)
        ->assertSee($solicitud->casoClinico->nombre)
        ->assertSee(route('panel.solicitudes.ver', $solicitud), escape: false);
});

it('dice en cada fila en qué va la solicitud', function (): void {
    Solicitud::factory()->create(['estado' => EstadoSolicitud::Pendiente]);
    Solicitud::factory()->revisada()->create();

    Livewire::actingAs($this->administrativo)
        ->test(BandejaRevision::class)
        ->assertSee('En revisión')
        ->assertSee('Por aprobar');
});

it('no decide nada desde la lista: las decisiones viven en el expediente', function (): void {
    Solicitud::factory()->create(['estado' => EstadoSolicitud::Pendiente]);

    Livewire::actingAs($this->administrativo)
        ->test(BandejaRevision::class)
        ->assertDontSee('Marcar como revisada')
        ->assertDontSee('wire:click="aprobar', escape: false);
});

it('no deja a un docente ni a un estudiante acercarse a la bandeja', function (Rol $rol): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    Livewire::actingAs($usuario->fresh())
        ->test(BandejaRevision::class)
        ->assertForbidden();
})->with([Rol::Docente, Rol::Estudiante]);

it('ordena la bandeja por la fecha de la práctica', function (): void {
    Solicitud::factory()->create(['fecha' => '2026-11-20']);
    Solicitud::factory()->create(['fecha' => '2026-10-05']);
    Solicitud::factory()->create(['fecha' => '2026-12-01']);

    Livewire::actingAs($this->administrativo)
        ->test(BandejaRevision::class)
        ->assertViewHas('solicitudes', fn ($p): bool => $p->pluck('fecha')
            ->map(fn ($f): string => $f->format('Y-m-d'))->all() === ['2026-10-05', '2026-11-20', '2026-12-01']);
});

it('filtra la bandeja por estado', function (): void {
    Solicitud::factory()->create(['estado' => EstadoSolicitud::Pendiente]);
    Solicitud::factory()->revisada()->create();

    Livewire::actingAs($this->administrativo)
        ->test(BandejaRevision::class)
        ->assertViewHas('solicitudes', fn ($p): bool => $p->total() === 2)
        ->set('estado', EstadoSolicitud::Revisada->value)
        ->assertViewHas('solicitudes', fn ($p): bool => $p->total() === 1);
});

it('no genera consultas N+1 al recorrer la bandeja', function (): void {
    Solicitud::factory()->count(6)->aprobada()->create()
        ->each(fn (Solicitud $s) => Preparacion::factory()->conSala()->create(['solicitud_id' => $s->id]));

    Model::preventLazyLoading();

    Livewire::actingAs($this->administrativo)->test(BandejaRevision::class)->assertOk();
});
