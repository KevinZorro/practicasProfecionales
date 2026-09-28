<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Formularios\CampoDeImagen;
use App\Filament\RecursoDelAdmin;
use App\Filament\Resources\EventoResource\Pages;
use App\Models\Evento;
use App\Models\TipoEvento;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Validation\Rules\Exists;

/**
 * Eventos de la landing (RF05, RF14). El tipo sale del catálogo que
 * gestiona el ADMIN (TipoEventoResource).
 */
final class EventoResource extends RecursoDelAdmin
{
    protected static ?string $model = Evento::class;

    protected static ?string $slug = 'eventos';

    protected static ?string $modelLabel = 'evento';

    protected static ?string $pluralModelLabel = 'eventos';

    protected static ?string $navigationGroup = 'Contenido público';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'titulo';

    public static function camposDeImagen(): array
    {
        return ['imagen'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Evento')->columns(2)->schema([
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
                DatePicker::make('fecha')
                    ->required(),
                // Un tipo desactivado no se ofrece en los eventos nuevos, pero
                // el evento que ya lo tiene lo sigue viendo y conservando. La
                // regla exists lo exige también en el servidor: las opciones
                // del selector solo filtran lo que se ve.
                Select::make('tipo_evento_id')
                    ->label('Tipo de evento')
                    ->relationship('tipoEvento', 'nombre', static fn (Builder $query, ?Evento $record) => self::limitarATiposDisponibles($query, $record))
                    // Filament inyecta por nombre: el parámetro tiene que llamarse $rule.
                    ->exists('tipos_evento', 'id', static fn (Exists $rule, ?Evento $record): Exists => $rule->where(
                        static fn (QueryBuilder $consulta) => self::limitarATiposDisponibles($consulta, $record),
                    ))
                    ->preload()
                    ->required(),
                CampoDeImagen::make('imagen', 'eventos')
                    ->label('Imagen')
                    ->columnSpanFull(),
            ]),
            Section::make('Publicación')->columns(2)->schema([
                Toggle::make('abierto_publico')
                    ->label('Abierto al público')
                    ->helperText('Si no, es solo para la comunidad universitaria.')
                    ->default(true),
                Toggle::make('activo')
                    ->label('Publicado')
                    ->helperText('Un evento sin publicar se conserva, pero no sale en la landing.')
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
                TextColumn::make('tipoEvento.nombre')->label('Tipo')->badge(),
                IconColumn::make('activo')->label('Publicado')->boolean(),
            ])
            ->reorderable('orden')
            ->defaultSort('orden')
            ->filters([
                TernaryFilter::make('activo')->label('Publicado'),
                SelectFilter::make('tipo_evento_id')->label('Tipo')->relationship('tipoEvento', 'nombre'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->after(static fn (Evento $record) => app(ImagenPublicaService::class)->borrarAlConfirmar($record->imagen)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEventos::route('/'),
            'create' => Pages\CreateEvento::route('/create'),
            'edit' => Pages\EditEvento::route('/{record}/edit'),
        ];
    }

    /**
     * Deja en la consulta los tipos activos, más el que ya tiene el evento
     * aunque se haya desactivado después. La usan el selector (una consulta
     * de Eloquent) y la regla exists (una del constructor de consultas).
     *
     * @param  QueryBuilder|Builder<TipoEvento>  $consulta
     */
    private static function limitarATiposDisponibles(QueryBuilder|Builder $consulta, ?Evento $evento): void
    {
        $consulta->where(static function (QueryBuilder|Builder $condicion) use ($evento): void {
            $condicion->where('activo', true);

            if ($evento?->tipo_evento_id !== null) {
                $condicion->orWhere('id', $evento->tipo_evento_id);
            }
        });
    }
}
