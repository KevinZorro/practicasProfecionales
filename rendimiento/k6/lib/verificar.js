// Comprobaciones y contadores propios de todas las pruebas.
import { check } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';
import { APDEX_T_MS, APDEX_TOLERABLE_MS } from './config.js';

export const respuestas429 = new Counter('respuestas_429');
export const respuestas5xx = new Counter('respuestas_5xx');
// 502, 503 y 504: nginx no consiguió respuesta de PHP-FPM (saturado o caído). Es la firma de la saturación.
export const respuestasDeUpstream = new Counter('respuestas_502_504');
export const sesionesInvalidas = new Counter('sesiones_invalidas');
export const tasaDeError = new Rate('tasa_error');
export const tamanoDelCuerpo = new Trend('tamano_cuerpo_bytes', false);
export const rechazosDeNegocio = new Counter('rechazos_422');

// Apdex (T = 0,5 s): se cuentan con etiqueta name para sacar el de cada pantalla.
export const apdexSatisfechas = new Counter('apdex_satisfechas');
export const apdexToleradas = new Counter('apdex_toleradas');
export const apdexTotal = new Counter('apdex_total');

export function fueRedirigidaAlAcceso(respuesta) {
    return /\/(acceso|desarrollo\/acceso)(\?|$|\/)/.test(respuesta.url || '');
}

// Registra una respuesta de página o JSON. `nombre` es el tag name.
// Con aceptar422 (escrituras concurrentes) un 422 de negocio no cuenta como error;
// `aceptar` admite otros estados de negocio (un 403 de la Policy al perder una carrera).
export function registrar(respuesta, nombre, { aceptar422 = false, aceptar = [], medirApdex = true } = {}) {
    const etiquetas = { name: nombre };
    const estado = respuesta.status;
    const redirigida = fueRedirigidaAlAcceso(respuesta);
    const largo = respuesta.body ? (respuesta.body.length !== undefined ? respuesta.body.length : respuesta.body.byteLength || 0) : 0;

    if (estado === 429) {
        respuestas429.add(1, etiquetas);
    }
    if (estado >= 500) {
        respuestas5xx.add(1, etiquetas);
    }
    if (estado === 502 || estado === 503 || estado === 504) {
        respuestasDeUpstream.add(1, etiquetas);
    }
    if (redirigida) {
        sesionesInvalidas.add(1, etiquetas);
    }
    if (estado === 422) {
        rechazosDeNegocio.add(1, etiquetas);
    }

    const bien = check(respuesta, {
        'responde 200': (r) => r.status === 200 || (aceptar422 && r.status === 422) || aceptar.includes(r.status),
        'no terminó en /acceso (sesión válida)': () => !redirigida,
        'cuerpo no vacío': () => largo > 0 || estado === 204,
    }, etiquetas);

    tasaDeError.add(!bien, etiquetas);
    tamanoDelCuerpo.add(largo, etiquetas);

    if (medirApdex) {
        const ms = respuesta.timings.duration;
        apdexTotal.add(1, etiquetas);
        if (bien && ms <= APDEX_T_MS) {
            apdexSatisfechas.add(1, etiquetas);
        } else if (bien && ms <= APDEX_TOLERABLE_MS) {
            apdexToleradas.add(1, etiquetas);
        }
    }

    return bien;
}
