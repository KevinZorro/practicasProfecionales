// Construcción de umbrales y submétricas. k6 solo guarda la métrica de un tag
// (por ejemplo http_req_duration{name:panel.calendario}) si algún umbral la
// nombra; por eso cada escenario declara los de sus pantallas, para que el
// resumen pueda decir p95 por pantalla. Los "informativos" siempre se cumplen:
// existen para obtener la métrica, no para aprobar o reprobar.
import { LIMITE_P95_MS } from './config.js';

export const INFORMATIVO = 'p(95)>=0';

const SUBMETRICAS_DE_CONTADOR = ['apdex_satisfechas', 'apdex_toleradas', 'apdex_total'];

// Para cada etiqueta de una dimensión (name, escalon, ventana) pide las métricas
// que el resumen necesita. `limiteMs` (opcional) convierte la de latencia en umbral real.
export function umbralesPorEtiqueta(dimension, valores, limiteMs = null) {
    const umbrales = {};

    for (const valor of valores) {
        const sufijo = `{${dimension}:${valor}}`;
        umbrales[`http_req_duration${sufijo}`] = [limiteMs === null ? INFORMATIVO : `p(95)<${limiteMs}`];
        umbrales[`http_req_failed${sufijo}`] = ['rate>=0'];
        umbrales[`http_reqs${sufijo}`] = ['count>=0'];

        if (dimension === 'name') {
            for (const contador of SUBMETRICAS_DE_CONTADOR) {
                umbrales[`${contador}${sufijo}`] = ['count>=0'];
            }
        } else {
            // En escalones, ventanas y fases importa qué falló primero.
            for (const contador of ['respuestas_429', 'respuestas_5xx', 'respuestas_502_504']) {
                umbrales[`${contador}${sufijo}`] = ['count>=0'];
            }
        }
    }

    return umbrales;
}

// Umbrales globales que casi todas las pruebas comparten: nada de 429 ni 5xx
// ni errores. Se pueden sobrescribir con Object.assign después.
export function umbralesDeCero() {
    return {
        respuestas_429: ['count==0'],
        respuestas_5xx: ['count==0'],
        sesiones_invalidas: ['count==0'],
        tasa_error: ['rate==0'],
    };
}

// p95 < LIMITE (RNF01) para las pantallas dadas, 0 errores y 0 de 429/5xx.
export function umbralesRnf01(nombres, limite = LIMITE_P95_MS) {
    return Object.assign(umbralesDeCero(), umbralesPorEtiqueta('name', nombres, limite));
}

// Métricas informativas globales para el resumen.
export function umbralesBase() {
    return {
        http_req_duration: [INFORMATIVO],
        http_reqs: ['count>=0'],
        apdex_total: ['count>=0'],
        apdex_satisfechas: ['count>=0'],
        apdex_toleradas: ['count>=0'],
        respuestas_429: ['count>=0'],
        respuestas_5xx: ['count>=0'],
        respuestas_502_504: ['count>=0'],
        rechazos_422: ['count>=0'],
        sesiones_invalidas: ['count>=0'],
        tasa_error: ['rate>=0'],
    };
}

// Une varios grupos de umbrales sin pisar los de pasa/no pasa con los informativos.
export function unir(...grupos) {
    const resultado = {};

    for (const grupo of grupos) {
        for (const [metrica, expresiones] of Object.entries(grupo)) {
            const actuales = resultado[metrica] || [];
            const nuevas = expresiones.filter((e) => !actuales.includes(e));
            const hayReal = [...actuales, ...nuevas].some((e) => e !== INFORMATIVO && !/>=0$/.test(e));

            resultado[metrica] = [...actuales, ...nuevas].filter((e) => !(hayReal && (e === INFORMATIVO || /^(count|rate)>=0$/.test(e))));
        }
    }

    return resultado;
}

export const ESTADISTICAS = ['avg', 'min', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'];
