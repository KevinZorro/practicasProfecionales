<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\RecursoDelAdmin;
use App\Filament\Resources\SalaResource\Pages;
use App\Models\Sala;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Catálogo de salas. Sin borrado: se desactivan (ver SalaPolicy).
 *
 * La capacidad de la sala es cuánta gente cabe en el espacio físico; no es
 * la capacidad máxima de estudiantes del escenario (RF74), que va en el
 * caso clínico.
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
                ->required()
                ->maxLength(255),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalas::route('/'),
            'create' => Pages\CreateSala::route('/create'),
            'edit' => Pages\EditSala::route('/{record}/edit'),
        ];
    }
}
