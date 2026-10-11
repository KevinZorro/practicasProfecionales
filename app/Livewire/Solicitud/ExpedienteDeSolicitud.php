<?php

declare(strict_types=1);

namespace App\Livewire\Solicitud;

use App\Enums\EstadoSolicitud;
use App\Livewire\Concerns\AutorizaEnCadaPeticion;
use App\Livewire\Concerns\AvisaDelResultado;
use App\Models\ItemInventario;
use App\Models\Preparacion;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\InventarioService;
use App\Services\SolicitudService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * El detalle del expediente de una solicitud: lo que se pidió, quién la ha
 * tramitado y, al pie, la decisión que le toca a quien la mira: revisarla o
 * rechazarla en la primera fase (RF30), aprobarla o rechazarla en la
 * segunda (RF31).
 *
 * Qué decisión aparece lo dice la Policy, no este componente: aquí no se
 * comprueba ningún rol. Las transiciones las hace el Service. Al terminar se
 * recarga la página, porque la cabecera del expediente (la línea de estados)
 * no es parte del componente.
 */
final class ExpedienteDeSolicitud extends Component
{
    use AutorizaEnCadaPeticion;
    use AvisaDelResultado;

    #[Locked]
    public int $solicitudId;

    public bool $rechazando = false;

    public string $motivoRechazo = '';

    public function mount(Solicitud $solicitud): void
    {
        $this->solicitudId = $solicitud->id;
    }

    public function revisar(SolicitudService $solicitudes): void
    {
        $solicitud = $this->solicitud();
        $this->authorize('revisar', $solicitud);

        $solicitudes->marcarRevisada($solicitud, $this->actor());
        $this->terminar('Solicitud marcada como revisada. Pasa a coordinación para aprobarla.');
    }

    public function aprobar(SolicitudService $solicitudes): void
    {
        $solicitud = $this->solicitud();
        $this->authorize('aprobar', $solicitud);

        $solicitudes->aprobar($solicitud, $this->actor());
        $this->terminar('Solicitud aprobada. El docente recibirá la notificación.');
    }

    public function pedirMotivo(): void
    {
        $this->rechazando = true;
        $this->motivoRechazo = '';
    }

    public function cancelarRechazo(): void
    {
        $this->reset('rechazando', 'motivoRechazo');
        $this->resetValidation();
    }

    public function rechazar(SolicitudService $solicitudes): void
    {
        $solicitud = $this->solicitud();
        $this->authorize('rechazar', $solicitud);
        $this->validate(['motivoRechazo' => 'nullable|string|max:1000']);

        $solicitudes->rechazar($solicitud, $this->actor(), $this->motivoRechazo ?: null);
        $this->terminar('Solicitud rechazada. El docente recibirá el motivo.');
    }

    /** El permiso que exige el controlador de la página. */
    protected function autorizarPantalla(): void
    {
        $this->authorize('view', $this->solicitud());
    }

    public function render(SolicitudService $solicitudes): mixed
    {
        $solicitud = $solicitudes->paraDetalle($this->solicitud());
        $actor = $this->actor();

        // Regla 5: el docente no ve la disponibilidad del inventario.
        $veInventario = $actor->can('viewAny', ItemInventario::class);

        return view('livewire.solicitud.expediente-de-solicitud', [
            'solicitud' => $solicitud,
            'puedeRevisar' => $solicitud->estado === EstadoSolicitud::Pendiente && $actor->can('revisar', $solicitud),
            'puedeAprobar' => $actor->can('aprobar', $solicitud),
            'puedeRechazar' => $actor->can('rechazar', $solicitud),
            'faltantes' => $veInventario ? $this->faltantesDeInventario($solicitud) : [],
            'sinCobertura' => $veInventario && $this->quedoSinCobertura($solicitud),
            'veLaPreparacion' => $actor->can('viewAny', Preparacion::class),
        ]);
    }

    /**
     * Equipos de los que se pidieron más unidades de las que quedan libres
     * en esa franja. Solo mientras se decide: aprobada, la propia solicitud
     * ya cuenta entre lo comprometido, y lo que avisa entonces es
     * quedoSinCobertura().
     *
     * Es información para quien decide, no un bloqueo: el docente no ve
     * disponibilidad al solicitar, así que puede pedir de más.
     *
     * @return array<int, int> id del ítem => unidades libres
     */
    private function faltantesDeInventario(Solicitud $solicitud): array
    {
        if (! in_array($solicitud->estado, [EstadoSolicitud::Pendiente, EstadoSolicitud::Revisada], true)) {
            return [];
        }

        $disponibles = app(InventarioService::class)->disponibilidadDeVarios(
            $solicitud->items,
            $solicitud->fecha->format('Y-m-d'),
            $solicitud->hora_inicio,
            $solicitud->hora_fin,
        );

        return $solicitud->items
            ->filter(static fn (ItemInventario $item): bool => ($disponibles[$item->id] ?? 0) < (int) data_get($item, 'pivot.cantidad'))
            ->mapWithKeys(static fn (ItemInventario $item): array => [$item->id => $disponibles[$item->id] ?? 0])
            ->all();
    }

    /** RF66: aprobada, y alguno de sus equipos dejó de estar operativo después. */
    private function quedoSinCobertura(Solicitud $solicitud): bool
    {
        return $solicitud->estado === EstadoSolicitud::Aprobada
            && in_array($solicitud->id, app(InventarioService::class)->solicitudesAprobadasSinCobertura(), true);
    }

    private function terminar(string $mensaje): void
    {
        $this->avisar($mensaje);
        $this->redirectRoute('panel.solicitudes.ver', ['solicitud' => $this->solicitudId], navigate: true);
    }

    private function solicitud(): Solicitud
    {
        return Solicitud::findOrFail($this->solicitudId);
    }

    private function actor(): User
    {
        /** @var User */
        return Auth::user();
    }
}
