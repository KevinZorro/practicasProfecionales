// CARGA DE HORA PICO REALISTA (ISTQB: load test; ISO/IEC 25023 PTb-3-G tiempo
// de ida y vuelta de una tarea y PTb-5-G rendimiento medio).
//
// Mezcla por rol con tiempos de reflexión de 3 a 10 s:
//   estudiantes: mis resultados y mi formato · docentes: mis solicitudes,
//   calendario, evaluaciones · administrativos: tablero de preparaciones,
//   inventario y disponibilidad, bandeja · coordinación: bandeja, reportes,
//   bitácora · admin: usuarios.
// 300 usuarios por defecto, 10 minutos con rampa de subida y de bajada.
// Umbrales: p(95) < 2 s por pantalla y errores < 1 %, sin 5xx.
//
// Variables: VUS (300), DURACION (10m), RAMPA (2m), BASE_URL, SESIONES,
// IP_POR_USUARIO, LIMITE_P95_MS.
import { LIMITE_P95_MS, aMilisegundos, entero, texto } from './lib/config.js';
import { nombresDeEventos, nombresDePantallas, ACTIVOS } from './lib/catalogo.js';
import { MEZCLA, mezclaDisponible, recorridoDelRol } from './lib/recorridos.js';
import { crearResumen } from './lib/resumen.js';
import { entrar, personaDelVU } from './lib/sesiones.js';
import { ESTADISTICAS, umbralesBase, umbralesPorEtiqueta, umbralesRnf01, unir } from './lib/umbrales.js';

const VUS = entero('VUS', 300);
const DURACION = aMilisegundos(texto('DURACION', '10m'));
const RAMPA = Math.min(aMilisegundos(texto('RAMPA', '2m')), Math.floor(DURACION / 3));
const segundos = (ms) => `${Math.max(1, Math.round(ms / 1000))}s`;

export const options = {
    scenarios: {
        hora_pico: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: segundos(RAMPA), target: VUS },
                { duration: segundos(DURACION - 2 * RAMPA), target: VUS },
                { duration: segundos(RAMPA), target: 0 },
            ],
            gracefulRampDown: '30s',
            gracefulStop: '30s',
        },
    },
    thresholds: unir(
        umbralesBase(),
        umbralesRnf01([...nombresDePantallas(), ...nombresDeEventos()], LIMITE_P95_MS),
        umbralesPorEtiqueta('name', ACTIVOS),
        { tasa_error: ['rate<0.01'], sesiones_invalidas: ['count==0'] },
    ),
    summaryTrendStats: ESTADISTICAS,
    noCookiesReset: true,
};

// En esta prueba un 429 no se tolera, pero el umbral de errores es del 1 %:
// se quita el 'rate==0' que umbralesRnf01 trae para el RNF01.
options.thresholds.tasa_error = ['rate<0.01'];

export default function () {
    const { rol, ordinal } = personaDelVU(mezclaDisponible(MEZCLA), __VU);

    entrar(rol, ordinal);
    recorridoDelRol(rol);
}

export const handleSummary = crearResumen('carga-mixta', {
    tipo: 'carga (hora pico realista)',
    medida: 'PTb-3-G tiempo de ida y vuelta de una tarea; PTb-5-G rendimiento medio',
});
