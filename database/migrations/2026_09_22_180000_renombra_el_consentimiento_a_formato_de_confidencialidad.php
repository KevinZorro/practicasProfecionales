<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El documento siempre fue uno solo; le estábamos diciendo como no se llama.
 *
 * El laboratorio lo llama "formato de confidencialidad" —así está rotulada
 * la carpeta del Drive— e incluye la autorización de captación de imágenes.
 * "Consentimiento informado" era nuestro nombre, no el suyo, y el §5 del
 * CLAUDE.md pide el vocabulario del dominio tal cual lo usa el cliente.
 *
 * Y lo firma todo el que entra a la práctica, docente incluido (RF51-RF52),
 * así que "estudiante_id" pasa a "firmante_id". La llave siempre apuntó a
 * users a secas: lo que cambia es el nombre, no a quién puede apuntar.
 *
 * Los índices y las restricciones se renombran a mano: PostgreSQL no los
 * arrastra al renombrar la tabla, y un índice "consentimientos_estudiante_*"
 * colgando de "formatos_confidencialidad" es justo la pista falsa que el
 * mantenedor futuro no necesita.
 */
return new class extends Migration
{
    /** @var array<string, array<string, string>> tabla => [nombre viejo => nombre nuevo] */
    private const RESTRICCIONES = [
        'formatos_confidencialidad' => [
            'consentimientos_estudiante_pkey' => 'formatos_confidencialidad_pkey',
            'consentimientos_estudiante_estudiante_id_periodo_academico_uniq' => 'formatos_confidencialidad_firmante_periodo_unico',
            'consentimientos_estudiante_estudiante_id_foreign' => 'formatos_confidencialidad_firmante_id_foreign',
            'consentimientos_estudiante_plantilla_id_foreign' => 'formatos_confidencialidad_plantilla_id_foreign',
            'consentimientos_estudiante_verificado_por_foreign' => 'formatos_confidencialidad_verificado_por_foreign',
            'consentimientos_estudiante_recibido_fisico_por_foreign' => 'formatos_confidencialidad_recibido_fisico_por_foreign',
        ],
        'plantillas_confidencialidad' => [
            'consentimientos_plantilla_pkey' => 'plantillas_confidencialidad_pkey',
            'consentimientos_plantilla_subido_por_foreign' => 'plantillas_confidencialidad_subido_por_foreign',
        ],
    ];

    /** Índices que no respaldan ninguna restricción: se renombran con ALTER INDEX. */
    private const INDICES = [
        'consentimientos_estudiante_periodo_academico_estado_index' => 'formatos_confidencialidad_periodo_academico_estado_index',
        'consentimientos_plantilla_activo_index' => 'plantillas_confidencialidad_activo_index',
    ];

    private const SECUENCIAS = [
        'consentimientos_estudiante_id_seq' => 'formatos_confidencialidad_id_seq',
        'consentimientos_plantilla_id_seq' => 'plantillas_confidencialidad_id_seq',
    ];

    public function up(): void
    {
        Schema::rename('consentimientos_plantilla', 'plantillas_confidencialidad');
        Schema::rename('consentimientos_estudiante', 'formatos_confidencialidad');

        Schema::table('formatos_confidencialidad', function (Blueprint $tabla): void {
            $tabla->renameColumn('estudiante_id', 'firmante_id');
        });

        $this->renombrarRestricciones(self::RESTRICCIONES);
        $this->renombrarIndices(self::INDICES);
        $this->renombrarSecuencias(self::SECUENCIAS);
    }

    public function down(): void
    {
        $this->renombrarRestricciones($this->alReves(self::RESTRICCIONES));
        $this->renombrarIndices(array_flip(self::INDICES));
        $this->renombrarSecuencias(array_flip(self::SECUENCIAS));

        Schema::table('formatos_confidencialidad', function (Blueprint $tabla): void {
            $tabla->renameColumn('firmante_id', 'estudiante_id');
        });

        Schema::rename('formatos_confidencialidad', 'consentimientos_estudiante');
        Schema::rename('plantillas_confidencialidad', 'consentimientos_plantilla');
    }

    /** @param array<string, array<string, string>> $porTabla */
    private function renombrarRestricciones(array $porTabla): void
    {
        foreach ($porTabla as $tabla => $nombres) {
            foreach ($nombres as $de => $a) {
                DB::statement(sprintf('ALTER TABLE %s RENAME CONSTRAINT %s TO %s', $tabla, $de, $a));
            }
        }
    }

    /** @param array<string, string> $nombres */
    private function renombrarIndices(array $nombres): void
    {
        foreach ($nombres as $de => $a) {
            DB::statement(sprintf('ALTER INDEX %s RENAME TO %s', $de, $a));
        }
    }

    /** @param array<string, string> $nombres */
    private function renombrarSecuencias(array $nombres): void
    {
        foreach ($nombres as $de => $a) {
            DB::statement(sprintf('ALTER SEQUENCE %s RENAME TO %s', $de, $a));
        }
    }

    /**
     * Para down(): las restricciones se renombran cuando las tablas todavía
     * llevan el nombre nuevo, así que la clave del mapa no se invierte; solo
     * el par de nombres.
     *
     * @param  array<string, array<string, string>>  $porTabla
     * @return array<string, array<string, string>>
     */
    private function alReves(array $porTabla): array
    {
        return array_map(static fn (array $nombres): array => array_flip($nombres), $porTabla);
    }
};
