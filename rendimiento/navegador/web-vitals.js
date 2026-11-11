/*
 * Rendimiento de punta a punta en el navegador (k6 browser).
 *
 * Mide, por página y perfil, en carga fría (contexto nuevo, caché vacía) y
 * en caliente (segunda visita en el mismo contexto): TTFB, FCP, LCP, CLS,
 * INP, bytes transferidos y número de peticiones.
 *
 *   ISO/IEC 25010:2023  Eficiencia de desempeño: comportamiento temporal y
 *                       utilización de recursos.
 *   ISO/IEC 25023:2016  PTb-1-G tiempo medio de respuesta (TTFB, FCP, LCP);
 *                       PTb-2-G adecuación del tiempo de respuesta
 *                       (medido / objetivo, en los umbrales);
 *                       PRu-4-S uso de ancho de banda (bytes transferidos).
 *   W3C                 Navigation Timing, Resource Timing, Paint Timing,
 *                       Largest Contentful Paint, Layout Instability,
 *                       Event Timing.
 *
 * Variables (todas opcionales):
 *   BASE_URL      http://localhost
 *   SESIONES      ruta del JSON de crear-sesiones.php, relativa a este script
 *                 (../resultados/sesiones-navegador.json). Sin él solo se
 *                 miden las páginas públicas.
 *   PERFILES      escritorio,movil3g
 *   PAGINAS       ids separados por coma (todas por defecto): portada,
 *                 escenario, hoy-estudiante, hoy-docente, hoy-administrativo,
 *                 calendario, bandeja, tablero
 *   ITERACIONES   repeticiones de toda la matriz (1). Cada una usa contextos
 *                 nuevos, así que cada vuelta es una carga fría de verdad.
 *   ESCENARIO_ID  id del escenario para /escenarios/{id}; si falta se busca
 *                 en los enlaces de la portada y, si no hay, se omite.
 *   FECHA_TABLERO fecha (AAAA-MM-DD) del tablero de preparaciones; hoy por defecto.
 *   SOLO_FRIA     1 para omitir la visita en caliente (comprobación de bytes
 *                 contra la métrica browser_data_received de k6).
 *   REPOSO_MS     espera tras la carga antes de leer (1000).
 *   SALIDA        carpeta de resultados dentro del contenedor/host, relativa
 *                 a este script (../resultados/navegador).
 *
 * Uso: ver README.md (comando docker exacto).
 */

import { browser } from 'k6/browser';
import http from 'k6/http';
import { Counter, Trend } from 'k6/metrics';
import {
  BASE_URL,
  PERFILES,
  perfilesElegidos,
  leerSesiones,
  sesionDe,
  nuevoContexto,
  limitar,
  instalarObservadores,
  leerMedicion,
  irA,
  asentar,
} from './comun.js';

/*
 * Umbrales. Su fuente y su justificación están en el README.
 *  - RNF10 (docs/escenarios-de-calidad.md): primera carga del panel ≤ 300 KB
 *    comprimidos = 307.200 bytes.
 *  - Core Web Vitals «bueno» (web.dev, percentil 75): LCP ≤ 2,5 s,
 *    INP ≤ 200 ms, CLS ≤ 0,1; referencias FCP ≤ 1,8 s y TTFB ≤ 0,8 s.
 *  - 3G: LCP ≤ 4 s (por encima, web.dev lo llama «malo»).
 */
const RNF10_BYTES = 300 * 1024;
const LCP_BUENO_MS = 2500;
const LCP_3G_MS = 4000;
const INP_BUENO_MS = 200;
const CLS_BUENO = 0.1;
const FCP_BUENO_MS = 1800;
const TTFB_BUENO_MS = 800;

/*
 * Cada página: ruta, rol de la sesión (null = sin sesión), si es del panel
 * (le aplica RNF10) y los controles con los que provocar una interacción
 * para el INP. Se prueban en orden y se pulsa el primero visible; solo se
 * eligen controles que no navegan, para no cambiar de documento a medias.
 */
const PAGINAS = [
  { id: 'portada', ruta: () => '/', rol: null, panel: false, controles: ['a[href^="#"]', 'main'] },
  { id: 'escenario', ruta: (d) => (d.escenarioId ? `/escenarios/${d.escenarioId}` : null), rol: null, panel: false, controles: ['a[href^="#"]', 'main'] },
  { id: 'hoy-estudiante', ruta: () => '/panel', rol: 'estudiante', panel: true, controles: ['button[aria-label="Abrir el menú"]', 'main'] },
  { id: 'hoy-docente', ruta: () => '/panel', rol: 'docente', panel: true, controles: ['button[aria-label="Abrir el menú"]', 'main'] },
  { id: 'hoy-administrativo', ruta: () => '/panel', rol: 'administrativo', panel: true, controles: ['button[aria-label="Abrir el menú"]', 'main'] },
  { id: 'calendario', ruta: () => '/panel/calendario', rol: 'administrativo', panel: true, controles: ['.fc-next-button'] },
  { id: 'bandeja', ruta: () => '/panel/solicitudes', rol: 'administrativo', panel: true, controles: ['label:has(input[name="estado"][value="pendiente"]) span'] },
  { id: 'tablero', ruta: () => `/panel/preparaciones?fecha=${fechaDelTablero()}`, rol: 'administrativo', panel: true, controles: ['//button[normalize-space(.)="Hoy"]'] },
];

function fechaDelTablero() {
  return __ENV.FECHA_TABLERO || new Date().toISOString().slice(0, 10);
}

const perfiles = perfilesElegidos();
const idsPedidos = (__ENV.PAGINAS || '').split(',').map((p) => p.trim()).filter(Boolean);
const paginas = PAGINAS.filter((p) => idsPedidos.length === 0 || idsPedidos.includes(p.id));
const sesiones = leerSesiones();
const VISITAS = ['fria', 'caliente'];

const m = {
  ttfb: new Trend('wv_ttfb_ms'),
  fcp: new Trend('wv_fcp_ms'),
  lcp: new Trend('wv_lcp_ms'),
  cls: new Trend('wv_cls'),
  inp: new Trend('wv_inp_ms'),
  bytes: new Trend('wv_bytes_transferidos'),
  codificados: new Trend('wv_bytes_cuerpo_codificado'),
  descomprimidos: new Trend('wv_bytes_descomprimidos'),
  peticiones: new Trend('wv_peticiones'),
  enCache: new Trend('wv_peticiones_en_cache'),
  bytesTotal: new Counter('wv_bytes_transferidos_suma'),
  fallidas: new Counter('wv_mediciones_fallidas'),
};

export const options = {
  scenarios: {
    web_vitals: {
      // Un solo usuario virtual y en serie: dos navegadores a la vez se
      // robarían CPU y falsearían los tiempos de ambos.
      executor: 'per-vu-iterations',
      vus: 1,
      iterations: Number(__ENV.ITERACIONES || 1),
      maxDuration: '2h',
      options: { browser: { type: 'chromium' } },
    },
  },
  summaryTrendStats: ['min', 'avg', 'med', 'p(75)', 'p(90)', 'p(95)', 'max', 'count'],
  thresholds: umbrales(),
};

/*
 * Un submétrico por página, perfil y visita. Los que no llevan umbral
 * propio reciben uno que siempre se cumple: es la forma de que k6 los
 * entregue por separado en handleSummary.
 */
function umbrales() {
  const t = {};
  const sel = (metrica, p, perfil, visita) => `${metrica}{pagina:${p.id},perfil:${perfil},visita:${visita}}`;
  const registrar = (clave, reglas) => {
    t[clave] = (t[clave] || []).concat(reglas);
  };

  perfilesElegidos().forEach((perfil) => {
    PAGINAS.forEach((p) => {
      VISITAS.forEach((visita) => {
        ['wv_ttfb_ms', 'wv_fcp_ms', 'wv_lcp_ms', 'wv_cls', 'wv_inp_ms', 'wv_bytes_transferidos', 'wv_bytes_cuerpo_codificado', 'wv_bytes_descomprimidos', 'wv_peticiones', 'wv_peticiones_en_cache'].forEach(
          (metrica) => registrar(sel(metrica, p, perfil, visita), ['max>=0']),
        );
      });

      const fria = 'fria';
      if (perfil === 'escritorio') {
        registrar(sel('wv_lcp_ms', p, perfil, fria), [`p(75)<=${LCP_BUENO_MS}`]);
        registrar(sel('wv_cls', p, perfil, fria), [`p(75)<=${CLS_BUENO}`]);
        registrar(sel('wv_inp_ms', p, perfil, fria), [`p(75)<=${INP_BUENO_MS}`]);
        registrar(sel('wv_fcp_ms', p, perfil, fria), [`p(75)<=${FCP_BUENO_MS}`]);
        registrar(sel('wv_ttfb_ms', p, perfil, fria), [`p(75)<=${TTFB_BUENO_MS}`]);
      } else {
        registrar(sel('wv_lcp_ms', p, perfil, fria), [`p(75)<=${LCP_3G_MS}`]);
      }

      if (p.panel) {
        registrar(sel('wv_bytes_transferidos', p, perfil, fria), [`max<=${RNF10_BYTES}`]);
      }
    });
  });

  return t;
}

export function setup() {
  let escenarioId = __ENV.ESCENARIO_ID || null;

  if (!escenarioId && paginas.some((p) => p.id === 'escenario')) {
    const portada = http.get(`${BASE_URL}/`);
    const coincidencia = /\/escenarios\/(\d+)/.exec(portada.body || '');
    escenarioId = coincidencia ? coincidencia[1] : null;
  }

  return { escenarioId };
}

async function medirPagina(datos, pagina, perfil, vuelta) {
  const ruta = pagina.ruta(datos);

  if (ruta === null) {
    console.warn(`OMITIDA ${pagina.id}: no hay escenario publicado (usa ESCENARIO_ID).`);

    return;
  }

  const sesion = pagina.rol ? sesionDe(sesiones, pagina.rol, vuelta) : null;

  if (pagina.rol && !sesion) {
    console.warn(`OMITIDA ${pagina.id}: no hay sesión del rol ${pagina.rol} en SESIONES.`);

    return;
  }

  const contexto = await nuevoContexto(browser, perfil, sesiones, sesion);
  const page = await contexto.newPage();

  try {
    await limitar(page, perfil);
    await contexto.addInitScript(`(${instalarObservadores.toString()})()`);

    for (const visita of VISITAS) {
      const etiquetas = { pagina: pagina.id, perfil, visita };
      const llegada = await irA(page, ruta, etiquetas);

      if (!llegada.correcta) {
        m.fallidas.add(1, etiquetas);
        console.error(`FALLO ${pagina.id}/${perfil}/${visita}: estado ${llegada.estado}, terminó en ${llegada.rutaFinal}`);
        break;
      }

      await asentar(page, Number(__ENV.REPOSO_MS || 1000));
      const lectura = JSON.parse(await page.evaluate(leerMedicion));

      // El INP solo en la visita fría: es la que vive quien llega por primera vez.
      if (visita === 'fria') {
        const interaccion = await provocarInteraccion(page, pagina.controles, perfil);
        lectura.control = interaccion === null ? null : interaccion.control;
        lectura.inpEsCota = interaccion === null ? null : interaccion.cota;
        lectura.inp = interaccion === null ? null : interaccion.duracion;
      }

      registrar(etiquetas, lectura);

      if (Number(__ENV.SOLO_FRIA || 0) === 1) {
        break;
      }
    }
  } catch (error) {
    m.fallidas.add(1, { pagina: pagina.id, perfil, visita: 'fria' });
    console.error(`ERROR ${pagina.id}/${perfil}: ${error}`);
  } finally {
    await page.close();
    await contexto.close();
  }
}

/*
 * Pulsa el primer control visible y lee la duración de la interacción
 * (Event Timing: del gesto del usuario al siguiente pintado). Si no hay
 * ninguno, devuelve null y el INP de esa página queda sin dato, no en cero.
 */
async function provocarInteraccion(page, controles, perfil) {
  for (const selector of controles) {
    // Se recorren las coincidencias hasta dar con una visible: el primer
    // enlace «#» de la portada, por ejemplo, es el de saltar al contenido.
    let control = null;
    try {
      const cuantos = Math.min(await page.locator(selector).count(), 12);

      for (let i = 0; i < cuantos && control === null; i++) {
        const candidato = page.locator(selector).nth(i);

        if (await candidato.isVisible()) {
          control = candidato;
        }
      }
    } catch (e) {
      control = null;
    }

    if (control === null) {
      continue;
    }

    try {
      // Plazo corto: si el control no se deja pulsar, es un dato (sin INP), no un fallo de la página.
      if (perfil === 'movil3g') {
        await control.tap({ timeout: 5000 });
      } else {
        await control.click({ timeout: 5000 });
      }
    } catch (e) {
      console.warn(`Sin INP: no se pudo pulsar ${selector}: ${e}`);
      continue;
    }

    await page.waitForTimeout(800);
    const despues = JSON.parse(await page.evaluate(leerMedicion));

    // Event Timing no entrega eventos de menos de 16 ms: sin entrada, la
    // interacción tardó eso o menos, y 16 es la cota superior que se registra.
    return { duracion: despues.inp === null ? 16 : despues.inp, cota: despues.inp === null, control: selector };
  }

  return null;
}

function registrar(etiquetas, l) {
  const poner = (metrica, valor) => {
    if (valor !== null && valor !== undefined) {
      metrica.add(valor, etiquetas);
    }
  };

  poner(m.ttfb, l.ttfb);
  poner(m.fcp, l.fcp);
  poner(m.lcp, l.lcp);
  poner(m.cls, l.cls);
  poner(m.inp, l.inp);
  poner(m.bytes, l.transferido);
  poner(m.codificados, l.codificado);
  poner(m.descomprimidos, l.descomprimido);
  poner(m.peticiones, l.peticiones);
  poner(m.enCache, l.enCache);
  m.bytesTotal.add(l.transferido, etiquetas);

  // Una línea por medición, con el detalle que no cabe en una métrica
  // (qué elemento fue el LCP, los recursos más pesados).
  console.log(`MEDICION ${JSON.stringify(Object.assign({ pagina: etiquetas.pagina, perfil: etiquetas.perfil, visita: etiquetas.visita }, l))}`);
}

export default async function (datos) {
  const vuelta = __ITER;

  for (const perfil of perfiles) {
    for (const pagina of paginas) {
      await medirPagina(datos, pagina, perfil, vuelta);
    }
  }
}

/* ---------- Informe: JSON y texto por página y perfil, más el consolidado ---------- */

const METRICAS = [
  ['wv_ttfb_ms', 'TTFB (ms)', 0],
  ['wv_fcp_ms', 'FCP (ms)', 0],
  ['wv_lcp_ms', 'LCP (ms)', 0],
  ['wv_cls', 'CLS', 3],
  ['wv_inp_ms', 'INP (ms)', 0],
  ['wv_bytes_transferidos', 'Bytes transferidos', 0],
  ['wv_bytes_cuerpo_codificado', 'Cuerpos comprimidos', 0],
  ['wv_bytes_descomprimidos', 'Cuerpos descomprimidos', 0],
  ['wv_peticiones', 'Peticiones', 0],
  ['wv_peticiones_en_cache', 'Peticiones servidas de caché', 0],
];

function valoresDe(data, metrica, pagina, perfil, visita) {
  const clave = `${metrica}{pagina:${pagina},perfil:${perfil},visita:${visita}}`;
  const entrada = data.metrics[clave];

  return entrada && entrada.values && entrada.values.count > 0 ? entrada : null;
}

function formato(valor, decimales) {
  return valor === undefined || valor === null ? '—' : Number(valor).toFixed(decimales);
}

function veredictos(entrada) {
  if (!entrada || !entrada.thresholds) {
    return [];
  }

  return Object.keys(entrada.thresholds)
    .filter((regla) => regla !== 'max>=0')
    .map((regla) => `${regla}: ${entrada.thresholds[regla].ok ? 'CUMPLE' : 'NO CUMPLE'}`);
}

export function handleSummary(data) {
  const salida = __ENV.SALIDA || '../resultados/navegador';
  const archivos = {};
  const filas = [];

  perfilesElegidos().forEach((perfil) => {
    PAGINAS.forEach((p) => {
      const porVisita = {};
      let hayDatos = false;

      VISITAS.forEach((visita) => {
        porVisita[visita] = {};
        METRICAS.forEach(([metrica]) => {
          const entrada = valoresDe(data, metrica, p.id, perfil, visita);

          if (entrada) {
            hayDatos = true;
            porVisita[visita][metrica] = { valores: entrada.values, umbrales: veredictos(entrada) };
          }
        });
      });

      if (!hayDatos) {
        return;
      }

      const base = `${salida}/${p.id}__${perfil}`;
      archivos[`${base}.json`] = JSON.stringify(
        { pagina: p.id, perfil, descripcionDelPerfil: PERFILES[perfil].descripcion, baseUrl: BASE_URL, visitas: porVisita },
        null,
        2,
      );

      const lineas = [`Página ${p.id} · perfil ${perfil} (${PERFILES[perfil].descripcion}) · ${BASE_URL}`, ''];
      VISITAS.forEach((visita) => {
        lineas.push(`  Visita ${visita}`);
        METRICAS.forEach(([metrica, etiqueta, decimales]) => {
          const v = porVisita[visita][metrica];

          if (!v) {
            return;
          }

          const est = v.valores;
          const verdictos = v.umbrales.length ? `   [${v.umbrales.join('; ')}]` : '';
          lineas.push(
            `    ${etiqueta.padEnd(30)} med ${formato(est.med, decimales)}  p75 ${formato(est['p(75)'], decimales)}  min ${formato(est.min, decimales)}  max ${formato(est.max, decimales)}  n=${est.count}${verdictos}`,
          );
        });
      });
      archivos[`${base}.txt`] = `${lineas.join('\n')}\n`;

      const fria = (metrica) => valoresDe(data, metrica, p.id, perfil, 'fria');
      const caliente = (metrica) => valoresDe(data, metrica, p.id, perfil, 'caliente');
      const med = (e) => (e ? e.values.med : null);
      filas.push({
        pagina: p.id,
        perfil,
        ttfb: med(fria('wv_ttfb_ms')),
        fcp: med(fria('wv_fcp_ms')),
        lcp: med(fria('wv_lcp_ms')),
        cls: med(fria('wv_cls')),
        inp: med(fria('wv_inp_ms')),
        bytesFria: med(fria('wv_bytes_transferidos')),
        peticionesFria: med(fria('wv_peticiones')),
        bytesCaliente: med(caliente('wv_bytes_transferidos')),
        lcpCaliente: med(caliente('wv_lcp_ms')),
      });
    });
  });

  const recibidos = data.metrics.browser_data_received ? data.metrics.browser_data_received.values.count : null;
  const sumaMedida = data.metrics.wv_bytes_transferidos_suma ? data.metrics.wv_bytes_transferidos_suma.values.count : null;

  const tabla = [
    'Resumen (medianas de la visita fría; bytes = Resource Timing transferSize)',
    '',
    `${'página'.padEnd(20)}${'perfil'.padEnd(12)}${'TTFB'.padStart(7)}${'FCP'.padStart(7)}${'LCP'.padStart(7)}${'CLS'.padStart(7)}${'INP'.padStart(6)}${'KB fría'.padStart(10)}${'peticiones'.padStart(12)}${'KB caliente'.padStart(13)}${'LCP cal.'.padStart(10)}`,
  ];
  filas.forEach((f) => {
    tabla.push(
      `${f.pagina.padEnd(20)}${f.perfil.padEnd(12)}${formato(f.ttfb, 0).padStart(7)}${formato(f.fcp, 0).padStart(7)}${formato(f.lcp, 0).padStart(7)}${formato(f.cls, 3).padStart(7)}${formato(f.inp, 0).padStart(6)}${formato(f.bytesFria === null ? null : f.bytesFria / 1024, 1).padStart(10)}${formato(f.peticionesFria, 0).padStart(12)}${formato(f.bytesCaliente === null ? null : f.bytesCaliente / 1024, 1).padStart(13)}${formato(f.lcpCaliente, 0).padStart(10)}`,
    );
  });
  tabla.push('');
  tabla.push(`Comprobación cruzada de bytes: k6 browser_data_received = ${recibidos === null ? '—' : recibidos} B; suma de transferSize de todas las visitas = ${sumaMedida === null ? '—' : sumaMedida} B.`);
  tabla.push('k6 cuenta los cuerpos YA DESCOMPRIMIDOS más las cabeceras, así que su cifra sobrepasa lo que viajó por la red cuando el servidor comprime; la cifra de la prueba es transferSize (ver README).');

  archivos[`${salida}/resumen.json`] = JSON.stringify(
    { baseUrl: BASE_URL, generado: new Date().toISOString(), filas, bytesK6: recibidos, bytesResourceTiming: sumaMedida, umbrales: Object.keys(data.metrics).filter((k) => data.metrics[k].thresholds && k.indexOf('{') > -1 && Object.keys(data.metrics[k].thresholds).some((r) => r !== 'max>=0')).reduce((acc, k) => { acc[k] = Object.keys(data.metrics[k].thresholds).filter((r) => r !== 'max>=0').map((r) => ({ regla: r, ok: data.metrics[k].thresholds[r].ok })); return acc; }, {}) },
    null,
    2,
  );
  archivos[`${salida}/resumen.txt`] = `${tabla.join('\n')}\n`;
  archivos.stdout = `${tabla.join('\n')}\n`;

  return archivos;
}
