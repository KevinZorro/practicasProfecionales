// Livewire como lo hace el navegador: se toma de la página el wire:snapshot
// del componente y el token CSRF, y se manda a /livewire/update. Lo que el
// navegador manda y los tests no (tests/Pest.php: accionDeLivewire) es el
// _token, porque Laravel salta el CSRF en pruebas.
import { BASE_URL } from './config.js';
import { http, pedir } from './http.js';

function decodificar(html) {
    return html
        .replace(/&quot;/g, '"')
        .replace(/&#0?39;|&#x27;/g, "'")
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&amp;/g, '&');
}

// Token CSRF de la página (meta csrf-token).
export function tokenDe(html) {
    const coincidencia = html.match(/name="csrf-token" content="([^"]+)"/) || html.match(/data-csrf="([^"]+)"/);

    return coincidencia ? coincidencia[1] : null;
}

// Todos los componentes Livewire de la página: [{ nombre, snapshot, datos }].
export function componentesDe(html) {
    const encontrados = [];
    const patron = /wire:snapshot="([^"]*)"/g;
    let m;

    while ((m = patron.exec(html)) !== null) {
        const snapshot = decodificar(m[1]);

        try {
            const j = JSON.parse(snapshot);
            encontrados.push({ nombre: j.memo.name, snapshot, datos: j.data });
        } catch (e) {
            // Un snapshot que no se puede leer no es un componente utilizable.
        }
    }

    return encontrados;
}

// El componente de la página por su nombre de Livewire ("solicitud.formulario-solicitud").
export function componenteDe(html, nombre) {
    const hallado = componentesDe(html).find((c) => c.nombre === nombre);

    if (!hallado) {
        throw new Error(`La página no trae el componente Livewire ${nombre}`);
    }

    return hallado;
}

// GET de una página y extracción de su componente y su token.
export function abrirComponente(ruta, nombre, etiqueta) {
    const respuesta = pedir('GET', `${BASE_URL}${ruta}`, null, { tags: { name: etiqueta } });
    const token = tokenDe(respuesta.body || '');

    if (respuesta.status !== 200 || token === null) {
        return { respuesta, componente: null, token: null, html: respuesta.body };
    }

    return { respuesta, componente: componenteDe(respuesta.body, nombre), token, html: respuesta.body };
}

// Una llamada a /livewire/update. `actualizaciones` es { propiedad: valor }
// (lo que el navegador manda al escribir en un campo) y `llamadas` es
// [{ metodo, parametros }]. Devuelve la respuesta y el componente con su
// nueva instantánea, para encadenar acciones.
export function actualizar(componente, token, { actualizaciones = {}, llamadas = [] }, etiqueta, extra = {}) {
    const cuerpo = JSON.stringify({
        _token: token,
        components: [{
            snapshot: componente.snapshot,
            updates: actualizaciones,
            calls: llamadas.map((l) => ({ path: '', method: l.metodo, params: l.parametros || [] })),
        }],
    });

    const respuesta = pedir('POST', `${BASE_URL}/livewire/update`, cuerpo, Object.assign({
        tags: { name: etiqueta },
        headers: {
            'Content-Type': 'application/json',
            'X-Livewire': 'true',
            'X-CSRF-TOKEN': token,
            Accept: 'application/json',
        },
    }, extra));

    let siguiente = componente;
    let efectos = {};
    let redireccion = null;

    if (respuesta.status === 200) {
        try {
            const c = JSON.parse(respuesta.body).components[0];
            const j = JSON.parse(c.snapshot);
            siguiente = { nombre: componente.nombre, snapshot: c.snapshot, datos: j.data };
            efectos = c.effects || {};
            redireccion = efectos.redirect || null;
        } catch (e) {
            // Respuesta que no es de Livewire: se devuelve el componente anterior.
        }
    }

    return { respuesta, componente: siguiente, efectos, redireccion };
}

export function esRedireccion(resultado) {
    return resultado.redireccion !== null;
}

export { http };
