<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\RecursoDelAdmin;
use App\Filament\Resources\MateriaResource\Pages;
use App\Models\Materia;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Materias (RF23). Sin borrado: se desactivan (ver MateriaPolicy).
 */
final class MateriaResource extends RecursoDelAdmin
{
    protected static ?string $model = Materia::class;

    protected static ?string $modelLabel = 'materia';

    protected static ?string $pluralModelLabel = 'materias';

    protected static ?string $navigationGroup = 'Estructura académica';

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 1;

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
                ->required()
                ->maxLength(255),
            TextInput::make('semestre')
                ->required()
                ->integer()
                ->minValue(1)
                // Tope de la columna (unsignedTinyInteger), no una regla del
                // programa: pasarlo daría un error de base de datos.
                ->maxValue(255),
            Toggle::make('activo')
                ->label('Activa')
                ->helperText('Una materia inactiva no aparece en el formulario de solicitud del docente.')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')->label('Código')->searchable()->sortable(),
                TextColumn::make('nombre')->searchable()->sortable(),
                TextColumn::make('semestre')->sortable(),
                IconColumn::make('activo')->label('Activa')->boolean(),
            ])
            ->defaultSort('semestre')
            ->filters([
                TernaryFilter::make('activo')->label('Activa'),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterias::route('/'),
            'create' => Pages\CreateMateria::route('/create'),
            'edit' => Pages\EditMateria::route('/{record}/edit'),
        ];
    }
}
