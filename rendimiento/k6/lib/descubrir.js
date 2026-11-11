// Datos que una prueba necesita y que dependen de la base: se descubren
// leyendo las mismas pantallas que ve una persona, no con SQL.
import { BASE_URL } from './config.js';
import { pedir } from './http.js';
import { entrar } from './sesiones.js';

// Id de una solicitud cualquiera, de la bandeja de coordinación (o la que se pida).
export function descubrirSolicitud(rol = 'coordinador') {
    entrar(rol, 0);
    const r = pedir('GET', `${BASE_URL}/panel/solicitudes`, null, { tags: { name: 'setup.descubrir' } });
    const m = (r.body || '').match(/\/panel\/solicitudes\/(\d+)(?![\d\/])/);

    return m ? Number(m[1]) : null;
}

// Ids de listas de reposición cerradas (enlaces a /panel/reposicion/{id}/pdf).
export function descubrirListasCerradas(rol = 'coordinador') {
    entrar(rol, 0);
    const r = pedir('GET', `${BASE_URL}/panel/reposicion`, null, { tags: { name: 'setup.descubrir' } });
    const ids = new Set();

    for (const m of (r.body || '').matchAll(/\/panel\/reposicion\/(\d+)\/(?:pdf|excel)/g)) {
        ids.add(Number(m[1]));
    }

    return [...ids];
}
