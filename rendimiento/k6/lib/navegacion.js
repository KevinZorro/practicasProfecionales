// Navegar como el navegador: la página y, la primera vez de cada usuario
// virtual (caché vacía), los assets que ella pide: CSS y JS de /build y
// livewire.js. Después el navegador los tiene en caché (nginx los sirve con
// un año de caché e immutable), así que no se vuelven a pedir.
import { sleep } from 'k6';
import { BASE_URL } from './config.js';
import { lote, pedir } from './http.js';
import { registrar, respuestas429, respuestas5xx, tasaDeError } from './verificar.js';

const yaVistos = {};

// Rutas de assets locales que la página pide, con la etiqueta que les toca.
export function activosDe(html) {
    const hallados = {};
    const agregar = (ruta) => {
        if (!ruta.startsWith('/') && !ruta.startsWith(BASE_URL)) {
            return;
        }
        const limpia = ruta.replace(BASE_URL, '');
        const sinQuery = limpia.split('?')[0];
        let etiqueta = 'activo.otro';

        if (sinQuery.endsWith('.css')) {
            etiqueta = 'activo.css';
        } else if (sinQuery.includes('livewire')) {
            etiqueta = 'activo.livewire';
        } else if (sinQuery.endsWith('.js')) {
            etiqueta = 'activo.js';
        }
        hallados[limpia] = etiqueta;
    };

    for (const m of html.matchAll(/<link[^>]+rel="(?:stylesheet|modulepreload)"[^>]*>/g)) {
        const href = m[0].match(/href="([^"]+)"/);
        if (href) {
            agregar(href[1]);
        }
    }
    for (const m of html.matchAll(/<script[^>]+src="([^"]+)"/g)) {
        agregar(m[1]);
    }

    return hallados;
}

// Pide los assets de la página que este usuario virtual aún no tiene.
// Con CACHE_VACIA=si los pide en cada visita (peor caso: navegador nuevo).
export function pedirActivos(html, siempre = (__ENV.CACHE_VACIA || '') === 'si') {
    const activos = activosDe(html);
    const faltan = Object.keys(activos).filter((ruta) => siempre || !yaVistos[ruta]);

    if (faltan.length === 0) {
        return;
    }

    const respuestas = lote(faltan.map((ruta) => ['GET', `${BASE_URL}${ruta}`, null, { tags: { name: activos[ruta] } }]));

    respuestas.forEach((r, i) => {
        const bien = r.status === 200;
        yaVistos[faltan[i]] = bien;
        tasaDeError.add(!bien, { name: activos[faltan[i]] });
        if (r.status === 429) {
            respuestas429.add(1, { name: activos[faltan[i]] });
        }
        if (r.status >= 500) {
            respuestas5xx.add(1, { name: activos[faltan[i]] });
        }
    });
}

// GET de una pantalla (o del JSON del calendario) con sus comprobaciones.
// Devuelve la respuesta. Con activos=true pide también los assets de la página.
export function visitar(pantalla, { activos = false } = {}) {
    const respuesta = pedir('GET', `${BASE_URL}${pantalla.ruta}`, null, {
        tags: { name: pantalla.nombre },
        headers: { Accept: pantalla.json ? 'application/json' : 'text/html,application/xhtml+xml' },
    });

    registrar(respuesta, pantalla.nombre);

    if (activos && !pantalla.json && respuesta.status === 200 && respuesta.body) {
        pedirActivos(respuesta.body);
    }

    return respuesta;
}

// Tiempo de reflexión de una persona entre dos pantallas.
export function pensar(minimo, maximo) {
    sleep(minimo + Math.random() * (maximo - minimo));
}
