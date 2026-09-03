<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

it('sirve la página de bienvenida', function (): void {
    $this->get('/')->assertOk();
});

it('está conectada a PostgreSQL', function (): void {
    expect(DB::connection()->getDriverName())->toBe('pgsql');
});

it('crea las tablas de spatie/laravel-permission al migrar', function (): void {
    expect(Schema::hasTable('roles'))->toBeTrue()
        ->and(Schema::hasTable('permissions'))->toBeTrue()
        ->and(Schema::hasTable('model_has_roles'))->toBeTrue()
        ->and(Schema::hasTable('model_has_permissions'))->toBeTrue()
        ->and(Schema::hasTable('role_has_permissions'))->toBeTrue();
});

it('asigna roles a un usuario', function (): void {
    Role::create(['name' => 'docente']);
    $usuario = User::factory()->create();

    $usuario->assignRole('docente');

    expect($usuario->hasRole('docente'))->toBeTrue()
        ->and($usuario->hasRole('coordinador'))->toBeFalse();
});
