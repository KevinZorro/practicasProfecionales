<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Rol;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Los cinco roles de la plataforma. Un usuario puede tener varios a la vez:
 * una coordinadora puede además ser docente.
 */
class RolSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Rol::cases() as $rol) {
            Role::updateOrCreate(
                ['name' => $rol->value, 'guard_name' => 'web'],
                ['descripcion' => $rol->descripcion()],
            );
        }
    }
}
