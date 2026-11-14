<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Formularios\CampoDeImagen;
use App\Filament\Formularios\CampoDeVideo;
use App\Filament\RecursoDelAdmin;
use App\Filament\Resources\VideoInstitucionalResource\Pages;
use App\Models\VideoInstitucional;
use App\Services\ImagenPublicaService;
use App\Services\VideoPublicoService;
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
 * Galería de videos institucionales de la portada (RF08, RF17). Cada video
 * es un archivo subido al servidor con su portada: la portada es lo que se
 * ve, y el video no se descarga hasta que el visitante lo reproduce. El
 * orden de la portada es el de esta tabla: se cambia arrastrando las filas.
 */
final class VideoInstitucionalResource extends RecursoDelAdmin
{
    protected static ?string $model = VideoInstitucional::class;

    protected static ?string $slug = 'videos-institucionales';

    protected static ?string $modelLabel = 'video institucional';

    protected static ?string $pluralModelLabel = 'videos institucionales';

    protected static ?string $navigationGroup = 'Contenido público';

    protected static ?string $navigationIcon = 'heroicon-o-film';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'titulo';

    public static function camposDeImagen(): array
    {
        return ['portada'];
    }

    public static function camposDeVideo(): array
    {
        return ['video'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('titulo')
                ->label('Título')
                ->helperText('Sale debajo del video y es su nombre para quien no puede verlo.')
                ->required()
                ->maxLength(255),
            CampoDeVideo::make('video', 'videos')
                ->helperText(sprintf(
                    'MP4 o WebM ya comprimido, de hasta %d MB (unos 90 segundos a 720p). No se recodifica.',
                    intdiv(VideoPublicoService::TAMANO_MAXIMO_KB, 1024),
                ))
                ->required(),
            CampoDeImagen::make('portada', 'videos')
                ->label('Portada')
                ->helperText('La imagen que se ve antes de reproducirlo. Con ella, la portada no descarga nada del video hasta que alguien lo pone.')
                ->required(),
            Toggle::make('activo')
                ->label('Publicado')
                ->helperText('Un video sin publicar se conserva, pero no sale en la landing.')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('portada')->label('Portada')->disk(ImagenPublicaService::DISCO)->height(48),
                TextColumn::make('titulo')->label('Título')->searchable(),
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
                    ->after(static fn (VideoInstitucional $record) => self::borrarArchivosDe($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVideosInstitucionales::route('/'),
            'create' => Pages\CreateVideoInstitucional::route('/create'),
            'edit' => Pages\EditVideoInstitucional::route('/{record}/edit'),
        ];
    }
}
