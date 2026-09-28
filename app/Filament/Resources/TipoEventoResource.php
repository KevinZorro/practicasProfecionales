<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\RecursoDelAdmin;
use App\Filament\Resources\TipoEventoResource\Pages;
use App\Models\TipoEvento;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Catálogo de tipos de evento (RF05, RF14). Sin borrado: se desactivan
 * (ver TipoEventoPolicy).
 */
final class TipoEventoResource extends RecursoDelAdmin
{
    protected static ?string $model = TipoEvento::class;

    protected static ?string $slug = 'tipos-de-evento';

    protected static ?string $modelLabel = 'tipo de evento';

    protected static ?string $pluralModelLabel = 'tipos de evento';

    protected static ?string $navigationGroup = 'Contenido público';

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('nombre')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Toggle::make('activo')
                ->label('Activo')
                ->helperText('Un tipo inactivo no se ofrece en los eventos nuevos; los que ya lo tienen lo conservan.')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')->searchable()->sortable(),
                TextColumn::make('eventos_count')->label('Eventos')->counts('eventos'),
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
            'index' => Pages\ListTiposEvento::route('/'),
            'create' => Pages\CreateTipoEvento::route('/create'),
            'edit' => Pages\EditTipoEvento::route('/{record}/edit'),
        ];
    }
}
