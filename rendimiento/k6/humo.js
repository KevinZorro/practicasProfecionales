// HUMO / LÍNEA BASE (ISTQB: smoke test; ISO/IEC 25023 PTb-1-G, tiempo medio de respuesta).
//
// Un solo usuario virtual recorre todas las pantallas de todos los roles N
// veces, sin contención: es la latencia de referencia de cada pantalla y la
// comprobación de que las sesiones y el catálogo sirven. Umbral: cero errores
// (sin 429, sin 5xx, sin sesión inválida). La latencia por pantalla se
// reporta pero no reprueba: sin carga no hay objetivo que cumplir, solo base.
//
//   docker run --rm --network host -v "$PWD/rendimiento:/rendimiento" -w /rendimiento \
//     -e BASE_URL=http://localhost grafana/k6:2.3.0 run k6/humo.js
//
// Variables: BASE_URL, SESIONES, REPETICIONES (3), IP_POR_USUARIO, CACHE_VACIA.
import { entero } from './lib/config.js';
import { ROLES, eventosDelCalendario, nombresDeTodo, pantallasDe, tieneCalendario, SOLICITUD_VER } from './lib/catalogo.js';
import { descubrirSolicitud } from './lib/descubrir.js';
import { BASE_URL } from './lib/config.js';
import { pedir } from './lib/http.js';
import { pensar, visitar } from './lib/navegacion.js';
import { crearResumen } from './lib/resumen.js';
import { ROLES_CON_SESION, entrar } from './lib/sesiones.js';
import { ESTADISTICAS, umbralesBase, umbralesDeCero, umbralesPorEtiqueta, unir } from './lib/umbrales.js';
import { registrar } from './lib/verificar.js';

export const options = {
    scenarios: {
        humo: { executor: 'per-vu-iterations', vus: 1, iterations: entero('REPETICIONES', 3), maxDuration: '30m' },
    },
    thresholds: unir(umbralesBase(), umbralesDeCero(), umbralesPorEtiqueta('name', nombresDeTodo([SOLICITUD_VER.nombre]))),
    summaryTrendStats: ESTADISTICAS,
    noCookiesReset: true,
};

export function setup() {
    return { solicitudId: ROLES_CON_SESION.includes('coordinador') ? descubrirSolicitud('coordinador') : null };
}

export default function (datos) {
    for (const rol of ROLES) {
        if (!ROLES_CON_SESION.includes(rol)) {
            console.warn(`Sin sesión de ${rol}: se omite`);
            continue;
        }

        entrar(rol, 0);

        for (const pantalla of pantallasDe(rol)) {
            visitar(pantalla, { activos: true });
            pensar(0.1, 0.3);
        }

        if (tieneCalendario(rol)) {
            visitar(eventosDelCalendario('semana'));
            visitar(eventosDelCalendario('mes'));
        }

        if (datos.solicitudId && ['coordinador', 'administrativo', 'admin'].includes(rol)) {
            registrar(pedir('GET', `${BASE_URL}/panel/solicitudes/${datos.solicitudId}`, null, { tags: { name: SOLICITUD_VER.nombre } }), SOLICITUD_VER.nombre);
        }
    }
}

export const handleSummary = crearResumen('humo', { tipo: 'humo / línea base', medida: 'PTb-1-G tiempo medio de respuesta' });
