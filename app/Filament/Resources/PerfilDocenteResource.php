<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\Rol;
use App\Filament\Formularios\CampoDeImagen;
use App\Filament\RecursoDelAdmin;
use App\Filament\Resources\PerfilDocenteResource\Pages;
use App\Models\PerfilDocente;
use App\Services\ImagenPublicaService;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
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
use Illuminate\Database\Eloquent\Builder;

/**
 * Perfiles docentes de la landing (RF07, RF16), con sus títulos.
 */
final class PerfilDocenteResource extends RecursoDelAdmin
{
    protected static ?string $model = PerfilDocente::class;

    protected static ?string $slug = 'perfiles-docentes';

    protected static ?string $modelLabel = 'perfil docente';

    protected static ?string $pluralModelLabel = 'perfiles docentes';

    protected static ?string $navigationGroup = 'Contenido público';

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function camposDeImagen(): array
    {
        return ['foto'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Perfil')->columns(2)->schema([
                TextInput::make('nombre')
                    ->required()
                    ->maxLength(255),
                TextInput::make('cargo')
                    ->required()
                    ->maxLength(255),
                // Opcional: el perfil público puede ser de alguien sin cuenta
                // en la plataforma. Se busca al escribir, sin cargar la lista
                // entera de usuarios, y solo entre quienes tienen el rol
                // vigente: roles() ya filtra por vigencia (regla 13).
                Select::make('user_id')
                    ->label('Cuenta en la plataforma')
                    ->helperText('Opcional: enlaza el perfil con la cuenta del docente.')
                    ->relationship('user', 'nombre', static fn (Builder $query) => $query->whereHas(
                        'roles',
                        static fn (Builder $rol) => $rol->where('name', Rol::Docente->value),
                    ))
                    ->searchable()
                    ->columnSpanFull(),
                CampoDeImagen::make('foto', 'perfiles')
                    ->label('Foto')
                    ->columnSpanFull(),
                Toggle::make('activo')
                    ->label('Publicado')
                    ->helperText('Un perfil sin publicar se conserva, pero no sale en la landing.')
                    ->default(true),
            ]),
            Section::make('Títulos')->schema([
                Repeater::make('titulos')
                    ->hiddenLabel()
                    ->relationship()
                    ->orderColumn('orden')
                    ->schema([
                        TextInput::make('titulo')
                            ->label('Título')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('institucion')
                            ->label('Institución')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel('Añadir título'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('foto')->disk(ImagenPublicaService::DISCO)->circular()->height(40),
                TextColumn::make('nombre')->searchable(),
                TextColumn::make('cargo'),
                TextColumn::make('titulos_count')->label('Títulos')->counts('titulos'),
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
                    ->after(static fn (PerfilDocente $record) => app(ImagenPublicaService::class)->borrarAlConfirmar($record->foto)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPerfilesDocentes::route('/'),
            'create' => Pages\CreatePerfilDocente::route('/create'),
            'edit' => Pages\EditPerfilDocente::route('/{record}/edit'),
        ];
    }
}
