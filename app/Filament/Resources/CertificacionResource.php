<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Formularios\CampoDeImagen;
use App\Filament\RecursoDelAdmin;
use App\Filament\Resources\CertificacionResource\Pages;
use App\Models\Certificacion;
use App\Services\ImagenPublicaService;
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
 * Certificaciones y acreditaciones del laboratorio (RF06, RF15). La
 * insignia suele ser un PNG con fondo transparente: ImagenPublicaService
 * conserva esa transparencia al pasarla a WebP.
 */
final class CertificacionResource extends RecursoDelAdmin
{
    protected static ?string $model = Certificacion::class;

    protected static ?string $slug = 'certificaciones';

    protected static ?string $modelLabel = 'certificación';

    protected static ?string $pluralModelLabel = 'certificaciones';

    protected static ?string $navigationGroup = 'Contenido público';

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function camposDeImagen(): array
    {
        return ['imagen_insignia'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('nombre')
                ->required()
                ->maxLength(255),
            TextInput::make('entidad')
                ->label('Entidad que la otorga')
                ->required()
                ->maxLength(255),
            Textarea::make('descripcion')
                ->label('Descripción')
                ->rows(3),
            CampoDeImagen::make('imagen_insignia', 'certificaciones')
                ->label('Insignia'),
            Toggle::make('activo')
                ->label('Publicada')
                ->helperText('Una certificación sin publicar se conserva, pero no sale en la landing.')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('imagen_insignia')->label('Insignia')->disk(ImagenPublicaService::DISCO)->height(40),
                TextColumn::make('nombre')->searchable(),
                TextColumn::make('entidad')->searchable(),
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
                    ->after(static fn (Certificacion $record) => app(ImagenPublicaService::class)->borrarAlConfirmar($record->imagen_insignia)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCertificaciones::route('/'),
            'create' => Pages\CreateCertificacion::route('/create'),
            'edit' => Pages\EditCertificacion::route('/{record}/edit'),
        ];
    }
}
