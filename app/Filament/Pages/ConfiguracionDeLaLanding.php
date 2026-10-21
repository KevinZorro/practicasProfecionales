<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\ClaveConfiguracionLanding as Clave;
use App\Enums\ModoCifraDeEstudiantes;
use App\Models\ConfiguracionLanding;
use App\Services\ConfiguracionLandingService;
use App\Services\EstudiantesAtendidosService;
use App\Services\ImagenPublicaService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
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
        $valores = $configuracion->valores();
        $valores[Clave::EstudiantesModo->value] ??= ModoCifraDeEstudiantes::Automatico->value;

        $this->form->fill($valores);
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
                Section::make('Estudiantes en el laboratorio')
                    ->description('La cifra de estudiantes que han practicado, en «En cifras» de la portada.')
                    ->schema([
                        Select::make(Clave::EstudiantesModo->value)
                            ->label('Cómo se obtiene')
                            ->options(collect(ModoCifraDeEstudiantes::cases())
                                ->mapWithKeys(fn (ModoCifraDeEstudiantes $modo): array => [$modo->value => $modo->etiqueta()]))
                            ->required()
                            ->native(false)
                            ->live()
                            ->helperText(sprintf(
                                'Automático cuenta, en cada periodo académico, los estudiantes distintos de las sesiones aprobadas ya realizadas (sin retirados) y suma los periodos. Hoy da %s.',
                                number_format(app(EstudiantesAtendidosService::class)->calcular(), 0, ',', '.'),
                            )),
                        TextInput::make(Clave::EstudiantesManual->value)
                            ->label('Total de estudiantes')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(1_000_000)
                            ->visible(fn (Get $get): bool => $get(Clave::EstudiantesModo->value) === ModoCifraDeEstudiantes::Manual->value)
                            ->required(fn (Get $get): bool => $get(Clave::EstudiantesModo->value) === ModoCifraDeEstudiantes::Manual->value)
                            ->helperText('Se publica tal cual. Con 0 la cifra no aparece en la portada.'),
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
