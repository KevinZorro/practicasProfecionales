<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Formularios\CampoDeImagen;
use App\Filament\RecursoDelAdmin;
use App\Filament\Resources\GaleriaFotoResource\Pages;
use App\Models\GaleriaFoto;
use App\Services\ImagenPublicaService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Galería de fotos de la landing (RF10). El orden de la landing es el de
 * esta tabla: se cambia arrastrando las filas.
 */
final class GaleriaFotoResource extends RecursoDelAdmin
{
    protected static ?string $model = GaleriaFoto::class;

    protected static ?string $slug = 'galeria';

    protected static ?string $modelLabel = 'foto';

    protected static ?string $pluralModelLabel = 'galería de fotos';

    protected static ?string $navigationGroup = 'Contenido público';

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'titulo';

    public static function camposDeImagen(): array
    {
        return ['imagen_path'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('titulo')
                ->label('Título')
                ->helperText('Describe la foto: es también el texto alternativo para quien no puede verla.')
                ->required()
                ->maxLength(255),
            CampoDeImagen::make('imagen_path', 'galeria')
                ->label('Foto')
                ->required(),
            Toggle::make('activo')
                ->label('Publicada')
                ->helperText('Una foto sin publicar se conserva, pero no sale en la landing.')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('imagen_path')->label('Foto')->disk(ImagenPublicaService::DISCO)->height(48),
                TextColumn::make('titulo')->label('Título')->searchable(),
                IconColumn::make('activo')->label('Publicada')->boolean(),
            ])
            ->reorderable('orden')
            ->defaultSort('orden')
            ->filters([
                TernaryFilter::make('activo')->label('Publicada'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->after(static fn (GaleriaFoto $record) => app(ImagenPublicaService::class)->borrarAlConfirmar($record->imagen_path)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGaleriaFotos::route('/'),
            'create' => Pages\CreateGaleriaFoto::route('/create'),
            'edit' => Pages\EditGaleriaFoto::route('/{record}/edit'),
        ];
    }
}
