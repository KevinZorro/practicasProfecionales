<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Http\Middleware\EstablecerRolActivo;
use App\Http\Middleware\VerificarUsuarioActivo;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Pantallas del ADMIN: estructura académica (RF22-RF26) y contenido público
 * (RF10-RF17). Los flujos operativos siguen en el panel de Livewire.
 *
 * Tres decisiones que no son las de la plantilla de Filament:
 *
 * - Sin ->login(). La entrada es únicamente por Google (RF18); quien llega
 *   sin sesión va a la misma entrada que el resto del panel.
 * - Los middleware del proyecto van en la pila de autenticación, antes que
 *   el Authenticate de Filament: VerificarUsuarioActivo (regla 8) y
 *   EstablecerRolActivo (RF21), que tiene que aplicar el rol activo antes de
 *   que Filament pregunte canAccessPanel(). El orden lo fija la lista de
 *   prioridad (bootstrap/app.php), no la posición en este arreglo. Los tres
 *   son persistentes —los nuestros desde AppServiceProvider, el de Filament
 *   desde su propio proveedor—, así que también protegen las acciones de
 *   Livewire de estas pantallas.
 * - La salida del menú apunta a la ruta "salir" del proyecto: una sola forma
 *   de cerrar sesión.
 */
final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Administración del laboratorio')
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->userMenuItems([
                'logout' => MenuItem::make()
                    ->label('Salir')
                    ->url(static fn (): string => route('salir')),
            ])
            // Guardar un caso clínico toca varias tablas (el caso, sus
            // materias, su inventario): todo o nada.
            ->databaseTransactions()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                VerificarUsuarioActivo::class,
                EstablecerRolActivo::class,
                Authenticate::class,
            ]);
    }
}
