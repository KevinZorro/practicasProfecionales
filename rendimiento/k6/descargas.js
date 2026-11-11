// OPERACIONES PESADAS (ISTQB: load test de operaciones críticas + prueba de los
// topes; ISO/IEC 25023 PTb-3-G tiempo de ida y vuelta de una tarea, PRu-3-G uso
// de E/S y PTb-5-G rendimiento).
//
// Escenario `descargas`: coordinación baja los reportes agregados en PDF y
// Excel con filtros realistas y, si hay alguna lista de reposición cerrada,
// la baja también. PDF dentro del tope (laboratorio.reportes: 31 días y 500
// filas); Excel con un rango mayor, porque no tiene tope. Se reporta latencia
// y tamaño por descarga. El ritmo de cada usuario queda por debajo de los
// topes (una descarga cada 3 s = 20 por minuto, menos que las 30 de la app y
// que los 5 r/s de nginx).
//
// Escenario `topes`: comprueba que los topes funcionan como se diseñaron:
//  - nginx (zona pesadas: 5 r/s con ráfaga de 20, por IP): una ráfaga de 60
//    peticiones a una ruta de descarga a la vez debe dejar pasar unas 21 y
//    cortar el resto con 429 de nginx (sin las cabeceras X-RateLimit de
//    Laravel). Solo existe con nginx delante: HAY_NGINX=no la omite
//    (el servidor de desarrollo no lo tiene).
//  - aplicación (30 descargas por minuto por usuario, throttle:descargas):
//    una persona a 3 por segundo (por debajo del tope de nginx) debe recibir
//    429 de Laravel (con X-RateLimit-Limit y Retry-After) a partir de la 31.
// Ese escenario genera 429 a propósito: corre solo y no entra en los
// umbrales de «0 respuestas 429». Con IP_POR_USUARIO=no la ráfaga de nginx
// cuenta por la IP compartida: no la corras junto a otra prueba.
//
// Umbrales: p(95) de cada descarga < DESCARGA_P95_MS (10 s, supuesto: el RNF no
// fija un número para reportes), 0 errores en las descargas; en topes:
// al menos un 429 de nginx, a lo sumo 30 pasan por minuto y al menos un 429
// de la aplicación.
//
// Variables: ESCENARIOS ("descargas,topes"), REPETICIONES (3), USUARIOS (2),
// DIAS_PDF (30), DIAS_EXCEL (180), DESCARGA_P95_MS (10000), HAY_NGINX (si),
// BASE_URL, SESIONES, IP_POR_USUARIO.
import { sleep } from 'k6';
import { Counter } from 'k6/metrics';
import { BASE_URL, entero, texto } from './lib/config.js';
import { http, lote, pedir } from './lib/http.js';
import { descubrirListasCerradas } from './lib/descubrir.js';
import { crearResumen } from './lib/resumen.js';
import { entrar } from './lib/sesiones.js';
import { ESTADISTICAS, umbralesBase, umbralesPorEtiqueta, unir } from './lib/umbrales.js';
import { registrar } from './lib/verificar.js';

const ESCENARIOS = texto('ESCENARIOS', 'descargas,topes').split(',').map((e) => e.trim());
const REPETICIONES = entero('REPETICIONES', 3);
const DIAS_PDF = entero('DIAS_PDF', 30);
const DIAS_EXCEL = entero('DIAS_EXCEL', 180);
const DESCARGA_P95_MS = entero('DESCARGA_P95_MS', 10000);
const HAY_NGINX = texto('HAY_NGINX', 'si') === 'si';
const RAFAGA = 60;
const PETICIONES_DE_LA_APP = 36;
const REPORTES = ['uso_de_escenarios', 'resultados_de_evaluacion', 'evaluaciones_no_registradas'];
// Ruta que cae en la zona «pesadas» de nginx y en throttle:descargas, y que
// no cuesta nada de generar: lo que se mide es el tope, no la descarga.
const RUTA_DE_TOPE = '/panel/formatos-confidencialidad/plantilla';

const nginx429 = new Counter('topes_nginx_429');
const nginxPasaron = new Counter('topes_nginx_pasaron');
const app429 = new Counter('topes_app_429');
const appPasaron = new Counter('topes_app_pasaron');
const bytes = new Counter('descargas_bytes');

const nombresDeDescarga = [];
for (const r of REPORTES) {
    nombresDeDescarga.push(`descarga.${r}.pdf`, `descarga.${r}.excel`);
}
nombresDeDescarga.push('descarga.lista-reposicion.pdf', 'descarga.lista-reposicion.excel');

const umbrales = unir(umbralesBase(), umbralesPorEtiqueta('name', nombresDeDescarga, DESCARGA_P95_MS), {
    topes_nginx_429: ['count>=0'], topes_nginx_pasaron: ['count>=0'], topes_app_429: ['count>=0'], topes_app_pasaron: ['count>=0'],
    descargas_bytes: ['count>=0'],
});

if (ESCENARIOS.includes('descargas')) {
    Object.assign(umbrales, { respuestas_5xx: ['count==0'], respuestas_429: ['count==0'], sesiones_invalidas: ['count==0'] });
}
if (ESCENARIOS.includes('topes')) {
    umbrales.topes_app_429 = ['count>=1'];
    umbrales.topes_app_pasaron = [`count<=${30}`];

    if (HAY_NGINX) {
        umbrales.topes_nginx_429 = ['count>=1'];
    }
}

const escenarios = {};
if (ESCENARIOS.includes('descargas')) {
    escenarios.descargas = {
        executor: 'per-vu-iterations', vus: entero('USUARIOS', 2), iterations: REPETICIONES, maxDuration: '30m', exec: 'descargas',
    };
}
if (ESCENARIOS.includes('topes')) {
    escenarios.topes = {
        executor: 'per-vu-iterations', vus: 1, iterations: 1, maxDuration: '5m', exec: 'topes',
        // Después de las descargas, para que el tope por usuario de la app no se mezcle.
        startTime: ESCENARIOS.includes('descargas') ? `${60 + REPETICIONES * 3 * (REPORTES.length * 2 + 2) + 30}s` : '0s',
    };
}

export const options = {
    scenarios: escenarios,
    thresholds: umbrales,
    summaryTrendStats: ESTADISTICAS,
    noCookiesReset: true,
};

function fechaMenos(dias) {
    return new Date(Date.now() - dias * 86400000).toISOString().slice(0, 10);
}

export function setup() {
    return { listas: ESCENARIOS.includes('descargas') ? descubrirListasCerradas('coordinador') : [] };
}

export function descargas(datos) {
    entrar('coordinador', __VU - 1);

    const hasta = fechaMenos(0);
    const pesadas = [];

    for (const reporte of REPORTES) {
        pesadas.push([`descarga.${reporte}.pdf`, `/panel/reportes/${reporte}/pdf?desde=${fechaMenos(DIAS_PDF)}&hasta=${hasta}`]);
        pesadas.push([`descarga.${reporte}.excel`, `/panel/reportes/${reporte}/excel?desde=${fechaMenos(DIAS_EXCEL)}&hasta=${hasta}`]);
    }
    if (datos.listas.length > 0) {
        const lista = datos.listas[(__VU - 1) % datos.listas.length];
        pesadas.push(['descarga.lista-reposicion.pdf', `/panel/reposicion/${lista}/pdf`]);
        pesadas.push(['descarga.lista-reposicion.excel', `/panel/reposicion/${lista}/excel`]);
    }

    for (const [nombre, ruta] of pesadas) {
        const r = pedir('GET', `${BASE_URL}${ruta}`, null, {
            tags: { name: nombre },
            responseType: 'binary',
            responseCallback: http.expectedStatuses(200, 422),
            timeout: '180s',
        });

        // Un 422 es el tope de filas o de días del PDF diciendo que no: aceptable y visible.
        registrar(r, nombre, { aceptar422: true });
        if (r.status === 200 && r.body) {
            bytes.add(r.body.byteLength, { name: nombre });
        }
        sleep(3);
    }
}

function esDeLaApp(respuesta) {
    return respuesta.headers['X-Ratelimit-Limit'] !== undefined || respuesta.headers['Retry-After'] !== undefined;
}

export function topes() {
    entrar('docente', 0);
    const aceptar = http.expectedStatuses({ min: 200, max: 499 });

    if (HAY_NGINX) {
        const rafaga = lote(Array.from({ length: RAFAGA }, () => ['GET', `${BASE_URL}${RUTA_DE_TOPE}`, null, { tags: { name: 'tope.nginx' }, responseCallback: aceptar }]));

        for (const r of rafaga) {
            if (r.status === 429 && !esDeLaApp(r)) {
                nginx429.add(1);
            } else if (r.status !== 429) {
                nginxPasaron.add(1);
            }
        }

        // Que el contador por minuto de la app no arrastre lo de la ráfaga.
        sleep(65);
    }

    for (let i = 0; i < PETICIONES_DE_LA_APP; i++) {
        const r = pedir('GET', `${BASE_URL}${RUTA_DE_TOPE}`, null, { tags: { name: 'tope.app' }, responseCallback: aceptar });

        if (r.status === 429 && esDeLaApp(r)) {
            app429.add(1);
        } else if (r.status !== 429) {
            appPasaron.add(1);
        }
        sleep(0.33);
    }
}

export const handleSummary = crearResumen('descargas', {
    tipo: 'operaciones pesadas y comprobación de topes',
    medida: 'PTb-3-G tiempo de ida y vuelta de una tarea; PRu-3-G uso medio de E/S',
    extra: (data) => {
        const c = (m) => (data.metrics[m] ? data.metrics[m].values.count : 0);

        return {
            topes: {
                nginx_zona_pesadas: HAY_NGINX ? { rafaga: RAFAGA, pasaron: c('topes_nginx_pasaron'), cortadas_con_429_de_nginx: c('topes_nginx_429'), esperado: 'unas 21 pasan (1 + ráfaga de 20), el resto 429' } : 'omitido (HAY_NGINX=no)',
                aplicacion_30_por_minuto: { peticiones: PETICIONES_DE_LA_APP, pasaron: c('topes_app_pasaron'), cortadas_con_429_de_la_aplicacion: c('topes_app_429'), esperado: 'pasan 30, desde la 31 llega 429' },
            },
            bytes_descargados: c('descargas_bytes'),
        };
    },
});
