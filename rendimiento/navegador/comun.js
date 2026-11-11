/*
 * Piezas compartidas por web-vitals.js y recorridos.js (k6 browser).
 *
 * Aquí viven los perfiles de dispositivo y red, la lectura de las sesiones
 * forjadas por rendimiento/herramientas/crear-sesiones.php y las dos
 * funciones que se ejecutan DENTRO de la página: una instala los
 * observadores de rendimiento antes de que cargue nada, la otra los lee.
 */

import { check } from 'k6';

export const BASE_URL = (__ENV.BASE_URL || 'http://localhost').replace(/\/+$/, '');

/*
 * Perfiles. Cada valor lleva su fuente porque el informe tiene que poder
 * defenderlo; si se cambia uno, se cambia también el README.
 *
 *  - escritorio: sin limitar red ni CPU, ventana de portátil habitual.
 *  - movil3g: «celular 3G gama baja».
 *      * Red: perfil «3G» de WebPageTest (connectivity profiles): 1,6 Mbps
 *        de bajada, 768 Kbps de subida y 300 ms de RTT. Chromium recibe
 *        bytes por segundo: 1.600.000 / 8 y 768.000 / 8.
 *      * CPU: 4x más lenta, el factor de «mobile» en Lighthouse
 *        (cpuSlowdownMultiplier = 4, referencia: un Moto G4).
 *      * Pantalla: 412 x 823 a 1,75 de densidad, la emulación móvil de
 *        Lighthouse (Moto G Power).
 */
export const PERFILES = {
  escritorio: {
    descripcion: 'Escritorio sin limitar (1366x768)',
    contexto: {
      viewport: { width: 1366, height: 768 },
      deviceScaleFactor: 1,
      locale: 'es-CO',
      timezoneID: 'America/Bogota',
    },
    red: null,
    cpu: 1,
  },
  movil3g: {
    descripcion: 'Celular 3G gama baja (412x823 @1,75; 3G de WebPageTest; CPU 4x)',
    contexto: {
      viewport: { width: 412, height: 823 },
      deviceScaleFactor: 1.75,
      isMobile: true,
      hasTouch: true,
      locale: 'es-CO',
      timezoneID: 'America/Bogota',
      userAgent:
        'Mozilla/5.0 (Linux; Android 11; moto g power (2022)) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
    },
    red: { latency: 300, download: 1600000 / 8, upload: 768000 / 8 },
    cpu: 4,
  },
};

export function perfilesElegidos() {
  const pedidos = (__ENV.PERFILES || 'escritorio,movil3g').split(',').map((p) => p.trim()).filter(Boolean);
  const desconocidos = pedidos.filter((p) => !(p in PERFILES));

  if (desconocidos.length > 0) {
    throw new Error(`Perfiles desconocidos: ${desconocidos.join(', ')}. Válidos: ${Object.keys(PERFILES).join(', ')}.`);
  }

  return pedidos;
}

/** Lee el JSON de crear-sesiones.php; sin archivo solo se miden las páginas públicas. */
export function leerSesiones() {
  const ruta = __ENV.SESIONES || '../resultados/sesiones-navegador.json';

  try {
    return JSON.parse(open(ruta));
  } catch (e) {
    return null;
  }
}

export function sesionDe(sesiones, rol, indice) {
  if (!sesiones) {
    return null;
  }

  const delRol = sesiones.sesiones.filter((s) => s.rol === rol);

  return delRol.length === 0 ? null : delRol[indice % delRol.length];
}

/** Contexto nuevo (caché y cookies vacías) con el perfil y, si hay, la sesión puesta. */
export async function nuevoContexto(browser, perfil, sesiones, sesion) {
  const contexto = await browser.newContext(PERFILES[perfil].contexto);

  if (sesion) {
    await contexto.addCookies([
      {
        name: sesiones.cookie,
        value: sesion.valor,
        domain: BASE_URL.replace(/^https?:\/\//, '').split(/[:/]/)[0],
        path: '/',
        httpOnly: true,
        sameSite: 'Lax',
      },
    ]);
  }

  return contexto;
}

/** Aplica la limitación de red y CPU del perfil a una página. */
export async function limitar(page, perfil) {
  const { red, cpu } = PERFILES[perfil];

  if (red) {
    await page.throttleNetwork(red);
  }

  if (cpu > 1) {
    await page.throttleCPU({ rate: cpu });
  }
}

/*
 * Se ejecuta en cada documento nuevo, antes de su primer script. Los
 * observadores con `buffered` no bastan para los eventos: el búfer del
 * navegador solo guarda los de 104 ms o más, y un INP bueno es justo lo que
 * está por debajo.
 */
function instalarObservadores() {
  const m = { lcp: null, cls: [], eventos: [] };
  window.__medicion = m;

  const observar = (tipo, alVer, extra) => {
    try {
      new PerformanceObserver((lista) => lista.getEntries().forEach(alVer)).observe(
        Object.assign({ type: tipo, buffered: true }, extra || {}),
      );
    } catch (e) {
      /* el navegador no conoce este tipo de entrada */
    }
  };

  observar('largest-contentful-paint', (e) => {
    m.lcp = {
      t: e.startTime,
      bytes: e.size,
      elemento: e.element ? e.element.tagName.toLowerCase() + (e.element.id ? '#' + e.element.id : '') : null,
      url: e.url || null,
    };
  });
  observar('layout-shift', (e) => {
    if (!e.hadRecentInput) {
      m.cls.push([e.startTime, e.value]);
    }
  });
  observar('event', (e) => {
    if (e.interactionId) {
      m.eventos.push([e.interactionId, e.duration, e.name]);
    }
  }, { durationThreshold: 16 });
}

/*
 * Lee lo acumulado y devuelve texto JSON (evaluate no siempre devuelve
 * objetos anidados tal cual). Los bytes salen de Resource Timing:
 *   transferSize        = lo que viajó por la red (cuerpo comprimido + cabeceras);
 *                         0 si salió de la caché.
 *   encodedBodySize     = cuerpo comprimido, sin cabeceras.
 *   decodedBodySize     = cuerpo ya descomprimido.
 */
function leerMedicion() {
  const m = window.__medicion || { lcp: null, cls: [], eventos: [] };
  const nav = performance.getEntriesByType('navigation')[0];
  const recursos = performance.getEntriesByType('resource');
  const todo = [nav].concat(recursos);
  const fcp = performance.getEntriesByName('first-contentful-paint')[0];

  // CLS: la ventana de sesión más mala (huecos de 1 s, tope de 5 s), como
  // la define web.dev.
  let cls = 0;
  let ventana = 0;
  let inicio = 0;
  let ultimo = 0;
  m.cls.forEach((par) => {
    if (ventana > 0 && (par[0] - ultimo > 1000 || par[0] - inicio > 5000)) {
      ventana = 0;
    }
    if (ventana === 0) {
      inicio = par[0];
    }
    ventana += par[1];
    ultimo = par[0];
    cls = Math.max(cls, ventana);
  });

  // INP: con menos de 50 interacciones es la más lenta.
  const porInteraccion = {};
  m.eventos.forEach((e) => {
    porInteraccion[e[0]] = Math.max(porInteraccion[e[0]] || 0, e[1]);
  });
  const duraciones = Object.keys(porInteraccion).map((k) => porInteraccion[k]);
  const inp = duraciones.length ? Math.max.apply(null, duraciones) : null;

  const suma = (campo) => todo.reduce((acumulado, e) => acumulado + (e[campo] || 0), 0);
  const pesados = recursos
    .map((r) => ({
      nombre: r.name.replace(location.origin, ''),
      tipo: r.initiatorType,
      transferido: r.transferSize,
      codificado: r.encodedBodySize,
      descomprimido: r.decodedBodySize,
    }))
    .sort((a, b) => b.transferido - a.transferido)
    .slice(0, 6);

  return JSON.stringify({
    url: location.pathname + location.search,
    ttfb: nav ? nav.responseStart : null,
    fcp: fcp ? fcp.startTime : null,
    lcp: m.lcp ? m.lcp.t : null,
    lcpElemento: m.lcp ? m.lcp.elemento : null,
    lcpUrl: m.lcp ? m.lcp.url : null,
    cls,
    inp,
    dcl: nav ? nav.domContentLoadedEventEnd : null,
    carga: nav ? nav.loadEventEnd : null,
    transferido: suma('transferSize'),
    codificado: suma('encodedBodySize'),
    descomprimido: suma('decodedBodySize'),
    documento: nav ? { transferido: nav.transferSize, codificado: nav.encodedBodySize, descomprimido: nav.decodedBodySize } : null,
    peticiones: todo.length,
    enCache: todo.filter((e) => e.transferSize === 0 && e.decodedBodySize > 0).length,
    pesados,
  });
}

/** Va a la URL, espera a que la red quede quieta y comprueba que no lo echaron a otra pantalla. */
export async function irA(page, ruta, etiquetas) {
  const respuesta = await page.goto(BASE_URL + ruta, { waitUntil: 'networkidle', timeout: 120000 });
  const estado = respuesta ? respuesta.status() : 0;
  const rutaFinal = page.url().replace(/^https?:\/\/[^/]+/, '').split(/[?#]/)[0];
  const esperada = ruta.split('?')[0];

  check(
    { estado, rutaFinal },
    {
      'la página responde 200': (r) => r.estado === 200,
      'sin redirección a otra pantalla': (r) => r.rutaFinal === esperada,
    },
    etiquetas,
  );

  return { estado, rutaFinal, correcta: estado === 200 && rutaFinal === esperada };
}

/** Un reposo corto para que Livewire, Alpine y las fuentes terminen de pintar. */
export async function asentar(page, milisegundos) {
  await page.waitForTimeout(milisegundos === undefined ? 1000 : milisegundos);
}

// Sin `export` delante: k6 convierte estas dos funciones en texto para
// ejecutarlas dentro de la página, y el texto no puede empezar por «export».
export { instalarObservadores, leerMedicion };
