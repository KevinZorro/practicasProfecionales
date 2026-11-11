// ESTRÉS (ISTQB: stress test; ISO/IEC 25023 PCa-1-G capacidad de procesamiento
// de transacciones y PTb-5-G rendimiento medio).
//
// Tasa de llegada creciente (`ramping-arrival-rate`) por encima de lo
// esperado hasta degradar. Cada escalón sube la tasa en PASO peticiones por
// segundo durante DURACION_ESCALON. La prueba se corta sola (abortOnFail) si
// el p(95) pasa de 10 s o los errores de 20 %.
//
// El análisis dice en qué tasa empezó a degradar (primer escalón con p95 >
// 2 s o algún error) y qué falló primero: 429 de nginx (topes por IP),
// 502/503/504 (PHP-FPM saturado: 20 workers), otros 5xx, o solo tiempos. Si
// k6 reporta `dropped_iterations`, no alcanzó a lanzar la tasa pedida: es otra
// señal de saturación (del generador o del servidor).
//
// Con IP_POR_USUARIO=no (por defecto) todo sale de una IP y los topes de
// nginx cortarán antes que PHP-FPM (hallazgo B3.8); con =si se mide la
// aplicación. Mismo script, solo cambia la variable.
//
// Variables: TASA_INICIAL (5), PASO (10), ESCALONES (12), DURACION_ESCALON (1m),
// VUS_INICIALES (100), VUS_MAXIMOS (1000), BASE_URL, SESIONES, IP_POR_USUARIO.
import { aMilisegundos, entero, texto } from './lib/config.js';
import { nombresDeEventos, nombresDePantallas } from './lib/catalogo.js';
import { MEZCLA, etiquetar, mezclaDisponible, peticionRealista, transcurridoMs } from './lib/recorridos.js';
import { crearResumen } from './lib/resumen.js';
import { ESTADISTICAS, umbralesBase, umbralesPorEtiqueta, unir } from './lib/umbrales.js';

const TASA_INICIAL = entero('TASA_INICIAL', 5);
const PASO = entero('PASO', 10);
const ESCALONES = entero('ESCALONES', 12);
const DURACION_ESCALON = texto('DURACION_ESCALON', '1m');
const ms = aMilisegundos(DURACION_ESCALON);

const tasaDe = (i) => TASA_INICIAL + i * PASO;
const etiquetas = Array.from({ length: ESCALONES }, (_, i) => `e${String(i).padStart(2, '0')}`);

export const options = {
    scenarios: {
        estres: {
            executor: 'ramping-arrival-rate',
            startRate: TASA_INICIAL,
            timeUnit: '1s',
            preAllocatedVUs: entero('VUS_INICIALES', 100),
            maxVUs: entero('VUS_MAXIMOS', 1000),
            stages: etiquetas.map((_, i) => ({ target: tasaDe(i), duration: DURACION_ESCALON })),
            gracefulStop: '30s',
        },
    },
    thresholds: unir(
        umbralesBase(),
        umbralesPorEtiqueta('name', [...nombresDePantallas(), ...nombresDeEventos()]),
        umbralesPorEtiqueta('escalon', etiquetas),
        {
            http_req_duration: [{ threshold: 'p(95)<10000', abortOnFail: true, delayAbortEval: '30s' }],
            tasa_error: [{ threshold: 'rate<0.2', abortOnFail: true, delayAbortEval: '30s' }],
        },
    ),
    summaryTrendStats: ESTADISTICAS,
    noCookiesReset: true,
};

export default function () {
    const escalon = Math.min(ESCALONES - 1, Math.floor(transcurridoMs() / ms));

    etiquetar('escalon', etiquetas[escalon]);
    peticionRealista(mezclaDisponible(MEZCLA));
}

export const handleSummary = crearResumen('estres', {
    tipo: 'estrés (stress)',
    medida: 'PCa-1-G capacidad de procesamiento de transacciones; PTb-5-G rendimiento medio',
    extra: (data, informe) => {
        const filas = Object.keys(informe.por_escalon).sort();
        const degradado = filas.find((e) => {
            const g = informe.por_escalon[e];

            return g.p95 > 2000 || g.respuestas_429 > 0 || g.respuestas_5xx > 0 || g.tasa_fallos_http > 0;
        });
        let primero = 'nada: no degradó en los escalones que corrieron';

        if (degradado) {
            const g = informe.por_escalon[degradado];
            const causas = [];
            if (g.respuestas_429 > 0) causas.push(`429 de nginx (${g.respuestas_429})`);
            if (g.respuestas_502_504 > 0) causas.push(`502/503/504, PHP-FPM saturado (${g.respuestas_502_504})`);
            if (g.respuestas_5xx - g.respuestas_502_504 > 0) causas.push(`otros 5xx (${g.respuestas_5xx - g.respuestas_502_504})`);
            if (g.p95 > 2000) causas.push(`tiempos: p95 ${Math.round(g.p95)} ms`);
            primero = causas.join('; ') || 'errores de comprobación';
        }

        const descartadas = data.metrics.dropped_iterations ? data.metrics.dropped_iterations.values.count : 0;

        return {
            escalon_en_que_empezo_a_degradar: degradado || null,
            tasa_pedida_en_ese_escalon_por_s: degradado ? tasaDe(Number(degradado.slice(1))) : null,
            primero_que_fallo: primero,
            iteraciones_descartadas_por_falta_de_usuarios_virtuales: descartadas,
            tasas_pedidas_por_escalon: Object.fromEntries(etiquetas.map((e, i) => [e, tasaDe(i)])),
            cortada_por_umbral: !data.metrics.http_req_duration || !!Object.values(data.metrics.http_req_duration.thresholds || {}).find((t) => t.ok === false),
        };
    },
});
