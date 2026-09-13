<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Caso base de las pruebas
|--------------------------------------------------------------------------
|
| Los tests de "Feature" arrancan la aplicación completa y migran una base de
| datos limpia en cada uno. Los de "Unit" no tocan Laravel: son para lógica
| aislada de los Services.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    // Los tests de vistas comprueban qué se pinta y quién puede verlo, no el
    // empaquetado de los assets: sin esto exigirían un "npm run build" previo
    // y fallarían en un entorno limpio. Que Vite compile de verdad lo prueba
    // el job de Docker del CI, que levanta el entorno completo.
    ->beforeEach(fn () => $this->withoutVite())
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');
