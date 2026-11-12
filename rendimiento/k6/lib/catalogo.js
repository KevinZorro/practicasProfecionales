// Catálogo de pantallas por rol. Sale de routes/web.php y de
// app/Support/MenuDelPanel.php: una pantalla está aquí para un rol solo si
// su Policy se lo deja ver (comprobado contra el servidor de desarrollo).
//
// `nombre` es la etiqueta estable (tag `name`) con la que k6 agrupa las
// métricas: una pantalla, aunque su URL lleve ids o fechas.

const P = (nombre, ruta) => ({ nombre, ruta });

export const PANTALLAS = {
    inicio: P('panel.inicio', '/panel'),
    calendario: P('panel.calendario', '/panel/calendario'),
    misSolicitudes: P('panel.mis-solicitudes', '/panel/mis-solicitudes'),
    solicitudNueva: P('panel.solicitudes.nueva', '/panel/solicitudes/nueva'),
    solicitudes: P('panel.solicitudes', '/panel/solicitudes'),
    sesionesApartadas: P('panel.sesiones-apartadas', '/panel/sesiones-apartadas'),
    evaluaciones: P('panel.evaluaciones', '/panel/evaluaciones'),
    misResultados: P('panel.mis-resultados', '/panel/mis-resultados'),
    supervision: P('panel.supervision', '/panel/supervision'),
    bitacora: P('panel.bitacora', '/panel/bitacora'),
    depuracion: P('panel.depuracion', '/panel/depuracion'),
    bloqueos: P('panel.bloqueos', '/panel/bloqueos'),
    preparaciones: P('panel.preparaciones', '/panel/preparaciones'),
    usuarios: P('panel.usuarios', '/panel/usuarios'),
    reposicion: P('panel.reposicion', '/panel/reposicion'),
    reportes: P('panel.reportes', '/panel/reportes'),
    inventario: P('panel.inventario', '/panel/inventario'),
    disponibilidad: P('panel.inventario.disponibilidad', '/panel/inventario/disponibilidad'),
    miFormato: P('panel.mi-formato', '/panel/mi-formato'),
    formatos: P('panel.formatos-confidencialidad', '/panel/formatos-confidencialidad'),
    estadoFormatos: P('panel.formatos-confidencialidad.estado', '/panel/formatos-confidencialidad/estado'),
    plantillas: P('panel.plantillas-confidencialidad', '/panel/plantillas-confidencialidad'),
    periodo: P('panel.periodo-academico', '/panel/periodo-academico'),
    administracion: P('admin.dashboard', '/admin'),
};

const COMUNES_DE_STAFF = ['inicio', 'calendario', 'solicitudes', 'sesionesApartadas', 'reposicion', 'inventario',
    'disponibilidad', 'formatos', 'estadoFormatos', 'periodo'];

export const POR_ROL = {
    estudiante: ['inicio', 'calendario', 'misResultados', 'miFormato'],
    docente: ['inicio', 'calendario', 'misSolicitudes', 'solicitudNueva', 'evaluaciones', 'miFormato'],
    administrativo: [...COMUNES_DE_STAFF, 'preparaciones'],
    coordinador: [...COMUNES_DE_STAFF, 'preparaciones', 'evaluaciones', 'bitacora', 'bloqueos', 'reportes'],
    admin: ['inicio', 'calendario', 'solicitudes', 'evaluaciones', 'bitacora', 'depuracion', 'bloqueos', 'usuarios',
        'reposicion', 'reportes', 'inventario', 'disponibilidad', 'formatos', 'estadoFormatos', 'plantillas',
        'periodo', 'administracion'],
    supervisor: ['inicio', 'supervision'],
};

export const ROLES = Object.keys(POR_ROL);

// Quién ve el calendario y, por tanto, pide /panel/calendario/eventos.
const CON_CALENDARIO = ['estudiante', 'docente', 'administrativo', 'coordinador', 'admin'];

export function pantallasDe(rol) {
    return (POR_ROL[rol] || []).map((clave) => PANTALLAS[clave]);
}

export function nombresDePantallas() {
    return Object.values(PANTALLAS).map((p) => p.nombre);
}

export function tieneCalendario(rol) {
    return CON_CALENDARIO.includes(rol);
}

function fecha(d) {
    return d.toISOString().slice(0, 10);
}

// Lo que pide FullCalendar (resources/js/calendario.js): desde y hasta como
// YYYY-MM-DD. Semana: lunes a lunes. Mes: la vista mensual, que incluye los
// días de las semanas vecinas (hasta seis semanas, tope de 62 días del servidor).
export function eventosDelCalendario(modo = 'mes', ahora = new Date()) {
    const hoy = new Date(Date.UTC(ahora.getUTCFullYear(), ahora.getUTCMonth(), ahora.getUTCDate()));
    const diaDeSemana = (hoy.getUTCDay() + 6) % 7;
    let desde;
    let hasta;

    if (modo === 'semana') {
        desde = new Date(hoy.getTime() - diaDeSemana * 86400000);
        hasta = new Date(desde.getTime() + 7 * 86400000);
    } else {
        const primero = new Date(Date.UTC(hoy.getUTCFullYear(), hoy.getUTCMonth(), 1));
        const ultimo = new Date(Date.UTC(hoy.getUTCFullYear(), hoy.getUTCMonth() + 1, 1));
        desde = new Date(primero.getTime() - ((primero.getUTCDay() + 6) % 7) * 86400000);
        hasta = new Date(ultimo.getTime() + ((7 - ((ultimo.getUTCDay() + 6) % 7)) % 7) * 86400000);
    }

    return {
        nombre: `panel.calendario.eventos.${modo}`,
        ruta: `/panel/calendario/eventos?desde=${fecha(desde)}&hasta=${fecha(hasta)}`,
        json: true,
    };
}

export function nombresDeEventos() {
    return ['panel.calendario.eventos.semana', 'panel.calendario.eventos.mes'];
}

export const ACTIVOS = ['activo.css', 'activo.js', 'activo.livewire', 'activo.otro'];

// Todas las etiquetas name que el catálogo puede producir, más las que un
// escenario agregue. Sirve para declarar sus umbrales por pantalla.
export function nombresDeTodo(extras = []) {
    return [...nombresDePantallas(), ...nombresDeEventos(), ...ACTIVOS, ...extras];
}

export const SOLICITUD_VER = { nombre: 'panel.solicitudes.ver' };
