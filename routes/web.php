<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AccesoDeDesarrolloController;
use App\Http\Controllers\Auth\SalirController;
use App\Http\Controllers\Panel\CalendarioController;
use App\Http\Controllers\Panel\CasoClinicoController;
use App\Http\Controllers\Panel\ConfidencialidadController;
use App\Http\Controllers\Panel\DescargaConfidencialidadController;
use App\Http\Controllers\Panel\InventarioController;
use App\Http\Controllers\Panel\PanelController;
use App\Http\Controllers\Panel\PreparacionController;
use App\Http\Controllers\Panel\ReposicionController;
use App\Http\Controllers\Panel\SelectorDeRolController;
use App\Http\Controllers\Panel\SolicitudController;
use App\Support\MenuDelPanel;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('inicio.publico');

Route::post('salir', SalirController::class)->name('salir');

/*
|--------------------------------------------------------------------------
| Panel interno
|--------------------------------------------------------------------------
|
| Las rutas del panel salen del propio menú: una sección de la navegación es
| una ruta, y no hay forma de que las dos listas se desincronicen. Cada una
| apunta de momento a un marcador de posición; se irán reemplazando por la
| pantalla de su módulo.
|
*/

Route::middleware(['auth', 'rol.activo'])->prefix('panel')->name('panel.')->group(function (): void {
    // Secciones ya construidas. Se declaran antes del marcador de posición
    // para que este solo cubra las que aún no tienen pantalla.
    $construidas = ['mis-solicitudes', 'solicitudes', 'calendario', 'preparaciones', 'inventario', 'formatos-confidencialidad', 'mi-formato', 'plantillas-confidencialidad', 'casos-clinicos', 'reposicion'];

    Route::get('mis-solicitudes', [SolicitudController::class, 'mias'])->name('mis-solicitudes');
    Route::get('solicitudes/nueva', [SolicitudController::class, 'nueva'])->name('solicitudes.nueva');
    Route::get('solicitudes', [SolicitudController::class, 'bandeja'])->name('solicitudes');

    Route::get('preparaciones', PreparacionController::class)->name('preparaciones');

    Route::get('casos-clinicos', CasoClinicoController::class)->name('casos-clinicos');

    /*
     * Lista de insumos por pedir (RF67). Las descargas solo existen para
     * listas ya cerradas: el soporte de una solicitud de compra no puede
     * cambiar después de entregarlo.
     */
    Route::get('reposicion', [ReposicionController::class, 'index'])->name('reposicion');
    Route::get('reposicion/{lista}/excel', [ReposicionController::class, 'excel'])->name('reposicion.excel');
    Route::get('reposicion/{lista}/pdf', [ReposicionController::class, 'pdf'])->name('reposicion.pdf');

    Route::get('inventario/nuevo', [InventarioController::class, 'nuevo'])->name('inventario.nuevo');
    Route::get('inventario/disponibilidad', [InventarioController::class, 'disponibilidad'])->name('inventario.disponibilidad');
    Route::get('inventario/{item}/editar', [InventarioController::class, 'editar'])->name('inventario.editar');
    Route::get('inventario', [InventarioController::class, 'index'])->name('inventario');

    /*
     * Formato de confidencialidad. Los archivos no se sirven por enlace
     * directo: las tres rutas de descarga leen del disco privado y solo
     * después de que la Policy lo autorice (RNF07).
     */
    Route::get('mi-formato', [ConfidencialidadController::class, 'mio'])->name('mi-formato');
    Route::get('formatos-confidencialidad', [ConfidencialidadController::class, 'bandeja'])->name('formatos-confidencialidad');
    Route::get('formatos-confidencialidad/estado', [ConfidencialidadController::class, 'estado'])->name('formatos-confidencialidad.estado');
    Route::get('plantillas-confidencialidad', [ConfidencialidadController::class, 'plantillas'])->name('plantillas-confidencialidad');

    Route::get('formatos-confidencialidad/plantilla', [DescargaConfidencialidadController::class, 'plantilla'])->name('formatos-confidencialidad.plantilla');
    Route::get('formatos-confidencialidad/plantilla/{plantilla}', [DescargaConfidencialidadController::class, 'versionDePlantilla'])->name('formatos-confidencialidad.version');
    Route::get('formatos-confidencialidad/{entrega}/documento', [DescargaConfidencialidadController::class, 'firmado'])->name('formatos-confidencialidad.firmado');

    Route::get('calendario', [CalendarioController::class, 'index'])->name('calendario');
    Route::get('calendario/eventos', [CalendarioController::class, 'eventos'])->name('calendario.eventos');

    foreach ((new MenuDelPanel)->todas() as $seccion) {
        if (in_array($seccion->clave, $construidas, true)) {
            continue;
        }

        Route::get($seccion->clave === 'inicio' ? '/' : $seccion->clave, PanelController::class)
            ->defaults('seccion', $seccion->clave)
            ->name($seccion->clave);
    }

    Route::post('rol-activo', SelectorDeRolController::class)->name('rol-activo');
});

/*
|--------------------------------------------------------------------------
| Acceso de desarrollo
|--------------------------------------------------------------------------
|
| Provisional, hasta que lleguen las credenciales de Google OAuth (RF18).
| Se registra solo en local, y además el middleware comprueba el entorno en
| cada petición para que una caché de rutas generada en desarrollo no pueda
| abrir esta puerta en producción.
|
*/

if (app()->environment('local')) {
    Route::middleware('solo.desarrollo')->prefix('desarrollo')->group(function (): void {
        Route::get('acceso', [AccesoDeDesarrolloController::class, 'formulario'])->name('login');
        Route::post('acceso', [AccesoDeDesarrolloController::class, 'entrar'])->name('desarrollo.entrar');
    });
}
