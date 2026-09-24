<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\RecursoDelAdmin;
use App\Filament\Resources\CasoClinicoResource\Pages;
use App\Models\CasoClinico;
use App\Models\ItemInventario;
use App\Models\Materia;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Casos clínicos (RF24, RF25, RF74): la ficha del escenario, las materias
 * en que se usa, el inventario que necesita y cuántos estudiantes admite.
 *
 * Sin borrado: se desactivan (ver CasoClinicoPolicy). Los campos de la
 * landing —imagen, visibilidad pública, orden (RF12)— llegan con el
 * contenido público.
 */
final class CasoClinicoResource extends RecursoDelAdmin
{
    /**
     * El mismo tope que tenía la pantalla de capacidad a la que sustituye:
     * un número grande para frenar un error de tecleo, no una regla del
     * laboratorio. La regla es el número que registra el ADMIN (regla 10).
     */
    private const TOPE_DE_CAPACIDAD = 200;

    protected static ?string $model = CasoClinico::class;

    protected static ?string $modelLabel = 'caso clínico';

    protected static ?string $pluralModelLabel = 'casos clínicos';

    protected static ?string $navigationGroup = 'Estructura académica';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Escenario')->schema([
                TextInput::make('nombre')
                    ->required()
                    ->maxLength(255),
                Textarea::make('descripcion')
                    ->label('Descripción')
                    ->required()
                    ->rows(4),
                TextInput::make('capacidad_maxima_estudiantes')
                    ->label('Capacidad máxima de estudiantes')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(self::TOPE_DE_CAPACIDAD)
                    ->placeholder('Sin definir')
                    // Regla 10: vacío es "sin definir" y no limita. Bloquear
                    // una clase por un campo que nadie llenó es peor.
                    ->helperText('Déjalo vacío si todavía no está definida: entonces no limita las solicitudes.'),
                Toggle::make('activo')
                    ->label('Activo')
                    ->helperText('Un caso inactivo no aparece en el formulario de solicitud del docente.')
                    ->default(true),
            ]),

            Section::make('Materias')
                ->description('Materias en las que se practica este escenario (RF24).')
                ->schema([
                    // Se ofrecen también las inactivas, marcadas: si se
                    // filtraran, una materia ya asociada que después se
                    // desactivó seguiría en el caso sin que el ADMIN la viera.
                    Select::make('materias')
                        ->hiddenLabel()
                        ->relationship('materias', 'nombre')
                        ->getOptionLabelFromRecordUsing(static fn (Materia $materia): string => self::etiquetaDeMateria($materia))
                        ->multiple()
                        ->searchable()
                        ->preload(),
                ]),

            Section::make('Inventario que necesita')
                ->description('Se precarga en la solicitud del docente, que puede ajustarlo antes de enviarla (RF25, RF29).')
                ->schema([
                    Repeater::make('itemsNecesarios')
                        ->hiddenLabel()
                        ->relationship()
                        ->schema([
                            Select::make('item_inventario_id')
                                ->label('Ítem')
                                ->relationship('itemInventario', 'nombre')
                                ->getOptionLabelFromRecordUsing(static fn (ItemInventario $item): string => self::etiquetaDeItem($item))
                                ->searchable()
                                ->preload()
                                ->required()
                                // Un ítem una sola vez por caso: la tabla
                                // tiene un índice único sobre el par.
                                // distinct() lo valida con un mensaje
                                // claro; deshabilitar la opción evita
                                // elegirlo dos veces en la pantalla.
                                ->distinct()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                            TextInput::make('cantidad')
                                ->required()
                                ->integer()
                                ->minValue(1)
                                ->default(1),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Añadir ítem'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')->searchable()->sortable(),
                TextColumn::make('capacidad_maxima_estudiantes')
                    ->label('Capacidad')
                    ->placeholder('Sin definir')
                    ->sortable(),
                TextColumn::make('materias_count')->label('Materias')->counts('materias'),
                TextColumn::make('items_necesarios_count')->label('Ítems')->counts('itemsNecesarios'),
                IconColumn::make('activo')->boolean(),
            ])
            ->defaultSort('nombre')
            ->filters([
                TernaryFilter::make('activo'),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCasosClinicos::route('/'),
            'create' => Pages\CreateCasoClinico::route('/create'),
            'edit' => Pages\EditCasoClinico::route('/{record}/edit'),
        ];
    }

    private static function etiquetaDeMateria(Materia $materia): string
    {
        return $materia->activo ? $materia->nombre : "{$materia->nombre} (inactiva)";
    }

    private static function etiquetaDeItem(ItemInventario $item): string
    {
        return $item->activo ? $item->nombre : "{$item->nombre} (inactivo)";
    }
}
