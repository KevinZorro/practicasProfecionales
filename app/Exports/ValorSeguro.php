<?php

declare(strict_types=1);

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * Cómo se escribe cada valor en una hoja de Excel (QA-08, CWE-1236).
 *
 * Por defecto, PhpSpreadsheet guarda como fórmula todo texto que empieza por
 * «=». Los textos de los reportes vienen de personas (una descripción en la
 * lista de reposición, un nombre en la fuente institucional) y los abren
 * otras: una fórmula como =HYPERLINK(…) podría filtrar datos de la hoja o
 * mandar a un enlace engañoso. Aquí todo texto es texto; los números siguen
 * siendo números, para poder sumarlos.
 */
final class ValorSeguro extends DefaultValueBinder
{
    /** Los que una hoja, o quien la copie a otra, puede tomar por fórmula. */
    private const INICIOS_PELIGROSOS = ['=', '+', '-', '@', "\t", "\r"];

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value) && $value !== '' && in_array($value[0], self::INICIOS_PELIGROSOS, true)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
