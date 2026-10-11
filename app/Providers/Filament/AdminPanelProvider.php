<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\AvatarConIniciales;
use App\Http\Middleware\EstablecerRolActivo;
use App\Http\Middleware\VerificarUsuarioActivo;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\View\View;

/**
 * Pantallas del ADMIN: estructura académica (RF23-RF26) y contenido público
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
            // Sin ui-avatars.com: ver AvatarConIniciales.
            ->defaultAvatarProvider(AvatarConIniciales::class)
            // La marca del panel (DESIGN.md): Onest servida desde el propio
            // servidor, sin fonts.bunny.net; los botones en tinta, el rojo
            // para lo que no se deshace y los grises fríos. Sin modo oscuro:
            // el resto de la plataforma no lo tiene.
            ->font('Onest', url: asset('fonts/onest/onest.css'), provider: LocalFontProvider::class)
            ->brandLogo(static fn (): View => view('filament.marca'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('favicon.ico'))
            ->darkMode(false)
            ->colors([
                'primary' => [
                    50 => '245, 245, 247',
                    100 => '237, 237, 240',
                    200 => '226, 226, 231',
                    300 => '210, 210, 215',
                    400 => '161, 161, 166',
                    500 => '63, 63, 67',
                    600 => '29, 29, 31',
                    700 => '29, 29, 31',
                    800 => '17, 17, 18',
                    900 => '17, 17, 18',
                    950 => '10, 10, 11',
                ],
                'danger' => [
                    50 => '254, 242, 243',
                    100 => '253, 227, 229',
                    200 => '251, 198, 203',
                    300 => '245, 151, 160',
                    400 => '236, 93, 107',
                    500 => '224, 43, 60',
                    600 => '211, 15, 35',
                    700 => '173, 12, 28',
                    800 => '142, 13, 27',
                    900 => '117, 15, 27',
                    950 => '64, 5, 12',
                ],
                'gray' => [
                    50 => '245, 245, 247',
                    100 => '237, 237, 240',
                    200 => '226, 226, 231',
                    300 => '210, 210, 215',
                    400 => '161, 161, 166',
                    500 => '110, 110, 115',
                    600 => '92, 92, 97',
                    700 => '63, 63, 67',
                    800 => '42, 42, 45',
                    900 => '29, 29, 31',
                    950 => '17, 17, 18',
                ],
                'success' => Color::Emerald,
                'warning' => Color::Amber,
            ])
            ->renderHook(PanelsRenderHook::STYLES_AFTER, static fn (): View => view('filament.estilos'))
            // Volver a las pantallas operativas sin escribir la dirección.
            ->navigationItems([
                NavigationItem::make('Volver al panel')
                    ->url(static fn (): string => route('panel.inicio'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->sort(-1),
            ])
            ->userMenuItems([
                'panel' => MenuItem::make()
                    ->label('Volver al panel')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->url(static fn (): string => route('panel.inicio')),
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
