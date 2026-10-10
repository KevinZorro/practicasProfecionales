<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Formularios\CampoDeImagen;
use App\Filament\RecursoDelAdmin;
use App\Filament\Resources\EquipoDestacadoResource\Pages;
use App\Models\EquipoDestacado;
use App\Services\ImagenPublicaService;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
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
 * Equipamiento que la portada presenta como protagonista (RF01, RF10): los
 * simuladores, la sala inmersiva, la mesa de anatomía virtual. El primero
 * del orden es el principal y sale más grande.
 */
final class EquipoDestacadoResource extends RecursoDelAdmin
{
    /** Las frases cortas que acompañan a cada equipo en la portada. */
    public const MAXIMO_DE_CARACTERISTICAS = 4;

    protected static ?string $model = EquipoDestacado::class;

    protected static ?string $slug = 'equipamiento-destacado';

    protected static ?string $modelLabel = 'equipo destacado';

    protected static ?string $pluralModelLabel = 'equipamiento destacado';

    protected static ?string $navigationGroup = 'Contenido público';

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function camposDeImagen(): array
    {
        return ['imagen'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('nombre')
                ->required()
                ->maxLength(120)
                ->helperText('Corto, como se nombra en el laboratorio: «Sala inmersiva», «Mesa de anatomía virtual».'),
            Textarea::make('resumen')
                ->label('Frase')
                ->required()
                ->maxLength(200)
                ->rows(2)
                ->helperText('Una sola frase que diga qué hace posible. Sale bajo el nombre, en grande.'),
            CampoDeImagen::make('imagen', 'equipamiento')
                ->label('Foto')
                ->helperText('Mejor una foto del equipo sobre un fondo claro y liso. Se recorta para llenar el recuadro.'),
            Repeater::make('caracteristicas')
                ->label('Lo que permite')
                ->simple(
                    TextInput::make('caracteristica')
                        ->validationAttribute('característica')
                        ->required()
                        ->maxLength(60),
                )
                ->defaultItems(0)
                ->maxItems(self::MAXIMO_DE_CARACTERISTICAS)
                ->addActionLabel('Añadir característica')
                ->helperText(sprintf(
                    'Hasta %d frases cortas, por ejemplo «Signos vitales en tiempo real».',
                    self::MAXIMO_DE_CARACTERISTICAS,
                )),
            Toggle::make('activo')
                ->label('Publicado')
                ->helperText('Un equipo sin publicar se conserva, pero no sale en la portada.')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('imagen')->label('Foto')->disk(ImagenPublicaService::DISCO)->height(40),
                TextColumn::make('nombre')->searchable(),
                TextColumn::make('resumen')->label('Frase')->limit(60),
                IconColumn::make('activo')->label('Publicado')->boolean(),
            ])
            ->reorderable('orden')
            ->defaultSort('orden')
            ->filters([
                TernaryFilter::make('activo')->label('Publicado'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->after(static fn (EquipoDestacado $record) => app(ImagenPublicaService::class)->borrarAlConfirmar($record->imagen)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEquiposDestacados::route('/'),
            'create' => Pages\CreateEquipoDestacado::route('/create'),
            'edit' => Pages\EditEquipoDestacado::route('/{record}/edit'),
        ];
    }
}
