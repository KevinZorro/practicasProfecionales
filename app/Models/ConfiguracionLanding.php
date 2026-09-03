<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ConfiguracionLandingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionLanding extends Model
{
    /** @use HasFactory<ConfiguracionLandingFactory> */
    use HasFactory;

    protected $table = 'configuracion_landing';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'clave',
        'valor',
    ];
}
