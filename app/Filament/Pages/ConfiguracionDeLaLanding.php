<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\ClaveConfiguracionLanding as Clave;
use App\Models\ConfiguracionLanding;
use App\Services\ConfiguracionLandingService;
use App\Services\ImagenPublicaService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * Configuración de la landing (RF02, RF11): textos del hero, su video y los
 * datos de contacto. Es una sola página y no un recurso porque las claves
 * son fijas (ClaveConfiguracionLanding): no hay una lista de registros que
 * crear o borrar.
 *
 * @property Form $form
 */
final class ConfiguracionDeLaLanding extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'configuracion-de-la-landing';

    protected static ?string $title = 'Configuración de la landing';

    protected static ?string $navigationGroup = 'Contenido público';

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.pages.configuracion-de-la-landing';

    /** @var array<string, mixed> */
    public array $datos = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->can('update', ConfiguracionLanding::class) ?? false;
    }

    public function mount(ConfiguracionLandingService $configuracion): void
    {
        $this->form->fill($configuracion->valores());
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('datos')
            ->schema([
                Section::make('Portada')->schema([
                    TextInput::make(Clave::HeroTitulo->value)
                        ->label('Título')
                        ->required()
                        ->maxLength(255),
                    TextInput::make(Clave::HeroSubtitulo->value)
                        ->label('Subtítulo')
                        ->maxLength(255),
                    FileUpload::make(Clave::HeroVideo->value)
                        ->label('Video de fondo')
                        ->disk(ImagenPublicaService::DISCO)
                        ->directory('landing')
                        ->acceptedFileTypes(ConfiguracionLandingService::TIPOS_DE_VIDEO)
                        ->maxSize(ConfiguracionLandingService::TAMANO_MAXIMO_VIDEO_KB)
                        ->helperText(sprintf(
                            'MP4 o WebM ya comprimido, de hasta %d MB. Sin sonido y de pocos segundos: se reproduce en bucle detrás del título.',
                            intdiv(ConfiguracionLandingService::TAMANO_MAXIMO_VIDEO_KB, 1024),
                        )),
                ]),
                Section::make('Contacto')->columns(2)->schema([
                    TextInput::make(Clave::ContactoEmail->value)
                        ->label('Correo')
                        ->email()
                        ->maxLength(255),
                    TextInput::make(Clave::ContactoTelefono->value)
                        ->label('Teléfono')
                        ->tel()
                        ->maxLength(50),
                    TextInput::make(Clave::ContactoDireccion->value)
                        ->label('Dirección')
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),
            ]);
    }

    /** @return list<Action> */
    protected function getFormActions(): array
    {
        return [
            Action::make('guardar')->label('Guardar')->submit('guardar'),
        ];
    }

    public function guardar(ConfiguracionLandingService $configuracion): void
    {
        $this->authorize('update', ConfiguracionLanding::class);

        $configuracion->guardar($this->form->getState());

        Notification::make()->title('Configuración guardada')->success()->send();
    }
}
