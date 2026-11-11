// CARGA, EL ESCENARIO RNF01 TAL CUAL (ISTQB: load test; ISO/IEC 25023 PTb-2-G
// adecuación del tiempo de respuesta y PCa-2-G capacidad de acceso de usuarios).
//
// 150 personas distintas (sobre todo docentes y estudiantes) abren, en una
// ventana de 5 minutos, el panel, el calendario y piden sus eventos, como el
// navegador: con los assets de /build y livewire.js, con la caché vacía la
// primera vez. Cada persona es un usuario virtual con su propia sesión.
//
// Umbrales (docs/escenarios-de-calidad.md, RNF01): p(95) < 2000 ms en las
// pantallas, 0 % de errores, 0 respuestas 429 y 0 5xx. El veredicto OFICIAL
// del RNF01 es el de nginx ($request_time): corre después
// rendimiento/herramientas/resumir-nginx.php sobre el log del servicio web.
// Este resumen es el punto de vista del cliente (incluye la red local).
//
// Variables: PERSONAS (150), VENTANA (5m), BASE_URL, SESIONES, IP_POR_USUARIO,
// CACHE_VACIA (si: assets en cada visita), LIMITE_P95_MS (2000).
import { sleep } from 'k6';
import { LIMITE_P95_MS, aMilisegundos, entero, texto } from './lib/config.js';
import { PANTALLAS, eventosDelCalendario, nombresDeEventos, nombresDePantallas } from './lib/catalogo.js';
import { visitar, pensar } from './lib/navegacion.js';
import { MEZCLA_RNF01, PRINCIPAL, mezclaDisponible } from './lib/recorridos.js';
import { crearResumen } from './lib/resumen.js';
import { ROLES_CON_SESION, cuantasHay, entrar, personaDelVU } from './lib/sesiones.js';
import { ESTADISTICAS, umbralesBase, umbralesPorEtiqueta, umbralesRnf01, unir } from './lib/umbrales.js';

const PERSONAS = entero('PERSONAS', 150);
const VENTANA = texto('VENTANA', '5m');

export const options = {
    scenarios: {
        personas: {
            executor: 'per-vu-iterations',
            vus: PERSONAS,
            iterations: 1,
            maxDuration: `${Math.ceil(aMilisegundos(VENTANA) / 1000) + 180}s`,
        },
    },
    thresholds: unir(
        umbralesBase(),
        umbralesRnf01([...nombresDePantallas(), ...nombresDeEventos()], LIMITE_P95_MS),
        umbralesPorEtiqueta('name', ['activo.css', 'activo.js', 'activo.livewire', 'activo.otro']),
    ),
    summaryTrendStats: ESTADISTICAS,
    noCookiesReset: true,
};

export function setup() {
    const mezcla = mezclaDisponible(MEZCLA_RNF01);
    const faltan = Object.keys(mezcla).filter((rol) => cuantasHay(rol) < PERSONAS * mezcla[rol] / 100);

    if (faltan.length > 0) {
        console.warn(`Hay menos sesiones que personas en los roles ${faltan.join(', ')}: se reutilizan y no son personas distintas. `
            + 'Crea más con RENDIMIENTO_SESIONES_POR_ROL.');
    }
    if (ROLES_CON_SESION.length === 0) {
        throw new Error('El archivo de sesiones está vacío');
    }
}

export default function () {
    const mezcla = mezclaDisponible(MEZCLA_RNF01);
    const { rol, ordinal } = personaDelVU(mezcla, __VU);
    const ventanaS = aMilisegundos(VENTANA) / 1000;

    // Llegan repartidos por toda la ventana, no todos en el segundo cero:
    // el RNF01 habla de 150 personas en 5 minutos, no de un pico instantáneo.
    sleep(((__VU - 1) / PERSONAS) * ventanaS * 0.95 + Math.random() * (ventanaS * 0.05));

    entrar(rol, ordinal);

    visitar(PANTALLAS.inicio, { activos: true });
    pensar(2, 5);
    visitar(PANTALLAS.calendario, { activos: true });
    visitar(eventosDelCalendario('mes'));
    pensar(2, 5);
    visitar(eventosDelCalendario('semana'));
    visitar(PANTALLAS[PRINCIPAL[rol]] || PANTALLAS.inicio, { activos: true });
}

export const handleSummary = crearResumen('carga-rnf01', {
    tipo: 'carga (escenario RNF01)',
    medida: 'PTb-2-G adecuación del tiempo de respuesta; PCa-2-G capacidad de acceso de usuarios',
});
