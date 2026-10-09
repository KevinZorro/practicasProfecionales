<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\AjusteDelLaboratorio as Clave;
use App\Models\AjusteLaboratorio;
use App\Services\AjustesService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * Ajustes del laboratorio: valores que el ADMIN cambia sin desplegar. Hoy,
 * la antelación del aviso de sesiones sin formato intramural (RF60).
 *
 * @property Form $form
 */
final class AjustesDelLaboratorio extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'ajustes-del-laboratorio';

    protected static ?string $title = 'Ajustes del laboratorio';

    protected static ?string $navigationGroup = 'Estructura académica';

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.ajustes-del-laboratorio';

    /** @var array<string, mixed> */
    public array $datos = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->can('update', AjusteLaboratorio::class) ?? false;
    }

    public function mount(AjustesService $ajustes): void
    {
        $this->form->fill([
            Clave::DiasDeAvisoIntramural->value => $ajustes->diasDeAvisoIntramural(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('datos')
            ->schema([
                TextInput::make(Clave::DiasDeAvisoIntramural->value)
                    ->label('Días de aviso del formato intramural')
                    ->helperText('Con cuántos días de antelación se avisa por correo a los administrativos y al docente de una sesión que todavía no tiene formato intramural.')
                    ->required()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(AjustesService::DIAS_DE_AVISO_MAXIMOS),
            ]);
    }

    /** @return list<Action> */
    protected function getFormActions(): array
    {
        return [
            Action::make('guardar')->label('Guardar')->submit('guardar'),
        ];
    }

    public function guardar(AjustesService $ajustes): void
    {
        $this->authorize('update', AjusteLaboratorio::class);

        $ajustes->guardar($this->form->getState(), Auth::user());

        Notification::make()->title('Ajustes guardados')->success()->send();
    }
}
