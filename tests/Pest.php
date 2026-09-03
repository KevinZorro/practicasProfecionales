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
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');
