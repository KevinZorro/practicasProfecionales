<?php

declare(strict_types=1);

use App\Enums\Reporte;
use App\Http\Controllers\Auth\AccesoConGoogleController;
use App\Http\Controllers\Auth\AccesoDeDesarrolloController;
use App\Http\Controllers\Auth\SalirController;
use App\Http\Controllers\Panel\BloqueoController;
use App\Http\Controllers\Panel\CalendarioController;
use App\Http\Controllers\Panel\ConfidencialidadController;
use App\Http\Controllers\Panel\DescargaConfidencialidadController;
use App\Http\Controllers\Panel\EvaluacionController;
use App\Http\Controllers\Panel\InventarioController;
use App\Http\Controllers\Panel\PanelController;
use App\Http\Controllers\Panel\PeriodoAcademicoController;
use App\Http\Controllers\Panel\PreparacionController;
use App\Http\Controllers\Panel\ReporteController;
use App\Http\Controllers\Panel\ReposicionController;
use App\Http\Controllers\Panel\SelectorDeRolController;
use App\Http\Controllers\Panel\SolicitudController;
use App\Http\Controllers\Panel\UsuarioController;
use App\Support\MenuDelPanel;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('inicio.publico');

Route::post('salir', SalirController::class)->name('salir');

/*
|--------------------------------------------------------------------------
| Entrada con Google (RF18)
|--------------------------------------------------------------------------
|
| La única entrada fuera de local. Sin credenciales configuradas las tres
| rutas responden 404 (lo comprueba el controlador en cada petición, así que
| una caché de rutas no la deja abierta a medias).
|
| Sin límite de peticiones por IP: en la universidad cientos de personas
| salen por la misma dirección y entran a la vez al empezar la clase. El
| retorno no se puede forzar probando valores: Google valida el código y
| Socialite el "state" de la sesión.
|
*/

Route::get('acceso', [AccesoConGoogleController::class, 'pantalla'])->name('acceso');
Route::get('acceso/google', [AccesoConGoogleController::class, 'redirigir'])->name('acceso.google');
Route::get('acceso/google/volver', [AccesoConGoogleController::class, 'volver'])->name('acceso.google.volver');

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

Route::middleware(['auth', 'usuario.activo', 'rol.activo'])->prefix('panel')->name('panel.')->group(function (): void {
    // Secciones ya construidas. Se declaran antes del marcador de posición
    // para que este solo cubra las que aún no tienen pantalla.
    $construidas = ['mis-solicitudes', 'solicitudes', 'calendario', 'preparaciones', 'inventario', 'formatos-confidencialidad', 'mi-formato', 'plantillas-confidencialidad', 'reposicion', 'usuarios', 'administracion', 'reportes', 'periodo-academico', 'bloqueos', 'evaluaciones', 'mis-resultados', 'sesiones-apartadas'];

    Route::get('mis-solicitudes', [SolicitudController::class, 'mias'])->name('mis-solicitudes');
    Route::get('solicitudes/nueva', [SolicitudController::class, 'nueva'])->name('solicitudes.nueva');
    Route::get('solicitudes', [SolicitudController::class, 'bandeja'])->name('solicitudes');
    Route::get('solicitudes/{solicitud}/participantes', [SolicitudController::class, 'participantes'])->name('solicitudes.participantes');
    Route::get('solicitudes/{solicitud}/formato-intramural', [SolicitudController::class, 'formatoIntramural'])->name('solicitudes.formato-intramural');
    Route::get('solicitudes/{solicitud}/novedades', [SolicitudController::class, 'novedades'])->name('solicitudes.novedades');

    /* Sesiones apartadas antes del semestre (RF57-RF59). */
    Route::get('sesiones-apartadas', [SolicitudController::class, 'apartadas'])->name('sesiones-apartadas');

    /* Evaluación de habilidades (RF41-RF50). */
    Route::get('evaluaciones', [EvaluacionController::class, 'index'])->name('evaluaciones');
    Route::get('evaluaciones/{evaluacion}', [EvaluacionController::class, 'registro'])->name('evaluaciones.registro');
    Route::get('mis-resultados', [EvaluacionController::class, 'misResultados'])->name('mis-resultados');

    /* Bloqueos de acceso al laboratorio (RF68). Coordinación y ADMIN. */
    Route::get('bloqueos', BloqueoController::class)->name('bloqueos');

    Route::get('preparaciones', PreparacionController::class)->name('preparaciones');

    /* Reparto de roles, con y sin vigencia (RF63, RF64). Solo el ADMIN. */
    Route::get('usuarios', [UsuarioController::class, 'roles'])->name('usuarios');

    /*
     * Lista de insumos por pedir (RF67). Las descargas solo existen para
     * listas ya cerradas: el soporte de una solicitud de compra no puede
     * cambiar después de entregarlo.
     */
    Route::get('reposicion', [ReposicionController::class, 'index'])->name('reposicion');
    Route::get('reposicion/{lista}/excel', [ReposicionController::class, 'excel'])->name('reposicion.excel');
    Route::get('reposicion/{lista}/pdf', [ReposicionController::class, 'pdf'])->name('reposicion.pdf');

    /*
     * Reportes agregados (RF54-RF56). Las descargas llevan en la URL los
     * mismos filtros que la pantalla. La lista de reposición no entra por
     * aquí: tiene sus propias descargas, solo de listas cerradas.
     */
    $reportesAgregados = array_map(static fn (Reporte $reporte): string => $reporte->value, Reporte::agregados());

    Route::get('reportes', [ReporteController::class, 'index'])->name('reportes');
    Route::get('reportes/{reporte}/excel', [ReporteController::class, 'excel'])->whereIn('reporte', $reportesAgregados)->name('reportes.excel');
    Route::get('reportes/{reporte}/pdf', [ReporteController::class, 'pdf'])->whereIn('reporte', $reportesAgregados)->name('reportes.pdf');

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

    /* Abrir y cerrar el periodo académico (RF75). */
    Route::get('periodo-academico', PeriodoAcademicoController::class)->name('periodo-academico');

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
