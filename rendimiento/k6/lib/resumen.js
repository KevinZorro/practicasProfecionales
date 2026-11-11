// handleSummary común: un JSON y un texto por corrida en
// rendimiento/resultados/k6/<escenario>-<fecha>.json|txt, con p50/p90/p95/p99/máx
// por pantalla (tag name), tasa de error, rps, Apdex (T = 0,5 s), el modo de IP
// y qué umbrales se cumplieron.
import { APDEX_T_MS, BASE_URL, IP_POR_USUARIO, RESULTADOS } from './config.js';
import { INFORMATIVO } from './umbrales.js';

function valores(data, metrica) {
    const m = data.metrics[metrica];

    return m ? m.values : null;
}

function latencia(v) {
    if (!v) {
        return null;
    }

    return {
        n: v.count === undefined ? null : v.count,
        promedio: v.avg,
        min: v.min,
        p50: v.med,
        p90: v['p(90)'],
        p95: v['p(95)'],
        p99: v['p(99)'],
        max: v.max,
    };
}

function apdex(data, sufijo) {
    const total = (valores(data, `apdex_total${sufijo}`) || {}).count || 0;
    const satisfechas = (valores(data, `apdex_satisfechas${sufijo}`) || {}).count || 0;
    const toleradas = (valores(data, `apdex_toleradas${sufijo}`) || {}).count || 0;

    return total === 0 ? null : (satisfechas + toleradas / 2) / total;
}

// Agrupa por una dimensión de etiquetas: { valor: {n, p50.., apdex, errores} }.
function porDimension(data, dimension) {
    const patron = new RegExp(`^http_req_duration\\{${dimension}:(.+)\\}$`);
    const grupos = {};

    for (const metrica of Object.keys(data.metrics)) {
        const m = metrica.match(patron);
        if (!m) {
            continue;
        }
        const sufijo = `{${dimension}:${m[1]}}`;
        const duracion = latencia(valores(data, metrica));
        const peticiones = valores(data, `http_reqs${sufijo}`);
        const fallidas = valores(data, `http_req_failed${sufijo}`);

        if (duracion && peticiones) {
            duracion.n = peticiones.count;
        }
        if (!duracion || !duracion.n) {
            continue;
        }

        const cuenta = (nombre) => (valores(data, `${nombre}${sufijo}`) || {}).count || 0;

        grupos[m[1]] = Object.assign(duracion, {
            respuestas_429: cuenta('respuestas_429'),
            respuestas_5xx: cuenta('respuestas_5xx'),
            respuestas_502_504: cuenta('respuestas_502_504'),
            apdex: apdex(data, sufijo),
            tasa_fallos_http: fallidas ? fallidas.rate : null,
        });
    }

    return grupos;
}

function umbrales(data) {
    const lista = [];

    for (const [metrica, definicion] of Object.entries(data.metrics)) {
        for (const [expresion, estado] of Object.entries(definicion.thresholds || {})) {
            if (expresion === INFORMATIVO || /^(count|rate)>=0$/.test(expresion)) {
                continue;
            }
            lista.push({ metrica, umbral: expresion, cumplido: estado.ok === true });
        }
    }

    return lista;
}

function redondear(valor, decimales = 1) {
    if (valor === null || valor === undefined || Number.isNaN(valor)) {
        return '-';
    }
    const f = Math.pow(10, decimales);

    return String(Math.round(valor * f) / f);
}

function tabla(titulo, grupos) {
    const nombres = Object.keys(grupos).sort();
    if (nombres.length === 0) {
        return '';
    }
    const ancho = Math.min(58, Math.max(...nombres.map((n) => n.length), 8));
    const col = (t, n = 8) => String(t).padStart(n);
    let texto = `\n${titulo}\n`;

    texto += `${'nombre'.padEnd(ancho)} ${col('n', 7)}${col('p50')}${col('p90')}${col('p95')}${col('p99')}${col('máx')}${col('apdex', 7)}${col('fallos%', 8)}${col('429', 6)}${col('5xx', 6)}${col('502/4', 6)}\n`;
    for (const nombre of nombres) {
        const g = grupos[nombre];
        texto += `${nombre.slice(0, ancho).padEnd(ancho)} ${col(g.n, 7)}${col(redondear(g.p50))}${col(redondear(g.p90))}${col(redondear(g.p95))}`
            + `${col(redondear(g.p99))}${col(redondear(g.max))}${col(redondear(g.apdex, 3), 7)}${col(redondear(g.tasa_fallos_http === null ? null : g.tasa_fallos_http * 100, 2), 8)}${col(g.respuestas_429, 6)}${col(g.respuestas_5xx, 6)}${col(g.respuestas_502_504, 6)}\n`;
    }

    return texto;
}

// `escenario`: nombre del archivo y del informe. `extra(data)` (opcional) agrega
// al informe lo propio del escenario (por ejemplo, en qué tasa empezó a degradar).
export function crearResumen(escenario, { tipo = '', medida = '', extra = null } = {}) {
    return function handleSummary(data) {
        const duracionMs = (data.state && data.state.testRunDurationMs) || 0;
        const global = latencia(valores(data, 'http_req_duration'));
        const peticiones = valores(data, 'http_reqs') || { count: 0, rate: 0 };
        const contar = (m) => (valores(data, m) || {}).count || 0;
        const lista = umbrales(data);

        const informe = {
            escenario,
            tipo_de_prueba: tipo,
            medida_iso_25023: medida,
            fecha: new Date().toISOString(),
            base_url: BASE_URL,
            ip_por_usuario: IP_POR_USUARIO ? 'si' : 'no',
            duracion_s: Math.round(duracionMs / 100) / 10,
            totales: {
                peticiones: peticiones.count,
                rps: peticiones.rate,
                tasa_de_error: (valores(data, 'tasa_error') || {}).rate,
                respuestas_429: contar('respuestas_429'),
                respuestas_5xx: contar('respuestas_5xx'),
                respuestas_502_504: contar('respuestas_502_504'),
                rechazos_422: contar('rechazos_422'),
                sesiones_invalidas: contar('sesiones_invalidas'),
                apdex_t_ms: APDEX_T_MS,
                apdex: apdex(data, ''),
                latencia_ms: global,
                tamano_cuerpo_bytes: latencia(valores(data, 'tamano_cuerpo_bytes')),
            },
            por_pantalla: porDimension(data, 'name'),
            por_escalon: porDimension(data, 'escalon'),
            por_ventana: porDimension(data, 'ventana'),
            por_fase: porDimension(data, 'fase'),
            umbrales: lista,
            todos_los_umbrales_cumplidos: lista.every((u) => u.cumplido),
        };

        if (extra) {
            informe.analisis = extra(data, informe);
        }

        const t = informe.totales;
        let texto = `Escenario: ${escenario}  (${tipo}; ${medida})\n`;
        texto += `Fecha: ${informe.fecha}  Base: ${BASE_URL}  Duración: ${informe.duracion_s} s\n`;
        texto += `IP por usuario: ${informe.ip_por_usuario}`
            + `${IP_POR_USUARIO ? '' : '  (una sola IP: reproduce producción detrás del proxy sin IP real, B3.8)'}\n`;
        texto += `Peticiones: ${t.peticiones}  rps: ${redondear(t.rps, 2)}  error: ${redondear(t.tasa_de_error === undefined ? null : t.tasa_de_error * 100, 2)} %  `
            + `429: ${t.respuestas_429}  5xx: ${t.respuestas_5xx}  422: ${t.rechazos_422}  sesión inválida: ${t.sesiones_invalidas}\n`;
        texto += `Apdex (T=${APDEX_T_MS} ms): ${redondear(t.apdex, 3)}\n`;
        if (global) {
            texto += `Latencia global ms: p50 ${redondear(global.p50)}  p90 ${redondear(global.p90)}  p95 ${redondear(global.p95)}  p99 ${redondear(global.p99)}  máx ${redondear(global.max)}\n`;
        }
        texto += tabla('Por pantalla (ms)', informe.por_pantalla);
        texto += tabla('Por escalón (ms)', informe.por_escalon);
        texto += tabla('Por ventana de tiempo (ms)', informe.por_ventana);
        texto += tabla('Por fase (ms)', informe.por_fase);

        texto += '\nUmbrales\n';
        // Los de cada pantalla, escalón o ventana se resumen: solo se nombran los que fallan.
        const porEtiqueta = lista.filter((u) => u.metrica.includes('{'));
        const generales = lista.filter((u) => !u.metrica.includes('{'));
        texto += generales.map((u) => `  ${u.cumplido ? 'CUMPLE   ' : 'NO CUMPLE'} ${u.metrica} ${u.umbral}`).join('\n') + (generales.length ? '\n' : '');
        texto += porEtiqueta.filter((u) => !u.cumplido).map((u) => `  NO CUMPLE ${u.metrica} ${u.umbral}`).join('\n') + '\n';
        texto += `  (${porEtiqueta.filter((u) => u.cumplido).length} de ${porEtiqueta.length} umbrales por pantalla/escalón/ventana cumplidos)\n`;
        texto += `Veredicto: ${informe.todos_los_umbrales_cumplidos ? 'PASA' : 'NO PASA'}\n`;

        if (informe.analisis) {
            texto += `\nAnálisis\n${JSON.stringify(informe.analisis, null, 2)}\n`;
        }

        const sello = informe.fecha.replace(/[:.]/g, '-');

        return {
            [`${RESULTADOS}/${escenario}-${sello}.json`]: JSON.stringify(informe, null, 2),
            [`${RESULTADOS}/${escenario}-${sello}.txt`]: texto,
            stdout: texto,
        };
    };
}
