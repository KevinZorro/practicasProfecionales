<?php

declare(strict_types=1);

use App\Enums\EstadoSolicitud;
use App\Enums\Rol;
use App\Enums\TipoItemInventario;
use App\Enums\TipoSesion;
use App\Models\CasoClinico;
use App\Models\ConsentimientoEstudiante;
use App\Models\Evaluacion;
use App\Models\ItemInventario;
use App\Models\Materia;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\TipoEvaluacion;
use App\Models\User;
use Database\Seeders\DatosPruebaSeeder;
use Database\Seeders\RolSeeder;
use Spatie\Permission\Models\Role;

it('registra los cinco roles con su descripción', function (): void {
    $this->seed(RolSeeder::class);

    expect(Role::count())->toBe(5);

    foreach (Rol::cases() as $rol) {
        $registro = Role::where('name', $rol->value)->first();

        expect($registro)->not->toBeNull()
            ->and($registro->descripcion)->toBe($rol->descripcion());
    }
});

it('no duplica roles al ejecutarse dos veces', function (): void {
    $this->seed(RolSeeder::class);
    $this->seed(RolSeeder::class);

    expect(Role::count())->toBe(5);
});

describe('DatosPruebaSeeder', function (): void {
    beforeEach(function (): void {
        $this->seed(RolSeeder::class);
        $this->seed(DatosPruebaSeeder::class);
    });

    it('carga la estructura académica y el inventario', function (): void {
        expect(Materia::count())->toBe(6)
            ->and(CasoClinico::count())->toBe(6)
            ->and(Sala::count())->toBe(5)
            ->and(TipoEvaluacion::count())->toBe(5)
            ->and(ItemInventario::simuladores()->count())->toBe(6);
    });

    it('crea usuarios de los cinco roles', function (): void {
        foreach (Rol::cases() as $rol) {
            expect(User::role($rol->value)->count())
                ->toBeGreaterThan(0, "no hay usuarios con el rol {$rol->value}");
        }
    });

    it('crea una coordinadora que además es docente', function (): void {
        $coordinadora = User::role(Rol::Coordinador->value)->firstOrFail();

        expect($coordinadora->hasRole(Rol::Docente->value))->toBeTrue();
    });

    it('deja el nivel de fidelidad solo en los simuladores', function (): void {
        ItemInventario::all()->each(function (ItemInventario $item): void {
            $item->tipo === TipoItemInventario::Simulador
                ? expect($item->nivel_fidelidad)->not->toBeNull()
                : expect($item->nivel_fidelidad)->toBeNull();
        });
    });

    it('precarga en cada solicitud el inventario de su caso clínico', function (): void {
        $solicitud = Solicitud::with(['items', 'casoClinico.items'])->firstOrFail();

        expect($solicitud->items->pluck('id')->sort()->values()->all())
            ->toBe($solicitud->casoClinico->items->pluck('id')->sort()->values()->all());
    });

    it('cubre los cuatro estados de solicitud', function (): void {
        foreach (EstadoSolicitud::cases() as $estado) {
            expect(Solicitud::enEstado($estado)->count())
                ->toBeGreaterThan(0, "no hay solicitudes en estado {$estado->value}");
        }
    });

    it('crea la preparación solo de las solicitudes aprobadas', function (): void {
        Solicitud::with('preparacion')->get()->each(function (Solicitud $solicitud): void {
            if ($solicitud->estado !== EstadoSolicitud::Aprobada) {
                expect($solicitud->preparacion)->toBeNull();
            }
        });

        expect(Solicitud::aprobadas()->has('preparacion')->count())->toBe(3);
    });

    it('registra la evaluación sobre una solicitud aprobada de tipo evaluación', function (): void {
        $evaluacion = Evaluacion::with('solicitud')->firstOrFail();

        expect($evaluacion->solicitud->tipo)->toBe(TipoSesion::Evaluacion)
            ->and($evaluacion->solicitud->estado)->toBe(EstadoSolicitud::Aprobada);
    });

    it('copia el checklist en la evaluación en lugar de referenciarlo', function (): void {
        $evaluacion = Evaluacion::with(['items', 'tipoEvaluacion.itemsChecklist'])->firstOrFail();
        $plantilla = $evaluacion->tipoEvaluacion->itemsChecklist;

        expect($evaluacion->items)->toHaveCount($plantilla->count())
            ->and($evaluacion->items->pluck('descripcion')->all())->toBe($plantilla->pluck('descripcion')->all());

        // Editar la plantilla no altera la evaluación ya registrada.
        $plantilla->first()->update(['descripcion' => 'Texto cambiado por el ADMIN']);

        expect($evaluacion->fresh()->items->pluck('descripcion'))
            ->not->toContain('Texto cambiado por el ADMIN');
    });

    it('entrega un consentimiento por estudiante en el periodo vigente', function (): void {
        $estudiantes = User::role(Rol::Estudiante->value)->count();

        expect(ConsentimientoEstudiante::delPeriodo('2026-2')->count())->toBe($estudiantes);
    });
});
