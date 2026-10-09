<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\RecursoDelAdmin;
use App\Filament\Resources\SalaResource\Pages;
use App\Models\Sala;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Unique;

/**
 * Catálogo de salas. Sin borrado: se desactivan (ver SalaPolicy).
 *
 * La capacidad de la sala es cuánta gente cabe en el espacio físico; no es
 * la capacidad máxima de estudiantes del escenario (RF74), que va en el
 * caso clínico.
 *
 * Cada sala se ubica por bloque, piso y número (RF65). El rastro de las
 * reubicaciones lo deja SalaService desde las páginas de alta y edición.
 */
final class SalaResource extends RecursoDelAdmin
{
    /**
     * Tope de la columna (integer de PostgreSQL), no una regla del
     * laboratorio: pasarlo daría un error de base de datos.
     */
    private const TOPE_DE_LA_COLUMNA = 2_147_483_647;

    protected static ?string $model = Sala::class;

    protected static ?string $modelLabel = 'sala';

    protected static ?string $pluralModelLabel = 'salas';

    protected static ?string $navigationGroup = 'Estructura académica';

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('codigo')
                ->label('Código')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            TextInput::make('nombre')
                ->helperText('Se puede cambiar cuando quiera; la ubicación va aparte.')
                ->required()
                ->maxLength(255),
            Fieldset::make('Ubicación')
                ->columns(3)
                ->schema([
                    TextInput::make('bloque')
                        ->required()
                        ->maxLength(20),
                    TextInput::make('piso')
                        ->required()
                        ->maxLength(10),
                    TextInput::make('numero')
                        ->label('Número de sala')
                        ->helperText('Dentro de ese bloque y piso.')
                        ->required()
                        ->maxLength(10)
                        // Dos salas no ocupan el mismo sitio; la base lo
                        // garantiza también, esto es para avisar a tiempo.
                        ->unique(
                            ignoreRecord: true,
                            // Filament inyecta por nombre: $rule y $get.
                            modifyRuleUsing: static fn (Unique $rule, Get $get): Unique => $rule
                                ->where('bloque', $get('bloque'))
                                ->where('piso', $get('piso')),
                        ),
                ]),
            Select::make('casosClinicos')
                ->label('Escenarios que se montan aquí')
                ->helperText('Al preparar, estas salas se ofrecen primero para esos escenarios. No impide usar otra sala libre.')
                ->relationship('casosClinicos', 'nombre')
                ->multiple()
                ->preload()
                ->searchable(),
            Placeholder::make('historial_de_ubicacion')
                ->label('Ubicaciones anteriores')
                ->visibleOn('edit')
                ->content(static fn (?Sala $record): HtmlString => self::historialDeUbicacion($record)),
            TextInput::make('capacidad')
                ->label('Capacidad (personas)')
                ->helperText('Cuánta gente cabe en el espacio físico.')
                ->required()
                ->integer()
                ->minValue(1)
                ->maxValue(self::TOPE_DE_LA_COLUMNA),
            Toggle::make('activo')
                ->label('Activa')
                ->helperText('Una sala inactiva no se ofrece al asignar sala en la preparación.')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')->label('Código')->searchable()->sortable(),
                TextColumn::make('nombre')->searchable()->sortable(),
                TextColumn::make('bloque')->searchable()->sortable(),
                TextColumn::make('piso')->sortable(),
                TextColumn::make('numero')->label('Número')->sortable(),
                TextColumn::make('capacidad')->label('Capacidad (personas)')->sortable(),
                IconColumn::make('activo')->label('Activa')->boolean(),
            ])
            ->defaultSort('nombre')
            ->filters([
                TernaryFilter::make('activo')->label('Activa'),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    private static function historialDeUbicacion(?Sala $sala): HtmlString
    {
        $ubicaciones = $sala?->ubicaciones()->with('registradaPor:id,nombre')->get() ?? collect();

        if ($ubicaciones->isEmpty()) {
            return new HtmlString('Sin registro todavía.');
        }

        $lineas = $ubicaciones->map(static fn ($ubicacion): string => sprintf(
            '<li>%s — desde el %s, registrada por %s</li>',
            e($ubicacion->descripcion()),
            $ubicacion->created_at?->format('d/m/Y'),
            e($ubicacion->registradaPor->nombre),
        ));

        return new HtmlString('<ul>'.$lineas->implode('').'</ul>');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalas::route('/'),
            'create' => Pages\CreateSala::route('/create'),
            'edit' => Pages\EditSala::route('/{record}/edit'),
        ];
    }
}
