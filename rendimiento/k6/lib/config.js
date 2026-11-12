// Configuración común de las pruebas k6. Todo entra por variables de entorno
// (-e NOMBRE=valor): ningún escenario lleva direcciones ni cifras escritas.
//
//   BASE_URL         sitio a probar (http://localhost, el SUT de producción por http).
//   SESIONES         JSON de rendimiento/herramientas/crear-sesiones.php. Dentro del
//                    contenedor, rendimiento/ está montado en /rendimiento.
//   IP_POR_USUARIO   "si" manda X-Forwarded-For distinto por usuario virtual (ver README).
//   RESULTADOS       carpeta de salida, relativa al directorio de trabajo de k6.

export const BASE_URL = (__ENV.BASE_URL || 'http://localhost').replace(/\/+$/, '');
export const SESIONES = __ENV.SESIONES || '/rendimiento/resultados/sesiones.json';
export const IP_POR_USUARIO = (__ENV.IP_POR_USUARIO || 'no').toLowerCase() === 'si';
export const RESULTADOS = (__ENV.RESULTADOS || 'resultados/k6').replace(/\/+$/, '');

// Apdex con T = 0,5 s: satisfecho <= T, tolerable <= 4T, frustrado > 4T.
// 4T = 2 s coincide con el p95 del RNF01.
export const APDEX_T_MS = 500;
export const APDEX_TOLERABLE_MS = 4 * APDEX_T_MS;

// Límite de p95 de las pantallas: el del RNF01 salvo que se pida otro.
export const LIMITE_P95_MS = entero('LIMITE_P95_MS', 2000);

export function entero(nombre, porDefecto) {
    const crudo = __ENV[nombre];
    const valor = crudo === undefined || crudo === '' ? NaN : Number(crudo);

    return Number.isFinite(valor) ? Math.floor(valor) : porDefecto;
}

export function texto(nombre, porDefecto) {
    const crudo = __ENV[nombre];

    return crudo === undefined || crudo === '' ? porDefecto : crudo;
}

// "30s", "5m", "1h30m" -> milisegundos, para calcular ventanas y escalones.
export function aMilisegundos(duracion) {
    const partes = String(duracion).match(/(\d+(?:\.\d+)?)(ms|s|m|h)/g) || [];
    const factores = { ms: 1, s: 1000, m: 60000, h: 3600000 };

    return partes.reduce((suma, parte) => {
        const [, cifra, unidad] = parte.match(/(\d+(?:\.\d+)?)(ms|s|m|h)/);

        return suma + Number(cifra) * factores[unidad];
    }, 0);
}
