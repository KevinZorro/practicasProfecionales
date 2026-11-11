// CONCURRENCIA DE ESCRITURAS (ISTQB: concurrency test; ISO/IEC 25023 PTb-3-G
// tiempo de ida y vuelta de una tarea; vigila la corrección de CLAUDE.md
// regla 9 y B3.11: los lockForUpdate).
//
// N personas hacen la MISMA escritura a la vez, por Livewire, arrancando
// juntas: cada usuario virtual abre su página (toma su snapshot), espera en
// un punto de encuentro común y manda su POST a /livewire/update en el mismo
// instante. Tres escenarios, uno tras otro:
//
//  1. solicitudes: N docentes crean una solicitud para los MISMOS estudiantes
//     y la misma franja (FormularioSolicitud::guardar). El bloqueo de
//     personas (CrucesDeSesionService) las serializa: solo una se crea; las
//     demás se rechazan por cruce. Umbral: a lo sumo 1 creada.
//  2. salas: N administrativos asignan la MISMA sala a preparaciones de la
//     misma franja (TableroDiario::asignarSalas). Con PREPARACIONES >= 2 solo
//     una puede quedarse con la sala (las otras reciben SalaOcupada); con 1
//     la acción es idempotente. Umbral: a lo sumo 1 preparación con la sala.
//  3. aprobacion: N de coordinación aprueban la MISMA solicitud
//     (ExpedienteDeSolicitud::aprobar, que relee con lockForUpdate). Gana una;
//     las demás reciben «ya había cambiado de estado». Umbral: exactamente 1.
//
// Estado previo, preparado por setup() con las mismas pantallas que usa una
// persona: un catálogo (materia, escenario, estudiantes) leído del
// formulario; para salas y aprobacion, solicitudes creadas por docentes,
// revisadas por un administrativo y aprobadas por coordinación (de ahí nacen
// las preparaciones). Usa fechas lejanas al azar para no cruzarse con otras
// corridas. Necesita al menos una sesión de docente, administrativo y
// coordinador, y estudiantes activos. Escribe en la base: solo en una
// desechable, como crear-sesiones.php.
//
// Un 422 de una regla de negocio es aceptable y se cuenta aparte
// (rechazos_422); lo que no se acepta es un 5xx. Latencia bajo contención:
// http_req_duration{name:concurrencia.<escenario>.accion}.
//
// Variables: N (10 usuarios por escenario), ESCENARIOS ("solicitudes,salas,aprobacion"),
// PREPARACIONES (2), ESPERA (10s: tiempo para que todos abran su página antes del
// disparo), BASE_URL, SESIONES, IP_POR_USUARIO.
import { sleep } from 'k6';
import exec from 'k6/execution';
import { Counter, Gauge } from 'k6/metrics';
import { LIMITE_P95_MS, aMilisegundos, entero, texto } from './lib/config.js';
import { http } from './lib/http.js';
import { abrirComponente, actualizar } from './lib/livewire.js';
import { BASE_URL } from './lib/config.js';
import { pedir } from './lib/http.js';
import { crearResumen } from './lib/resumen.js';
import { ESTADISTICAS, umbralesBase, umbralesPorEtiqueta, unir } from './lib/umbrales.js';
import { cuantasHay, entrar } from './lib/sesiones.js';
import {
    abrirExpediente, aprobar, avisosDe, cargaDeSolicitud, crearSolicitud, descubrirCatalogo, fechaEn, resultadoDeGuardar, revisar,
} from './lib/escrituras.js';
import { registrar } from './lib/verificar.js';

const N = entero('N', 10);
const PREPARACIONES = entero('PREPARACIONES', 2);
const ESPERA_MS = aMilisegundos(texto('ESPERA', '10s'));
const ESCENARIOS = texto('ESCENARIOS', 'solicitudes,salas,aprobacion').split(',').map((e) => e.trim());
const DURACION_ESCENARIO_S = Math.ceil(ESPERA_MS / 1000) + 60;

const ganadas = new Counter('escrituras_ganadas');
const rechazadas = new Counter('escrituras_rechazadas');
const conLaSala = new Gauge('preparaciones_con_la_sala');

const NOMBRES = ESCENARIOS.map((e) => `concurrencia.${e}.accion`);

const umbrales = unir(
    umbralesBase(),
    umbralesPorEtiqueta('name', NOMBRES, null),
    { respuestas_5xx: ['count==0'], sesiones_invalidas: ['count==0'], tasa_error: ['rate==0'] },
    { escrituras_ganadas: ['count>=0'], escrituras_rechazadas: ['count>=0'] },
);

for (const e of ESCENARIOS) {
    // Sin un umbral que la nombre, k6 no guarda la métrica por escenario y el resumen no la vería.
    umbrales[`escrituras_rechazadas{escenario:${e}}`] = ['count>=0'];
    umbrales[`escrituras_ganadas{escenario:${e}}`] = ['count>=0'];
    umbrales[`http_req_duration{name:concurrencia.${e}.accion}`] = [`p(95)<${LIMITE_P95_MS}`];
}
if (ESCENARIOS.includes('solicitudes')) {
    umbrales['escrituras_ganadas{escenario:solicitudes}'] = ['count<=1'];
}
if (ESCENARIOS.includes('salas')) {
    // Varias personas pueden repetir la misma asignación (idempotente) y todas «ganan»:
    // lo que no puede pasar es que dos preparaciones se queden con la misma sala.
    // Se comprueba en teardown(), leyendo el tablero.
    umbrales.preparaciones_con_la_sala = ['value<=1'];
}
if (ESCENARIOS.includes('aprobacion')) {
    umbrales['escrituras_ganadas{escenario:aprobacion}'] = ['count==1'];
}

const escenarios = {};
ESCENARIOS.forEach((e, i) => {
    escenarios[e] = {
        executor: 'per-vu-iterations',
        vus: N,
        iterations: 1,
        maxDuration: `${DURACION_ESCENARIO_S}s`,
        startTime: `${i * (DURACION_ESCENARIO_S + 5)}s`,
        exec: e,
    };
});

export const options = {
    scenarios: escenarios,
    thresholds: umbrales,
    summaryTrendStats: ESTADISTICAS,
    noCookiesReset: true,
    setupTimeout: '10m',
};

function fechaLibre() {
    return fechaEn(40 + Math.floor(Math.random() * 300));
}

// ---------------------------------------------------------------- preparación

export function setup() {
    const necesarios = 1 + PREPARACIONES + 1 + 4;

    entrar('docente', 0);
    const catalogo = descubrirCatalogo(necesarios + 5);
    const datos = { catalogo, solicitudes: null, salas: null, aprobacion: null, advertencias: [] };

    if (!catalogo.materiaId || catalogo.estudiantes.length < necesarios) {
        throw new Error(`Sin catálogo suficiente (materia ${catalogo.materiaId}, ${catalogo.estudiantes.length} estudiantes de ${necesarios}).`);
    }

    const estudiantes = catalogo.estudiantes;

    if (ESCENARIOS.includes('solicitudes')) {
        datos.solicitudes = { fecha: fechaLibre(), estudiantes: [estudiantes[0]] };
    }

    if (ESCENARIOS.includes('salas')) {
        datos.salas = prepararSalas(catalogo, estudiantes.slice(1, 1 + PREPARACIONES), datos.advertencias);
    }

    if (ESCENARIOS.includes('aprobacion')) {
        datos.aprobacion = prepararAprobacion(catalogo, estudiantes[estudiantes.length - 1], datos.advertencias);
    }

    console.log(`Preparación lista: ${JSON.stringify({ solicitudes: datos.solicitudes, salas: datos.salas, aprobacion: datos.aprobacion, advertencias: datos.advertencias })}`);

    return datos;
}

// Crea una solicitud como el docente `ordinal`, con reintentos con otra fecha.
function solicitudDe(catalogo, ordinal, estudianteIds, fechas) {
    for (let intento = 0; intento < 5; intento++) {
        entrar('docente', ordinal);
        const fecha = fechas.fecha || fechaLibre();
        const r = crearSolicitud(catalogo, { fecha, horaInicio: '07:00', horaFin: '09:00', grupo: 'A', estudianteIds });

        if (r.id) {
            fechas.fecha = fecha;

            return r.id;
        }
        console.warn(`No se pudo crear la solicitud de preparación (docente ${ordinal}): ${r.razon}`);
        fechas.fecha = null;
    }

    return null;
}

function prepararAprobacion(catalogo, estudianteId, advertencias) {
    const fechas = { fecha: null };
    const id = solicitudDe(catalogo, 0, [estudianteId], fechas);

    if (!id) {
        advertencias.push('aprobacion: no se pudo crear la solicitud');

        return null;
    }

    entrar('administrativo', 0);
    if (!revisar(id)) {
        advertencias.push('aprobacion: no se pudo revisar');

        return null;
    }

    return { solicitudId: id, fecha: fechas.fecha };
}

function prepararSalas(catalogo, estudiantes, advertencias) {
    const fecha = fechaLibre();
    const preparaciones = [];
    const docentes = Math.min(PREPARACIONES, cuantasHay('docente'));

    if (docentes < PREPARACIONES) {
        advertencias.push(`salas: hay ${docentes} docentes para ${PREPARACIONES} preparaciones; se usan ${docentes}`);
    }

    for (let i = 0; i < docentes; i++) {
        const id = solicitudDe(catalogo, i, [estudiantes[i]], { fecha });

        if (!id) {
            continue;
        }
        entrar('administrativo', 0);
        revisar(id);
        entrar('coordinador', 0);
        aprobar(id);
    }

    entrar('administrativo', 0);
    const { componente, token, html } = abrirComponente(`/panel/preparaciones?fecha=${fecha}`, 'preparacion.tablero-diario', 'setup.tablero');

    if (!componente) {
        advertencias.push('salas: el administrativo no pudo abrir el tablero');

        return null;
    }

    for (const m of html.matchAll(/abrir\((\d+)\)/g)) {
        if (!preparaciones.includes(Number(m[1]))) {
            preparaciones.push(Number(m[1]));
        }
    }

    // La sala: la primera que ofrece el panel de montaje de la primera preparación.
    const abierta = actualizar(componente, token, { llamadas: [{ metodo: 'abrir', parametros: [preparaciones[0]] }] }, 'setup.tablero');
    const sala = (((abierta.efectos && abierta.efectos.html) || '').match(/<input type="checkbox" value="(\d+)" wire:model="salasElegidas"/) || [])[1];

    if (preparaciones.length === 0 || !sala) {
        advertencias.push(`salas: ${preparaciones.length} preparaciones y sala ${sala || 'ninguna'}: no se puede ejecutar`);

        return null;
    }

    return { fecha, preparaciones, salaId: Number(sala) };
}

export function teardown(datos) {
    if (!datos.salas) {
        return;
    }

    entrar('administrativo', 0);
    const { html } = abrirComponente(`/panel/preparaciones?fecha=${datos.salas.fecha}`, 'preparacion.tablero-diario', 'teardown.tablero');
    const sinSala = (html.match(/Sin asignar/g) || []).length;
    const total = new Set([...html.matchAll(/abrir\((\d+)\)/g)].map((m) => m[1])).size;

    conLaSala.add(total - sinSala);
    console.log(`Preparaciones con la sala al terminar: ${total - sinSala} de ${total}`);
}

// -------------------------------------------------------------------- disparo

// Punto de encuentro: todos esperan al mismo instante absoluto, contado
// desde el arranque de su escenario, y disparan.
function esperarElDisparo() {
    const instante = exec.scenario.startTime + ESPERA_MS;

    sleep(Math.max(0, (instante - Date.now()) / 1000));
}

// Estados de negocio aceptables al perder una carrera: 422 (DomainException,
// QA-04) y 403 (la Policy ya ve el estado nuevo: aprobar una solicitud que otra
// persona acaba de aprobar deja de estar permitido).
const DE_NEGOCIO = [403, 422];

function disparar(nombre, componente, token, carga) {
    esperarElDisparo();

    return actualizar(componente, token, carga, nombre, { responseCallback: http.expectedStatuses(200, ...DE_NEGOCIO) });
}

function contar(escenario, gano, resultado, nombre) {
    registrar(resultado.respuesta, nombre, { aceptar: DE_NEGOCIO });

    if (resultado.respuesta.status >= 500) {
        return;
    }

    (gano ? ganadas : rechazadas).add(1, { escenario });
}

// ---------------------------------------------------------------- escenarios

export function solicitudes(datos) {
    const nombre = 'concurrencia.solicitudes.accion';

    entrar('docente', __VU - 1);

    const { componente, token } = abrirComponente('/panel/solicitudes/nueva', 'solicitud.formulario-solicitud', 'concurrencia.solicitudes.abrir');

    if (!componente) {
        registrar(pedir('GET', `${BASE_URL}/panel/solicitudes/nueva`, null, { tags: { name: nombre } }), nombre);

        return;
    }

    const r = disparar(nombre, componente, token,
        cargaDeSolicitud(datos.catalogo, { fecha: datos.solicitudes.fecha, estudianteIds: datos.solicitudes.estudiantes }));

    contar('solicitudes', resultadoDeGuardar(r) === 'creada', r, nombre);
}

export function salas(datos) {
    const nombre = 'concurrencia.salas.accion';

    if (!datos.salas) {
        console.error('salas: sin preparación previa, el escenario no corre');

        return;
    }

    entrar('administrativo', __VU - 1);

    const { componente, token } = abrirComponente(`/panel/preparaciones?fecha=${datos.salas.fecha}`, 'preparacion.tablero-diario', 'concurrencia.salas.abrir');

    if (!componente) {
        return;
    }

    const preparacionId = datos.salas.preparaciones[(__VU - 1) % datos.salas.preparaciones.length];
    const r = disparar(nombre, componente, token, {
        actualizaciones: { salasElegidas: [datos.salas.salaId] },
        llamadas: [{ metodo: 'asignarSalas', parametros: [preparacionId] }],
    });

    // Un conflicto queda en la propiedad conflictos del componente; sin él, la sala se asignó (o ya la tenía).
    const conflictos = r.componente.datos && r.componente.datos.conflictos;
    const hayConflicto = Array.isArray(conflictos) ? Object.keys(conflictos[0] || {}).length > 0 : false;

    contar('salas', !hayConflicto, r, nombre);
}

export function aprobacion(datos) {
    const nombre = 'concurrencia.aprobacion.accion';

    if (!datos.aprobacion) {
        console.error('aprobacion: sin solicitud previa, el escenario no corre');

        return;
    }

    entrar('coordinador', __VU - 1);

    const { componente, token } = abrirExpediente(datos.aprobacion.solicitudId, 'concurrencia.aprobacion.abrir');

    if (!componente) {
        return;
    }

    const r = disparar(nombre, componente, token, {
        actualizaciones: { observacionesAprobacion: 'Aprobada por la prueba de concurrencia' },
        llamadas: [{ metodo: 'aprobar' }],
    });
    const avisos = avisosDe(r.efectos).join(' ');

    contar('aprobacion', /aprobada\. El docente/i.test(avisos), r, nombre);
}

export const handleSummary = crearResumen('concurrencia', {
    tipo: 'concurrencia de escrituras',
    medida: 'PTb-3-G tiempo de ida y vuelta de una tarea (bajo contención)',
    extra: (data) => {
        const cuenta = (metrica, escenario) => {
            const m = data.metrics[`${metrica}{escenario:${escenario}}`];

            return m ? m.values.count : 0;
        };

        return Object.fromEntries(ESCENARIOS.map((e) => [e, {
            usuarios_simultaneos: N,
            ganaron: cuenta('escrituras_ganadas', e),
            rechazadas_por_regla_de_negocio: cuenta('escrituras_rechazadas', e),
            esperado: e === 'aprobacion' ? 'exactamente 1 ganó' : 'a lo sumo 1 ganó',
        }]));
    },
});
