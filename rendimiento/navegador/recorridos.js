/*
 * Recorridos de punta a punta, cronometrados paso a paso (k6 browser).
 *
 *   ISO/IEC 25023:2016  PTb-3-G tiempo medio de ida y vuelta de una tarea:
 *                       cada paso va del gesto de la persona (clic, selección,
 *                       escritura de la búsqueda) hasta que la interfaz muestra
 *                       el resultado; la suma de los pasos es el tiempo de la
 *                       tarea sin contar lo que la persona tarda en teclear.
 *   ISO/IEC 25010:2023  Eficiencia de desempeño: comportamiento temporal.
 *
 * El flujo es el real de CLAUDE.md §4 (regla 9), con cinco actores, cada uno
 * en su propio contexto de navegador y con su propia sesión forjada:
 *
 *   a-solicitar       docente        crea la solicitud (FormularioSolicitud)
 *   b-revisar         administrativo la acepta: «Marcar como revisada» (ExpedienteDeSolicitud::revisar)
 *   c-aprobar         coordinador    la aprueba (ExpedienteDeSolicitud::aprobar)
 *   d-asignar-sala    administrativo asigna sala en el tablero del día (TableroDiario::asignarSalas)
 *   e-resultados      estudiante     consulta sus resultados (MisResultados)
 *
 * CREA DATOS: una solicitud nueva, aprobada y con sala por cada iteración.
 * Solo se corre contra bases desechables.
 *
 * Variables (todas opcionales):
 *   BASE_URL       http://localhost
 *   SESIONES       JSON de crear-sesiones.php, relativo a este script
 *                  (../resultados/sesiones-navegador.json). Obligatorio en la práctica.
 *   PERFILES       escritorio,movil3g
 *   ITERACIONES    veces que se repite todo el recorrido (1)
 *   ESTUDIANTES    estudiantes que el docente agrega al grupo (2)
 *   BUSQUEDA       texto con el que busca estudiantes en el formulario («a»)
 *   MATERIA_ID     materia fija; sin ella prueba las de la lista hasta hallar una con escenarios
 *   CASO_ID        escenario fijo (de la materia elegida)
 *   SALIDA         carpeta de resultados relativa al directorio de trabajo (../resultados/navegador)
 *
 * Supuestos: la solicitud se programa en una fecha y hora al azar entre 20 y
 * 400 días adelante, para no chocar con las que ya hay (un docente no puede
 * tener dos solicitudes que se cruzan); si el sistema la rechaza por cruce se
 * reintenta con otra, hasta tres veces, y solo se cronometra el intento que
 * sirvió. El id de la solicitud se toma de «Mis solicitudes» (la de id mayor).
 *
 * Pasos que NO se cronometran, a propósito: teclear la fecha, la hora y el
 * grupo (no viajan al servidor hasta enviar) y marcar la casilla de la sala
 * (tampoco). Se llenan con fill(), que no es tiempo de la aplicación.
 */

import { browser } from 'k6/browser';
import { Counter, Trend } from 'k6/metrics';
import {
  BASE_URL,
  PERFILES,
  perfilesElegidos,
  leerSesiones,
  sesionDe,
  nuevoContexto,
  limitar,
} from './comun.js';

const REFERENCIA_PASO_MS = 2000;

const RECORRIDOS = {
  'a-solicitar': ['abrir_formulario', 'elegir_materia', 'elegir_caso', 'agregar_estudiante', 'enviar_solicitud', 'total'],
  'b-revisar': ['abrir_expediente', 'marcar_revisada', 'total'],
  'c-aprobar': ['abrir_expediente', 'aprobar', 'total'],
  'd-asignar-sala': ['abrir_tablero', 'abrir_panel_de_montaje', 'asignar_sala', 'total'],
  'e-resultados': ['abrir_mis_resultados', 'total'],
};

const perfiles = perfilesElegidos();
const sesiones = leerSesiones();
const paso = new Trend('rec_paso_ms');
const fallos = new Counter('rec_fallos');

export const options = {
  scenarios: {
    recorridos: {
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
 * Referencia del escritorio: p95 ≤ 2 s por paso, el mismo objetivo del RNF01
 * (150 personas, p95 ≤ 2 s). No hay objetivo acordado para 3G: ahí solo se
 * registra. Los demás submétricos llevan un umbral que siempre se cumple, que
 * es la forma de que k6 los entregue por separado a handleSummary.
 */
function umbrales() {
  const t = {};

  perfilesElegidos().forEach((perfil) => {
    Object.keys(RECORRIDOS).forEach((recorrido) => {
      RECORRIDOS[recorrido].forEach((nombre) => {
        const clave = `rec_paso_ms{recorrido:${recorrido},paso:${nombre},perfil:${perfil}}`;
        t[clave] = ['max>=0'];

        if (perfil === 'escritorio' && nombre !== 'total') {
          t[clave].push(`p(95)<=${REFERENCIA_PASO_MS}`);
        }
      });
    });
  });

  return t;
}

/* ---------- Utilidades de cronometraje ---------- */

async function cronometrar(recorrido, perfil, nombre, accion, acumulador) {
  const inicio = Date.now();
  await accion();
  const duracion = Date.now() - inicio;

  paso.add(duracion, { recorrido, paso: nombre, perfil });
  acumulador.total += duracion;
  console.log(`PASO ${recorrido}/${perfil}/${nombre} ${duracion} ms`);

  return duracion;
}

function cerrarRecorrido(recorrido, perfil, acumulador) {
  paso.add(acumulador.total, { recorrido, paso: 'total', perfil });
  console.log(`TOTAL ${recorrido}/${perfil} ${acumulador.total} ms`);
}

/** Espera a que el cuerpo de la página diga alguno de los textos; devuelve el que apareció. */
async function esperarAlgunTexto(page, textos, plazo) {
  await page.waitForFunction(
    (lista) => lista.some((t) => document.body.innerText.includes(t)),
    { polling: 'raf', timeout: plazo || 30000 },
    textos,
  );

  return page.evaluate((lista) => lista.find((t) => document.body.innerText.includes(t)) || null, textos);
}

/** Selector XPath de un botón por su texto: k6 no entiende :has-text. */
function boton(texto) {
  return `//button[contains(normalize-space(.), "${texto}")]`;
}

async function visible(page, selector, plazo) {
  await page.locator(selector).first().waitFor({ state: 'visible', timeout: plazo || 30000 });
}

async function abrir(page, ruta, selectorListo) {
  await page.goto(BASE_URL + ruta, { waitUntil: 'load', timeout: 120000 });
  await visible(page, selectorListo, 60000);
}

async function pulsar(perfil, page, selector) {
  const control = page.locator(selector).first();

  if (perfil === 'movil3g') {
    await control.tap();
  } else {
    await control.click();
  }
}

async function contextoDe(perfil, rol, vuelta) {
  const sesion = sesionDe(sesiones, rol, vuelta);

  if (!sesion) {
    throw new Error(`no hay sesión del rol ${rol} en SESIONES`);
  }

  const contexto = await nuevoContexto(browser, perfil, sesiones, sesion);
  const page = await contexto.newPage();
  await limitar(page, perfil);
  page.setDefaultTimeout(60000);

  return { contexto, page };
}

async function cerrar(actor) {
  await actor.page.close();
  await actor.contexto.close();
}

function aleatorio(minimo, maximo) {
  return minimo + Math.floor(Math.random() * (maximo - minimo + 1));
}

function dosDigitos(n) {
  return String(n).padStart(2, '0');
}

/** Fecha y horas al azar, lejos en el futuro, para no cruzarse con las sesiones que ya existen. */
function sesionAlAzar() {
  const dia = new Date(Date.now() + aleatorio(20, 400) * 86400000);
  const inicio = aleatorio(6, 19);
  const fecha = `${dia.getUTCFullYear()}-${dosDigitos(dia.getUTCMonth() + 1)}-${dosDigitos(dia.getUTCDate())}`;

  return { fecha, inicio: `${dosDigitos(inicio)}:00`, fin: `${dosDigitos(inicio + 1)}:00` };
}

/* ---------- Los cinco recorridos ---------- */

async function solicitar(perfil, vuelta) {
  const recorrido = 'a-solicitar';
  const acumulador = { total: 0 };
  const actor = await contextoDe(perfil, 'docente', vuelta);
  const { page } = actor;

  try {
    await cronometrar(recorrido, perfil, 'abrir_formulario', () => abrir(page, '/panel/solicitudes/nueva', '#materiaId'), acumulador);

    // Materia y escenario: el escenario depende de la materia, así que se
    // prueban las materias hasta que una ofrezca alguno.
    const materias = __ENV.MATERIA_ID
      ? [__ENV.MATERIA_ID]
      : await page.evaluate(() => Array.from(document.querySelectorAll('#materiaId option')).map((o) => o.value).filter((v) => v !== ''));
    let casoElegido = null;

    for (const materia of materias) {
      const inicio = Date.now();
      await page.locator('#materiaId').selectOption(String(materia));

      try {
        await page.waitForFunction(() => document.querySelectorAll('#casoClinicoId option').length > 1, { polling: 'raf', timeout: 6000 });
      } catch (e) {
        continue; // esa materia no tiene escenarios activos
      }

      const duracion = Date.now() - inicio;
      paso.add(duracion, { recorrido, paso: 'elegir_materia', perfil });
      acumulador.total += duracion;
      console.log(`PASO ${recorrido}/${perfil}/elegir_materia ${duracion} ms`);
      casoElegido = __ENV.CASO_ID || (await page.evaluate(() => document.querySelectorAll('#casoClinicoId option')[1].value));
      break;
    }

    if (casoElegido === null) {
      throw new Error('ninguna materia tiene escenarios para elegir');
    }

    // Al elegir el escenario se precargan equipos y se muestra la capacidad.
    await cronometrar(
      recorrido,
      perfil,
      'elegir_caso',
      async () => {
        await page.locator('#casoClinicoId').selectOption(String(casoElegido));
        await page.waitForFunction(
          () => document.body.innerText.includes('admite') || document.querySelectorAll('input[id^="cantidad-"]').length > 0 || document.body.innerText.includes('Elige un escenario para precargar') === false,
          { polling: 'raf', timeout: 30000 },
        );
      },
      acumulador,
    );

    // Estudiantes: se busca, aparece la lista y se agrega el primero.
    const cuantos = Number(__ENV.ESTUDIANTES || 2);

    for (let i = 1; i <= cuantos; i++) {
      await cronometrar(
        recorrido,
        perfil,
        'agregar_estudiante',
        async () => {
          await page.locator('#busquedaEstudiante').fill(__ENV.BUSQUEDA || 'a');
          await visible(page, '//li//button[contains(normalize-space(.), "Agregar")]');
          await pulsar(perfil, page, '//li//button[contains(normalize-space(.), "Agregar")]');
          await page.waitForFunction(
            (n) => document.body.innerText.includes(n + (n === 1 ? ' estudiante' : ' estudiantes')),
            { polling: 'raf', timeout: 30000 },
            i,
          );
        },
        acumulador,
      );
    }

    await page.locator('#grupo').fill('A');

    // Envío, con hasta tres fechas distintas si el sistema avisa de un cruce.
    let enviada = false;
    let fechaEnviada = null;

    for (let intento = 1; intento <= 3 && !enviada; intento++) {
      const sesion = sesionAlAzar();
      await page.locator('#fecha').fill(sesion.fecha);
      await page.locator('#horaInicio').fill(sesion.inicio);
      await page.locator('#horaFin').fill(sesion.fin);

      const inicio = Date.now();
      // Primero la respuesta de Livewire: así un aviso de error de un intento anterior no se confunde con el de este.
      await Promise.all([page.waitForResponse(/livewire.*\/update/, { timeout: 60000 }), pulsar(perfil, page, boton('Enviar solicitud'))]);
      // Éxito: el sistema lleva a «Mis solicitudes». Fallo: se queda en el formulario con el aviso de error.
      await page.waitForFunction(
        () => {
          return location.pathname === '/panel/mis-solicitudes' || document.body.innerText.includes('Revisa los campos marcados');
        },
        { polling: 'raf', timeout: 60000 },
      );
      const resultado = await page.evaluate(() => {
        return location.pathname === '/panel/mis-solicitudes' ? 'ok' : 'error';
      });
      const duracion = Date.now() - inicio;

      if (resultado === 'ok') {
        enviada = true;
        fechaEnviada = sesion.fecha;
        paso.add(duracion, { recorrido, paso: 'enviar_solicitud', perfil });
        acumulador.total += duracion;
        console.log(`PASO ${recorrido}/${perfil}/enviar_solicitud ${duracion} ms (intento ${intento})`);
      } else {
        console.warn(`Solicitud rechazada al enviar (intento ${intento}); se prueba otra fecha.`);
      }
    }

    if (!enviada) {
      throw new Error('el sistema no aceptó la solicitud en tres intentos');
    }

    // La solicitud nueva es la de id mayor en «Mis solicitudes», que va por fecha descendente.
    const id = await page.evaluate(() => {
      const ids = Array.from(document.querySelectorAll('a[href*="/panel/solicitudes/"]'))
        .map((a) => /\/panel\/solicitudes\/(\d+)/.exec(a.getAttribute('href')))
        .filter(Boolean)
        .map((c) => Number(c[1]));

      return ids.length ? Math.max.apply(null, ids) : null;
    });
    cerrarRecorrido(recorrido, perfil, acumulador);

    return { id, fecha: fechaEnviada };
  } finally {
    await cerrar(actor);
  }
}

async function transicion(recorrido, perfil, vuelta, rol, id, nombrePaso, etiquetaBoton, textoDeExito) {
  const acumulador = { total: 0 };
  const actor = await contextoDe(perfil, rol, vuelta);
  const { page } = actor;

  try {
    await cronometrar(recorrido, perfil, 'abrir_expediente', () => abrir(page, `/panel/solicitudes/${id}`, boton(etiquetaBoton)), acumulador);

    if (recorrido === 'c-aprobar') {
      // Si faltan unidades, el sistema exige decir cuáles: se llena siempre y no cuesta nada.
      await page.locator('#observaciones-aprobacion').fill('Aprobada en la prueba de rendimiento.');
    }

    await cronometrar(
      recorrido,
      perfil,
      nombrePaso,
      async () => {
        await pulsar(perfil, page, boton(etiquetaBoton));
        await esperarAlgunTexto(page, [textoDeExito], 60000);
      },
      acumulador,
    );
    cerrarRecorrido(recorrido, perfil, acumulador);
  } finally {
    await cerrar(actor);
  }
}

async function asignarSala(perfil, vuelta, fecha) {
  const recorrido = 'd-asignar-sala';
  const acumulador = { total: 0 };
  const actor = await contextoDe(perfil, 'administrativo', vuelta);
  const { page } = actor;

  try {
    await cronometrar(recorrido, perfil, 'abrir_tablero', () => abrir(page, `/panel/preparaciones?fecha=${fecha}`, boton('Preparar escenario')), acumulador);

    await cronometrar(
      recorrido,
      perfil,
      'abrir_panel_de_montaje',
      async () => {
        await pulsar(perfil, page, boton('Preparar escenario'));
        await visible(page, '//fieldset/legend[contains(normalize-space(.), "Sala")]');
      },
      acumulador,
    );

    // Se prueba una sala a la vez: si no cabe el grupo o se cruza, el sistema lo dice y se pasa a la siguiente.
    const salas = await page.locator('input[type="checkbox"][value]').count();
    let asignada = false;

    for (let i = 0; i < Math.min(salas, 5) && !asignada; i++) {
      const casilla = page.locator('input[type="checkbox"][value]').nth(i);
      await casilla.check();

      const inicio = Date.now();
      await Promise.all([page.waitForResponse(/livewire.*\/update/, { timeout: 60000 }), pulsar(perfil, page, boton('Asignar'))]);
      // Éxito: el aviso «Sala asignada». Fallo: el aviso rojo del panel de montaje (cruce, capacidad).
      await page.waitForFunction(
        () => {
          return /Salas? asignadas?/.test(document.body.innerText) || document.querySelector('.space-y-6 > [role="alert"]') !== null;
        },
        { polling: 'raf', timeout: 60000 },
      );
      const resultado = await page.evaluate(() => {
        return /Salas? asignadas?/.test(document.body.innerText) ? 'ok' : 'conflicto';
      });
      const duracion = Date.now() - inicio;

      if (resultado === 'ok') {
        asignada = true;
        paso.add(duracion, { recorrido, paso: 'asignar_sala', perfil });
        acumulador.total += duracion;
        console.log(`PASO ${recorrido}/${perfil}/asignar_sala ${duracion} ms (sala ${i + 1})`);
      } else {
        await casilla.uncheck();
      }
    }

    if (!asignada) {
      throw new Error('ninguna de las salas libres se pudo asignar');
    }

    cerrarRecorrido(recorrido, perfil, acumulador);
  } finally {
    await cerrar(actor);
  }
}

async function consultarResultados(perfil, vuelta) {
  const recorrido = 'e-resultados';
  const acumulador = { total: 0 };
  const actor = await contextoDe(perfil, 'estudiante', vuelta);

  try {
    await cronometrar(recorrido, perfil, 'abrir_mis_resultados', () => abrir(actor.page, '/panel/mis-resultados', 'main'), acumulador);
    cerrarRecorrido(recorrido, perfil, acumulador);
  } finally {
    await cerrar(actor);
  }
}

export default async function () {
  if (!sesiones) {
    throw new Error('Falta el JSON de sesiones (SESIONES). Genéralo con rendimiento/herramientas/crear-sesiones.php.');
  }

  for (const perfil of perfiles) {
    const etapa = { recorrido: 'a-solicitar' };

    try {
      const creada = await solicitar(perfil, __ITER);
      console.log(`Solicitud creada: id ${creada.id}, fecha ${creada.fecha}`);

      etapa.recorrido = 'b-revisar';
      await transicion('b-revisar', perfil, __ITER, 'administrativo', creada.id, 'marcar_revisada', 'Marcar como revisada', 'Solicitud marcada como revisada');

      etapa.recorrido = 'c-aprobar';
      await transicion('c-aprobar', perfil, __ITER, 'coordinador', creada.id, 'aprobar', 'Aprobar', 'Solicitud aprobada');

      etapa.recorrido = 'd-asignar-sala';
      await asignarSala(perfil, __ITER, creada.fecha);
    } catch (error) {
      fallos.add(1, { recorrido: etapa.recorrido, perfil });
      console.error(`FALLO ${etapa.recorrido}/${perfil}: ${error}`);
    }

    try {
      etapa.recorrido = 'e-resultados';
      await consultarResultados(perfil, __ITER);
    } catch (error) {
      fallos.add(1, { recorrido: 'e-resultados', perfil });
      console.error(`FALLO e-resultados/${perfil}: ${error}`);
    }
  }
}

/* ---------- Informe ---------- */

function formato(valor) {
  return valor === undefined || valor === null ? '—' : Number(valor).toFixed(0);
}

export function handleSummary(data) {
  const salida = __ENV.SALIDA || '../resultados/navegador';
  const archivos = {};
  const resumen = [];
  const lineas = ['Recorridos de punta a punta (ms, del gesto a la interfaz actualizada)', ''];

  perfilesElegidos().forEach((perfil) => {
    lineas.push(`Perfil ${perfil}: ${PERFILES[perfil].descripcion}`);

    Object.keys(RECORRIDOS).forEach((recorrido) => {
      const pasos = {};

      RECORRIDOS[recorrido].forEach((nombre) => {
        const entrada = data.metrics[`rec_paso_ms{recorrido:${recorrido},paso:${nombre},perfil:${perfil}}`];

        if (entrada && entrada.values && entrada.values.count > 0) {
          pasos[nombre] = {
            valores: entrada.values,
            umbrales: Object.keys(entrada.thresholds || {})
              .filter((r) => r !== 'max>=0')
              .map((r) => `${r}: ${entrada.thresholds[r].ok ? 'CUMPLE' : 'NO CUMPLE'}`),
          };
        }
      });

      if (Object.keys(pasos).length === 0) {
        return;
      }

      resumen.push({ recorrido, perfil, pasos });
      lineas.push(`  ${recorrido}`);
      Object.keys(pasos).forEach((nombre) => {
        const v = pasos[nombre].valores;
        const u = pasos[nombre].umbrales.length ? `   [${pasos[nombre].umbrales.join('; ')}]` : '';
        lineas.push(`    ${nombre.padEnd(24)} med ${formato(v.med)}  p95 ${formato(v['p(95)'])}  min ${formato(v.min)}  max ${formato(v.max)}  n=${v.count}${u}`);
      });
    });

    lineas.push('');
  });

  const fallosTotales = data.metrics.rec_fallos ? data.metrics.rec_fallos.values.count : 0;
  lineas.push(`Recorridos o tramos fallidos: ${fallosTotales}`);

  archivos[`${salida}/recorridos.json`] = JSON.stringify({ baseUrl: BASE_URL, generado: new Date().toISOString(), fallos: fallosTotales, resumen }, null, 2);
  archivos[`${salida}/recorridos.txt`] = `${lineas.join('\n')}\n`;
  archivos.stdout = `${lineas.join('\n')}\n`;

  return archivos;
}
