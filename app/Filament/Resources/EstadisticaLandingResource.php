<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\RecursoDelAdmin;
use App\Filament\Resources\EstadisticaLandingResource\Pages;
use App\Models\EstadisticaLanding;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Cifras destacadas de la landing (RF01, RF10): "5 salas de simulación",
 * "22 simuladores". El valor lo escribe el ADMIN a mano y se muestra tal
 * cual: no se calcula de las tablas, porque lo que se publica es una cifra
 * redonda y revisada, no el conteo del día.
 */
final class EstadisticaLandingResource extends RecursoDelAdmin
{
    protected static ?string $model = EstadisticaLanding::class;

    protected static ?string $slug = 'estadisticas';

    protected static ?string $modelLabel = 'estadística';

    protected static ?string $pluralModelLabel = 'estadísticas';

    protected static ?string $navigationGroup = 'Contenido público';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'etiqueta';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('valor')
                ->required()
                ->maxLength(30)
                ->helperText('Se publica tal cual lo escribas: 22, +700, 98 %.'),
            TextInput::make('etiqueta')
                ->required()
                ->maxLength(255)
                ->helperText('Lo que acompaña a la cifra: "simuladores disponibles".'),
            Toggle::make('activo')
                ->label('Publicada')
                ->helperText('Una estadística sin publicar se conserva, pero no sale en la landing.')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('valor'),
                TextColumn::make('etiqueta')->searchable(),
                IconColumn::make('activo')->label('Publicada')->boolean(),
            ])
            ->reorderable('orden')
            ->defaultSort('orden')
            ->filters([
                TernaryFilter::make('activo')->label('Publicada'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEstadisticasLanding::route('/'),
            'create' => Pages\CreateEstadisticaLanding::route('/create'),
            'edit' => Pages\EditEstadisticaLanding::route('/{record}/edit'),
        ];
    }
}
