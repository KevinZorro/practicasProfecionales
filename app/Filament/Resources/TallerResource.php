<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ModalidadTaller;
use App\Filament\Formularios\CampoDeImagen;
use App\Filament\RecursoDelAdmin;
use App\Filament\Resources\TallerResource\Pages;
use App\Models\Taller;
use App\Services\ImagenPublicaService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Talleres de la landing (RF04, RF13). Se borran solo mientras no tengan
 * solicitudes de información (ver TallerPolicy).
 */
final class TallerResource extends RecursoDelAdmin
{
    protected static ?string $model = Taller::class;

    protected static ?string $slug = 'talleres';

    protected static ?string $modelLabel = 'taller';

    protected static ?string $pluralModelLabel = 'talleres';

    protected static ?string $navigationGroup = 'Contenido público';

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'titulo';

    public static function camposDeImagen(): array
    {
        return ['imagen'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Taller')->columns(2)->schema([
                TextInput::make('titulo')
                    ->label('Título')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('descripcion')
                    ->label('Descripción')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
                TextInput::make('tema')
                    ->required()
                    ->maxLength(255),
                DatePicker::make('fecha')
                    ->required(),
                Select::make('modalidad')
                    ->options(collect(ModalidadTaller::cases())->mapWithKeys(
                        static fn (ModalidadTaller $modalidad): array => [$modalidad->value => $modalidad->etiqueta()],
                    ))
                    // Las opciones solo filtran lo que se ve: esto rechaza en
                    // el servidor un valor que no sea del enum (RF04).
                    ->enum(ModalidadTaller::class)
                    ->required(),
                CampoDeImagen::make('imagen', 'talleres')
                    ->label('Imagen')
                    ->columnSpanFull(),
            ]),
            Section::make('Publicación')->columns(2)->schema([
                Toggle::make('muestra_formulario')
                    ->label('Mostrar el formulario de interés')
                    ->helperText('Quien vea el taller en la landing podrá dejar sus datos (RF09).')
                    ->default(true),
                Toggle::make('activo')
                    ->label('Publicado')
                    ->helperText('Un taller sin publicar se conserva, pero no sale en la landing.')
                    ->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('imagen')->disk(ImagenPublicaService::DISCO)->height(40),
                TextColumn::make('titulo')->label('Título')->searchable(),
                TextColumn::make('fecha')->date('d/m/Y')->sortable(),
                TextColumn::make('modalidad')
                    ->formatStateUsing(static fn (ModalidadTaller $state): string => $state->etiqueta())
                    ->badge(),
                TextColumn::make('solicitudes_informacion_count')
                    ->label('Interesados')
                    ->counts('solicitudesInformacion'),
                IconColumn::make('activo')->label('Publicado')->boolean(),
            ])
            ->reorderable('orden')
            ->defaultSort('orden')
            ->filters([
                TernaryFilter::make('activo')->label('Publicado'),
                SelectFilter::make('modalidad')->options(collect(ModalidadTaller::cases())->mapWithKeys(
                    static fn (ModalidadTaller $modalidad): array => [$modalidad->value => $modalidad->etiqueta()],
                )),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->after(static fn (Taller $record) => app(ImagenPublicaService::class)->borrarAlConfirmar($record->imagen)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTalleres::route('/'),
            'create' => Pages\CreateTaller::route('/create'),
            'edit' => Pages\EditTaller::route('/{record}/edit'),
        ];
    }
}
