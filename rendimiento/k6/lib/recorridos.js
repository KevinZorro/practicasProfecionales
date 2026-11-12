// Recorridos de una persona en el panel, compartidos por las pruebas de
// carga, resistencia, capacidad, estrés y pico. Qué hace cada rol sale de lo
// que el MenuDelPanel le muestra; los tiempos de reflexión (3 a 10 s) son
// los de una persona que lee la pantalla antes de pasar a la siguiente.
import exec from 'k6/execution';
import { PANTALLAS, eventosDelCalendario } from './catalogo.js';
import { pensar, visitar } from './navegacion.js';
import { ROLES_CON_SESION, entrar } from './sesiones.js';

// Mezcla de la hora pico (peso relativo de cada rol). Los roles sin sesión
// en el archivo se descartan al usarla (mezclaDisponible).
export const MEZCLA = { estudiante: 55, docente: 28, administrativo: 8, coordinador: 5, admin: 4 };

// Los 150 del RNF01: sobre todo docentes y estudiantes.
export const MEZCLA_RNF01 = { estudiante: 60, docente: 30, administrativo: 6, coordinador: 3, admin: 1 };

export function mezclaDisponible(mezcla = MEZCLA) {
    const disponible = {};

    for (const [rol, peso] of Object.entries(mezcla)) {
        if (ROLES_CON_SESION.includes(rol)) {
            disponible[rol] = peso;
        }
    }

    return disponible;
}

// Qué mira cada rol en su hora pico, en orden. 'calendario' pide también sus eventos.
const FLUJOS = {
    estudiante: ['misResultados', 'miFormato', 'calendario'],
    docente: ['misSolicitudes', 'calendario', 'evaluaciones'],
    administrativo: ['preparaciones', 'inventario', 'disponibilidad', 'solicitudes'],
    coordinador: ['solicitudes', 'reportes', 'bitacora'],
    admin: ['usuarios'],
};

// La pantalla principal de cada rol, la que abre justo después del panel.
export const PRINCIPAL = {
    estudiante: 'misResultados', docente: 'misSolicitudes', administrativo: 'preparaciones',
    coordinador: 'solicitudes', admin: 'usuarios', supervisor: 'supervision',
};

export function flujoDe(rol) {
    return FLUJOS[rol] || ['inicio'];
}

export function rolAleatorio(mezcla) {
    const roles = Object.keys(mezcla);
    let punto = Math.random() * roles.reduce((s, r) => s + mezcla[r], 0);

    for (const rol of roles) {
        punto -= mezcla[rol];
        if (punto < 0) {
            return rol;
        }
    }

    return roles[0];
}

// Una pasada completa del flujo del rol, con reflexión entre pantallas. Es
// el recorrido de la carga mixta, la resistencia y el pico.
export function recorridoDelRol(rol, { activos = true, reflexion = [3, 10] } = {}) {
    for (const clave of flujoDe(rol)) {
        visitar(PANTALLAS[clave], { activos });

        if (clave === 'calendario') {
            visitar(eventosDelCalendario('mes'));
        }

        pensar(reflexion[0], reflexion[1]);
    }
}

// Una sola petición representativa, sin reflexión: para las pruebas de tasa
// de llegada, donde lo que se controla es cuántas peticiones por segundo
// entran y no cuántas personas hay.
export function peticionRealista(mezcla) {
    const rol = rolAleatorio(mezcla);
    entrar(rol, Math.floor(Math.random() * 100000));

    const flujo = flujoDe(rol);
    const clave = flujo[Math.floor(Math.random() * flujo.length)];

    visitar(PANTALLAS[clave]);

    if (clave === 'calendario') {
        visitar(eventosDelCalendario(Math.random() < 0.5 ? 'semana' : 'mes'));
    }
}

// Etiqueta todas las métricas siguientes de este usuario virtual (escalon,
// ventana, fase). k6 solo reporta por etiqueta lo que un umbral nombra.
export function etiquetar(clave, valor) {
    exec.vu.metrics.tags[clave] = valor;
}

export function transcurridoMs() {
    return exec.instance.currentTestRunDuration;
}
