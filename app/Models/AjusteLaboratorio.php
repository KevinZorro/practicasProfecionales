<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AjusteDelLaboratorio;
use Illuminate\Database\Eloquent\Model;

/** Un ajuste del laboratorio. Las claves son fijas: AjusteDelLaboratorio. */
class AjusteLaboratorio extends Model
{
    protected $table = 'ajustes_laboratorio';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'clave',
        'valor',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clave' => AjusteDelLaboratorio::class,
        ];
    }
}
