<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roles con vigencia (RF63, RF64).
 *
 * Dos piezas con trabajos distintos:
 *
 * 1. Columnas en el pivote de spatie: es lo que se hace cumplir. El filtro
 *    de User::roles() las lee en SQL, así que un rol vencido deja de valer
 *    en la consulta misma, sin que haya corrido ningún job.
 *
 * 2. Tabla asignaciones_de_rol, de solo añadir: es el rastro. Hace falta
 *    aparte porque la llave primaria del pivote es (role_id, model_id,
 *    model_type), así que solo cabe una fila por usuario y rol: si un
 *    pasante viene, se va y vuelve, la segunda asignación pisaría las
 *    fechas de la primera y se perdería la historia.
 *
 * Es el mismo patrón que la regla 11 y la regla 12 del CLAUDE.md: valor
 * vigente desnormalizado para consultar barato, historial de solo añadir
 * para responder quién y cuándo.
 *
 * "desde" y "hasta" son nulables y nulo quiere decir "sin límite por ese
 * lado": así attach() sigue funcionando sin pasar las columnas, que es lo
 * que hacen los seeders y las factories a través de assignRole(), y los
 * roles reales de hoy no cambian de significado.
 *
 * Nulo y no un default CURRENT_DATE, que sería el reloj de PostgreSQL: en
 * la franja entre las 7 de la tarde y medianoche de Bogotá, una base en UTC
 * ya está en el día siguiente y el rol nacería sin valer hasta mañana. El
 * Service escribe la fecha en la zona de la aplicación, como la frontera de
 * las listas de reposición.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->pivote(), function (Blueprint $tabla): void {
            $tabla->date('desde')->nullable();
            $tabla->date('hasta')->nullable();
        });

        // Quién vence pronto se pregunta por esta columna, y el filtro de
        // User::roles() la compara en cada consulta de permisos.
        Schema::table($this->pivote(), function (Blueprint $tabla): void {
            $tabla->index('hasta', 'model_has_roles_hasta_index');
        });

        Schema::create('asignaciones_de_rol', function (Blueprint $tabla): void {
            $tabla->id();
            // restrict en todas: borrar un usuario o un rol rompería el
            // histórico, y los usuarios no se borran, se desactivan.
            $tabla->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $tabla->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            // Nulables por lo mismo que en el pivote: de un rol repartido
            // antes de que existiera el historial no consta desde cuándo.
            $tabla->date('desde')->nullable();
            $tabla->date('hasta')->nullable();
            $tabla->text('motivo')->nullable();
            // Nulable a propósito: el historial arranca con esta migración,
            // así que los roles repartidos antes —los del seeder— no tienen
            // a quién atribuirlos. "No consta" es más honesto que inventar un
            // responsable al revocarlos.
            $tabla->foreignId('asignado_por')->nullable()->constrained('users')->restrictOnDelete();
            $tabla->timestamp('revocada_at')->nullable();
            $tabla->foreignId('revocada_por')->nullable()->constrained('users')->restrictOnDelete();
            $tabla->timestamps();

            $tabla->index(['user_id', 'role_id']);
            $tabla->index('hasta');
        });

        DB::statement(
            'ALTER TABLE asignaciones_de_rol ADD CONSTRAINT asignaciones_de_rol_rango_coherente '
            .'CHECK (hasta IS NULL OR hasta >= desde)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones_de_rol');

        Schema::table($this->pivote(), function (Blueprint $tabla): void {
            $tabla->dropIndex('model_has_roles_hasta_index');
            $tabla->dropColumn(['desde', 'hasta']);
        });
    }

    /** El nombre lo fija config/permission.php, no se escribe a mano. */
    private function pivote(): string
    {
        return config('permission.table_names.model_has_roles');
    }
};
