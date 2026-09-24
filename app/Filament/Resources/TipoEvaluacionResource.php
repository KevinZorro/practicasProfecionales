<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\RecursoDelAdmin;
use App\Filament\Resources\TipoEvaluacionResource\Pages;
use App\Models\Materia;
use App\Models\TipoEvaluacion;
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
 * Tipos de evaluación (RF26): la plantilla del checklist y las materias en
 * que se puede usar (RF43).
 *
 * Editar la plantilla aquí no toca ninguna evaluación ya creada: al crearla,
 * EvaluacionService copia los ítems (regla 3). Por eso se pueden quitar o
 * reescribir ítems sin miedo al histórico.
 *
 * Sin borrado: se desactivan (ver TipoEvaluacionPolicy).
 */
final class TipoEvaluacionResource extends RecursoDelAdmin
{
    protected static ?string $model = TipoEvaluacion::class;

    // Filament pluraliza en inglés ("tipo-evaluacions").
    protected static ?string $slug = 'tipos-de-evaluacion';

    protected static ?string $modelLabel = 'tipo de evaluación';

    protected static ?string $pluralModelLabel = 'tipos de evaluación';

    protected static ?string $navigationGroup = 'Estructura académica';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Tipo de evaluación')->schema([
                TextInput::make('nombre')
                    ->required()
                    ->maxLength(255),
                Textarea::make('descripcion')
                    ->label('Descripción')
                    ->rows(3),
                Toggle::make('activo')
                    ->label('Activo')
                    ->default(true),
            ]),

            Section::make('Materias')
                ->description('Solo se puede evaluar con este tipo en las materias marcadas aquí (RF43).')
                ->schema([
                    // Las inactivas se ofrecen marcadas, igual que en casos
                    // clínicos: una ya asociada tiene que seguir viéndose.
                    Select::make('materias')
                        ->hiddenLabel()
                        ->relationship('materias', 'nombre')
                        ->getOptionLabelFromRecordUsing(static fn (Materia $materia): string => $materia->activo ? $materia->nombre : "{$materia->nombre} (inactiva)")
                        ->multiple()
                        ->searchable()
                        ->preload(),
                ]),

            Section::make('Checklist')
                ->description('Lo que el docente marca al evaluar, en este orden. Cambiarlo no altera las evaluaciones ya creadas.')
                ->schema([
                    Repeater::make('itemsChecklist')
                        ->hiddenLabel()
                        ->relationship()
                        ->orderColumn('orden')
                        ->schema([
                            Textarea::make('descripcion')
                                ->label('Ítem')
                                ->required()
                                ->rows(2),
                        ])
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
                TextColumn::make('materias_count')->label('Materias')->counts('materias'),
                TextColumn::make('items_checklist_count')->label('Ítems del checklist')->counts('itemsChecklist'),
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
            'index' => Pages\ListTiposEvaluacion::route('/'),
            'create' => Pages\CreateTipoEvaluacion::route('/create'),
            'edit' => Pages\EditTipoEvaluacion::route('/{record}/edit'),
        ];
    }
}
