// Toda petición del arnés pasa por aquí, para que lleve las mismas cabeceras
// y el mismo modo de IP, sea página, asset, Livewire o descarga.
import http from 'k6/http';
import { IP_POR_USUARIO } from './config.js';

// IP estable y distinta por usuario virtual, en 10.0.0.0/8 y sin terminar en
// 0 ni 255. nginx de producción solo la toma de X-Forwarded-For si la
// petición viene de una IP de PROXIES_DE_CONFIANZA (docker/produccion/ip-real.sh).
export function ipDelUsuario(vu) {
    const n = Math.max(0, vu);
    const ultimo = 1 + (n % 253);
    const tercero = Math.floor(n / 253) % 256;
    const segundo = Math.floor(n / (253 * 256)) % 256;

    return `10.${segundo}.${tercero}.${ultimo}`;
}

export function cabeceras(extra = {}) {
    const base = { 'Accept-Encoding': 'gzip, deflate' };

    if (IP_POR_USUARIO) {
        base['X-Forwarded-For'] = ipDelUsuario(typeof __VU === 'number' ? __VU : 0);
    }

    return Object.assign(base, extra);
}

export function pedir(metodo, url, cuerpo, parametros = {}) {
    const params = Object.assign({}, parametros, { headers: cabeceras(parametros.headers || {}) });

    return http.request(metodo, url, cuerpo, params);
}

export function lote(peticiones) {
    return http.batch(peticiones.map(([metodo, url, cuerpo, parametros = {}]) => [
        metodo,
        url,
        cuerpo,
        Object.assign({}, parametros, { headers: cabeceras(parametros.headers || {}) }),
    ]));
}

export { http };
