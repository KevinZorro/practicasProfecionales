// PICO (ISTQB: spike test; ISO/IEC 25023 PCa-2-G capacidad de acceso de
// usuarios y PTb-2-G adecuación del tiempo de respuesta).
//
// Inicio de clase: de 0 a 300 usuarios en 10 s, un minuto sostenido y bajada
// en 10 s. Una sonda aparte (un usuario que pide el panel cada segundo
// durante toda la prueba) mide la línea base antes del pico y la
// recuperación después: cuántos segundos pasan desde que termina el pico
// hasta que la sonda vuelve a recibir 3 respuestas seguidas, 200 y <= 2 s.
//
// IMPORTANTE, UNA SOLA IP: con IP_POR_USUARIO=no (por defecto) los 300 usuarios
// salen de una sola dirección, como el proxy de la universidad sin
// set_real_ip_from (auditoría B3.8, ADR 0006). nginx cuenta por IP: la zona
// general (100 r/s, ráfaga 300) y las 100 conexiones se aplican a todos
// juntos, así que los 429 que aparezcan son el hallazgo B3.8 y no un fallo
// de la aplicación. Con IP_POR_USUARIO=si cada usuario va con su IP
// (X-Forwarded-For; el SUT debe confiar en la pasarela, ver README) y los
// 429 deben ser cero: ese modo mide la aplicación.
//
// Umbrales: 0 5xx; en modo IP real, 0 429; p(95) < 2 s en la fase posterior al
// pico (la prueba falla si el sistema no se recupera). Los 429 y el p95 del
// pico en sí se reportan, no se exigen.
//
// Variables: VUS (300), SUBIDA (10s), SOSTENIDO (1m), BAJADA (10s), BASE (30s
// de sonda antes), RECUPERACION (90s de sonda después), BASE_URL, SESIONES,
// IP_POR_USUARIO.
import { sleep } from 'k6';
import { Gauge } from 'k6/metrics';
import { IP_POR_USUARIO, LIMITE_P95_MS, aMilisegundos, entero, texto } from './lib/config.js';
import { PANTALLAS, nombresDeEventos, nombresDePantallas, ACTIVOS } from './lib/catalogo.js';
import { visitar } from './lib/navegacion.js';
import { MEZCLA, etiquetar, mezclaDisponible, recorridoDelRol, transcurridoMs } from './lib/recorridos.js';
import { crearResumen } from './lib/resumen.js';
import { entrar, personaDelVU } from './lib/sesiones.js';
import { ESTADISTICAS, umbralesBase, umbralesPorEtiqueta, unir } from './lib/umbrales.js';

const VUS = entero('VUS', 300);
const SUBIDA = texto('SUBIDA', '10s');
const SOSTENIDO = texto('SOSTENIDO', '1m');
const BAJADA = texto('BAJADA', '10s');
const BASE = aMilisegundos(texto('BASE', '30s'));
const RECUPERACION = aMilisegundos(texto('RECUPERACION', '90s'));
const PICO = aMilisegundos(SUBIDA) + aMilisegundos(SOSTENIDO) + aMilisegundos(BAJADA);
const FIN_DEL_PICO = BASE + PICO;
const TOTAL = FIN_DEL_PICO + RECUPERACION;

const recuperacion = new Gauge('recuperacion_segundos');

const umbrales = unir(
    umbralesBase(),
    umbralesPorEtiqueta('name', [...nombresDePantallas(), ...nombresDeEventos(), ...ACTIVOS, 'sonda.inicio']),
    umbralesPorEtiqueta('fase', ['antes', 'pico', 'despues']),
    {
        respuestas_5xx: ['count==0'],
        'http_req_duration{fase:despues}': [`p(95)<${LIMITE_P95_MS}`],
        recuperacion_segundos: ['value>=0'],
    },
);

if (IP_POR_USUARIO) {
    umbrales.respuestas_429 = ['count==0'];
}

export const options = {
    scenarios: {
        sonda: { executor: 'constant-vus', vus: 1, duration: `${Math.ceil(TOTAL / 1000)}s`, exec: 'sonda' },
        pico: {
            executor: 'ramping-vus',
            exec: 'pico',
            startTime: `${Math.floor(BASE / 1000)}s`,
            startVUs: 0,
            stages: [
                { duration: SUBIDA, target: VUS },
                { duration: SOSTENIDO, target: VUS },
                { duration: BAJADA, target: 0 },
            ],
            gracefulRampDown: '30s',
            gracefulStop: '30s',
        },
    },
    thresholds: umbrales,
    summaryTrendStats: ESTADISTICAS,
    noCookiesReset: true,
};

function fase() {
    const t = transcurridoMs();

    return t < BASE ? 'antes' : t < FIN_DEL_PICO ? 'pico' : 'despues';
}

export function pico() {
    etiquetar('fase', fase());

    const { rol, ordinal } = personaDelVU(mezclaDisponible(MEZCLA), __VU);

    entrar(rol, ordinal);
    // Inicio de clase: todos abren el panel y su pantalla de siempre, sin tiempo de reflexión largo.
    recorridoDelRol(rol, { reflexion: [1, 3] });
}

let buenosSeguidos = 0;
let recuperado = false;
let faseAnterior = '';

export function sonda() {
    // La sonda es siempre el mismo docente (o el primer rol con sesión).
    const rol = mezclaDisponible({ docente: 1, estudiante: 1, administrativo: 1, coordinador: 1, admin: 1 });
    entrar(Object.keys(rol)[0], 0);

    const faseActual = fase();

    etiquetar('fase', faseActual);

    // Cuenta solo desde que terminó el pico: las respuestas buenas de antes no son recuperación.
    if (faseActual !== faseAnterior) {
        buenosSeguidos = 0;
        faseAnterior = faseActual;
    }

    const respuesta = visitar({ nombre: 'sonda.inicio', ruta: PANTALLAS.inicio.ruta });
    const bueno = respuesta.status === 200 && respuesta.timings.duration <= LIMITE_P95_MS;

    buenosSeguidos = bueno ? buenosSeguidos + 1 : 0;

    if (!recuperado && faseActual === 'despues' && buenosSeguidos >= 3) {
        recuperado = true;
        recuperacion.add(Math.max(0, (transcurridoMs() - FIN_DEL_PICO) / 1000));
    }

    sleep(1);
}

export const handleSummary = crearResumen('pico', {
    tipo: 'pico (spike)',
    medida: 'PCa-2-G capacidad de acceso de usuarios; PTb-2-G adecuación del tiempo de respuesta',
    extra: (data) => {
        const g = data.metrics.recuperacion_segundos;

        return {
            usuarios_pico: VUS,
            origen: IP_POR_USUARIO ? 'una IP por usuario (X-Forwarded-For)' : 'UNA SOLA IP para todos (caso proxy de la universidad, B3.8)',
            recuperacion_segundos: g ? g.values.value : 'no se recuperó antes de terminar la prueba',
            nota: IP_POR_USUARIO ? '' : 'Los 429 con una sola IP son el hallazgo B3.8 (topes por IP de nginx), no un fallo de la aplicación.',
        };
    },
});
