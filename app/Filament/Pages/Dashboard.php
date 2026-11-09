<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard as EscritorioDeFilament;

/**
 * La entrada de /admin. El escritorio de Filament llegaba vacío, un título
 * y nada más: aquí está lo que se puede cambiar, por grupo, con una línea
 * que dice para qué es cada uno, y dónde está lo que no vive aquí.
 *
 * Los enlaces salen de la navegación del panel, así que un recurso nuevo
 * aparece aquí sin tocar esta página. Se llama Dashboard para conservar la
 * ruta de Filament (filament.admin.pages.dashboard).
 */
final class Dashboard extends EscritorioDeFilament
{
    /**
     * Los grupos del menú, en el orden en que se ven (AdminPanelProvider), y
     * para qué es cada uno, dicho para quien administra.
     */
    public const GRUPOS = [
        'Estructura académica' => 'Lo que el docente elige al pedir un escenario: las materias, los casos clínicos con su inventario y su capacidad, y los tipos de evaluación con su checklist.',
        'Laboratorio' => 'Las salas, con su ubicación y cuántos caben, y los ajustes que cambian sin desplegar.',
        'Contenido público' => 'Lo que ve cualquiera en la portada, sin entrar: los textos, el video, las fotos, los talleres, los eventos y el contacto del laboratorio.',
    ];

    protected static ?string $title = 'Administración';

    protected static ?string $navigationLabel = 'Inicio';

    protected static string $view = 'filament.pages.inicio';

    public function getSubheading(): string
    {
        return 'Aquí se cambia lo que la plataforma ofrece y lo que muestra la portada. El trabajo del día a día —solicitudes, preparación, inventario, evaluaciones— está en el panel.';
    }

    /**
     * @return list<array{titulo: string, descripcion: ?string, enlaces: list<array{etiqueta: string, url: string, icono: ?string}>}>
     */
    public function grupos(): array
    {
        $grupos = [];

        foreach (Filament::getNavigation() as $grupo) {
            $titulo = $grupo->getLabel();

            if ($titulo === null) {
                continue;
            }

            $grupos[] = [
                'titulo' => $titulo,
                'descripcion' => self::GRUPOS[$titulo] ?? null,
                'enlaces' => collect($grupo->getItems())
                    ->map(static fn (NavigationItem $item): array => [
                        'etiqueta' => $item->getLabel(),
                        'url' => (string) $item->getUrl(),
                        'icono' => is_string($item->getIcon()) ? $item->getIcon() : null,
                    ])
                    ->values()
                    ->all(),
            ];
        }

        return $grupos;
    }
}
