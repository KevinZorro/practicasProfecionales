<?php

declare(strict_types=1);

use App\Filament\RecursoDelAdmin;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/*
 * Filament consulta las Policies, pero si el modelo no tiene Policy o la
 * Policy no define el método, por defecto PERMITE. RecursoDelAdmin lo
 * invierte. Este test vigila que todos los recursos del panel hereden de
 * ella y que cada modelo tenga su Policy antes de tener pantalla.
 */

/** Modelo de prueba: no tiene ni tendrá Policy. */
final class ModeloSinPolicy extends Model {}

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->admin()->create());
});

it('hace que cada recurso del panel herede de RecursoDelAdmin y tenga Policy', function (): void {
    $recursos = Filament::getPanel('admin')->getResources();

    // Todo archivo de recurso tiene que estar en el panel: si el
    // descubrimiento no lo encuentra, la pantalla no existe y nadie avisa.
    $archivos = glob(app_path('Filament/Resources/*Resource.php')) ?: [];
    expect($recursos)->toHaveCount(count($archivos));

    foreach ($recursos as $recurso) {
        expect(is_subclass_of($recurso, RecursoDelAdmin::class))
            ->toBeTrue("{$recurso} tiene que heredar de RecursoDelAdmin: si no, Filament permite lo que la Policy no define.");

        $modelo = $recurso::getModel();

        expect(Gate::getPolicyFor($modelo))
            ->not->toBeNull("{$modelo} no tiene Policy, o su nombre no coincide con el del modelo. Créala antes que la pantalla.");
    }
});

it('deniega lo que no está definido en ninguna Policy', function (): void {
    $recurso = new class extends RecursoDelAdmin
    {
        protected static ?string $model = ModeloSinPolicy::class;
    };

    expect($recurso::can('viewAny'))->toBeFalse()
        ->and($recurso::can('create'))->toBeFalse()
        ->and($recurso::can('deleteAny'))->toBeFalse();
});

it('comprueba que sin la clase base Filament lo permitiría', function (): void {
    // Control: si Filament cambiara su comportamiento por defecto, el test
    // anterior pasaría por otra razón y esta base sobraría.
    $recurso = new class extends Resource
    {
        protected static ?string $model = ModeloSinPolicy::class;
    };

    expect($recurso::can('viewAny'))->toBeTrue();
});
