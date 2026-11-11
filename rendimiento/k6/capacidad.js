// CAPACIDAD (ISTQB: capacity test; ISO/IEC 25023 PCa-1-G capacidad de
// procesamiento de transacciones y PCa-2-G capacidad de acceso de usuarios).
//
// Escalones de tasa constante (`constant-arrival-rate`), DURACION_ESCALON
// cada uno, uno tras otro. Cada escalón lleva su etiqueta (escalon) y sus
// métricas: la capacidad es el último escalón con p(95) <= 2 s y 0 errores,
// contando solo hasta el primer escalón que falla (un escalón alto que pase
// por casualidad después de uno que falló no cuenta).
//
// Con IP_POR_USUARIO=si se mide la aplicación; sin ella, desde una sola IP, el
// tope de nginx (100 r/s con ráfaga de 300) fija la capacidad antes que la
// aplicación (B3.8).
//
// PCa-2-G se estima, no se mide: peticiones por segundo del escalón x 6,5 s
// de reflexión media (3 a 10 s) = personas simultáneas que generan esa tasa.
//
// Variables: ESCALONES ("5,10,20,40,80": peticiones por segundo), DURACION_ESCALON
// (2m), VUS_INICIALES (100), VUS_MAXIMOS (1000), BASE_URL, SESIONES, IP_POR_USUARIO.
import { LIMITE_P95_MS, aMilisegundos, entero, texto } from './lib/config.js';
import { nombresDeEventos, nombresDePantallas } from './lib/catalogo.js';
import { MEZCLA, mezclaDisponible, peticionRealista } from './lib/recorridos.js';
import { crearResumen } from './lib/resumen.js';
import { ESTADISTICAS, umbralesBase, umbralesDeCero, umbralesPorEtiqueta, unir } from './lib/umbrales.js';

const TASAS = texto('ESCALONES', '5,10,20,40,80').split(',').map((t) => Number(t.trim())).filter((t) => t > 0);
const DURACION_ESCALON = texto('DURACION_ESCALON', '2m');
const ms = aMilisegundos(DURACION_ESCALON);
const REFLEXION_MEDIA_S = 6.5;
const etiqueta = (tasa) => `r${String(tasa).padStart(4, '0')}`;

const escenarios = {};
TASAS.forEach((tasa, i) => {
    escenarios[etiqueta(tasa)] = {
        executor: 'constant-arrival-rate',
        rate: tasa,
        timeUnit: '1s',
        duration: DURACION_ESCALON,
        startTime: `${Math.round((i * (ms + 15000)) / 1000)}s`,
        preAllocatedVUs: entero('VUS_INICIALES', 100),
        maxVUs: entero('VUS_MAXIMOS', 1000),
        gracefulStop: '15s',
        tags: { escalon: etiqueta(tasa) },
    };
});

export const options = {
    scenarios: escenarios,
    thresholds: unir(
        umbralesBase(),
        umbralesDeCero(),
        umbralesPorEtiqueta('name', [...nombresDePantallas(), ...nombresDeEventos()]),
        umbralesPorEtiqueta('escalon', TASAS.map(etiqueta), LIMITE_P95_MS),
    ),
    summaryTrendStats: ESTADISTICAS,
    noCookiesReset: true,
};

export default function () {
    peticionRealista(mezclaDisponible(MEZCLA));
}

export const handleSummary = crearResumen('capacidad', {
    tipo: 'capacidad (capacity)',
    medida: 'PCa-1-G capacidad de procesamiento de transacciones; PCa-2-G capacidad de acceso de usuarios',
    extra: (data, informe) => {
        const filas = TASAS.map((tasa) => {
            const g = informe.por_escalon[etiqueta(tasa)];
            const sinErrores = g && g.respuestas_429 === 0 && g.respuestas_5xx === 0 && (g.tasa_fallos_http || 0) === 0;
            const bien = !!g && g.p95 <= LIMITE_P95_MS && sinErrores;

            return { tasa_pedida_por_s: tasa, peticiones: g ? g.n : 0, peticiones_por_s_medidas: g ? Math.round((g.n / (ms / 1000)) * 10) / 10 : null,
                p95_ms: g ? g.p95 : null, errores: g ? g.respuestas_429 + g.respuestas_5xx : null, cumple: bien };
        });
        let capacidad = null;

        for (const fila of filas) {
            if (!fila.cumple) {
                break;
            }
            capacidad = fila;
        }

        return {
            escalones: filas,
            capacidad_pca1_peticiones_por_s: capacidad ? capacidad.peticiones_por_s_medidas : null,
            capacidad_escalon_pedido_por_s: capacidad ? capacidad.tasa_pedida_por_s : null,
            usuarios_equivalentes_pca2_estimados: capacidad ? Math.round(capacidad.peticiones_por_s_medidas * REFLEXION_MEDIA_S) : null,
            criterio: `último escalón consecutivo con p95 <= ${LIMITE_P95_MS} ms y 0 errores`,
        };
    },
});
