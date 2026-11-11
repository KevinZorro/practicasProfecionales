// Escrituras por Livewire para preparar y ejecutar las pruebas de
// concurrencia: crear una solicitud (docente), revisarla (administrativo) y
// aprobarla (coordinación). Hacen lo que hace el navegador: abrir la página,
// tomar el snapshot y mandar actualizaciones y llamadas a /livewire/update.
import { BASE_URL } from './config.js';
import { pedir } from './http.js';
import { abrirComponente, actualizar } from './livewire.js';

const FORMULARIO = 'solicitud.formulario-solicitud';
const EXPEDIENTE = 'solicitud.expediente-de-solicitud';

function opciones(html, id) {
    const bloque = (html.match(new RegExp(`<select[^>]*id="${id}"[\\s\\S]*?</select>`)) || [''])[0];

    return [...bloque.matchAll(/<option value="(\d+)">/g)].map((m) => Number(m[1]));
}

// Una materia con al menos un escenario clínico y los ids de estudiantes que
// se pueden agregar (se buscan con letras y dígitos hasta juntar `cuantos`).
// Lo ve y lo hace un docente, como en la pantalla real.
export function descubrirCatalogo(cuantos) {
    const { componente, token, html } = abrirComponente('/panel/solicitudes/nueva', FORMULARIO, 'setup.catalogo');

    if (!componente) {
        throw new Error('El docente no pudo abrir /panel/solicitudes/nueva: ¿sesión de docente válida?');
    }

    let materiaId = null;
    let casoClinicoId = null;
    let actual = componente;

    for (const candidata of opciones(html, 'materiaId')) {
        const r = actualizar(actual, token, { actualizaciones: { materiaId: candidata } }, 'setup.catalogo');
        const casos = opciones((r.efectos && r.efectos.html) || '', 'casoClinicoId');

        actual = r.componente;
        if (casos.length > 0) {
            materiaId = candidata;
            casoClinicoId = casos[0];
            break;
        }
    }

    const estudiantes = [];

    for (const aguja of ['a', 'e', 'o', 'i', 'u', 'n', 'r', 's', 'l', 'm', '1', '2', '3', '4', '5']) {
        if (estudiantes.length >= cuantos) {
            break;
        }
        const r = actualizar(actual, token, { actualizaciones: { busquedaEstudiante: aguja } }, 'setup.catalogo');
        const ids = [...((r.efectos && r.efectos.html) || '').matchAll(/agregarEstudiante\((\d+)\)/g)].map((m) => Number(m[1]));

        actual = r.componente;
        for (const id of ids) {
            if (!estudiantes.includes(id)) {
                estudiantes.push(id);
            }
        }
    }

    return { materiaId, casoClinicoId, estudiantes: estudiantes.slice(0, cuantos) };
}

// Fecha de hoy + días, YYYY-MM-DD.
export function fechaEn(dias) {
    return new Date(Date.now() + dias * 86400000).toISOString().slice(0, 10);
}

// Lo que se manda al guardar la solicitud: propiedades y la llamada.
export function cargaDeSolicitud(catalogo, { fecha, horaInicio = '07:00', horaFin = '09:00', grupo = 'A', estudianteIds }) {
    return {
        actualizaciones: {
            tipo: 'practica',
            materiaId: catalogo.materiaId,
            casoClinicoId: catalogo.casoClinicoId,
            fecha,
            horaInicio,
            horaFin,
            grupo,
            estudianteIds,
            observaciones: 'Prueba de rendimiento',
        },
        llamadas: [{ metodo: 'guardar' }],
    };
}

// El resultado de guardar: creada (redirige a mis solicitudes), rechazada por
// una regla de negocio (la pantalla enseña el error y se queda) o fallo.
export function resultadoDeGuardar(r) {
    if (r.respuesta.status >= 500) {
        return 'fallo';
    }
    if (r.respuesta.status === 422) {
        return 'rechazada';
    }

    return r.redireccion ? 'creada' : 'rechazada';
}

// Crea una solicitud como el docente en sesión y devuelve su id (el mayor de
// los suyos en "Mis solicitudes") o null con la razón.
export function crearSolicitud(catalogo, datos) {
    const { componente, token } = abrirComponente('/panel/solicitudes/nueva', FORMULARIO, 'setup.crear');

    if (!componente) {
        return { id: null, razon: 'no abrió el formulario' };
    }

    const r = actualizar(componente, token, cargaDeSolicitud(catalogo, datos), 'setup.crear');
    const resultado = resultadoDeGuardar(r);

    if (resultado !== 'creada') {
        const motivo = ((r.efectos && r.efectos.html) || '').match(/role="alert"[^>]*>\s*(?:<[^>]+>\s*)*([^<]{10,200})/);

        return { id: null, razon: `${resultado}: ${motivo ? motivo[1].trim() : `HTTP ${r.respuesta.status}`}` };
    }

    const lista = pedir('GET', `${BASE_URL}/panel/mis-solicitudes`, null, { tags: { name: 'setup.crear' } });
    const ids = [...(lista.body || '').matchAll(/\/panel\/solicitudes\/(\d+)(?![\d\/])/g)].map((m) => Number(m[1]));

    return { id: ids.length ? Math.max(...ids) : null, razon: ids.length ? '' : 'creada pero no aparece en Mis solicitudes' };
}

// Acciones del expediente, con el usuario en sesión.
export function abrirExpediente(solicitudId, etiqueta) {
    return abrirComponente(`/panel/solicitudes/${solicitudId}`, EXPEDIENTE, etiqueta);
}

export function revisar(solicitudId) {
    const { componente, token } = abrirExpediente(solicitudId, 'setup.revisar');

    if (!componente) {
        return false;
    }

    return actualizar(componente, token, { llamadas: [{ metodo: 'revisar' }] }, 'setup.revisar').respuesta.status === 200;
}

export function aprobar(solicitudId) {
    const { componente, token } = abrirExpediente(solicitudId, 'setup.aprobar');

    if (!componente) {
        return false;
    }

    return actualizar(componente, token, {
        actualizaciones: { observacionesAprobacion: 'Aprobada por la prueba de rendimiento' },
        llamadas: [{ metodo: 'aprobar' }],
    }, 'setup.aprobar').respuesta.status === 200;
}

// Los mensajes que la acción mandó al navegador (aviso-de-la-accion).
export function avisosDe(efectos) {
    const eventos = (efectos && efectos.dispatches) || [];

    return eventos.filter((e) => e.name === 'aviso-de-la-accion').map((e) => String((e.params && (e.params.mensaje || e.params[0])) || ''));
}
