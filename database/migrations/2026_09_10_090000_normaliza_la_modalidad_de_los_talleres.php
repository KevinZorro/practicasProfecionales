<?php

declare(strict_types=1);

use App\Enums\ModalidadTaller;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El RF04 define solo dos modalidades: virtual y presencial. La migración
 * inicial se creó con una tercera, "mixta", inventada cuando el valor se
 * consideraba ambiguo.
 *
 * No hay cambio de esquema: modalidad es una columna string, igual que el
 * resto de los enums del proyecto, que se validan en la capa de PHP. Lo que
 * hace falta es normalizar las filas que quedaron con el valor retirado,
 * porque al leerlas el cast a ModalidadTaller fallaría.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Un taller mixto tiene componente presencial: es el valor que menos
        // información pierde de los dos que admite el RF04.
        DB::table('talleres')
            ->whereNotIn('modalidad', array_column(ModalidadTaller::cases(), 'value'))
            ->update(['modalidad' => ModalidadTaller::Presencial->value]);
    }

    public function down(): void
    {
        // Irreversible: no se puede saber qué talleres eran mixtos.
    }
};
