// Sesiones autenticadas: una por rol y por usuario virtual, de forma
// determinista. El JSON lo escribe rendimiento/herramientas/crear-sesiones.php.
import http from 'k6/http';
import { BASE_URL, SESIONES } from './config.js';

// open() solo existe en el contexto init: se lee una vez por usuario virtual.
const datos = JSON.parse(open(SESIONES));

const PORROL = {};
for (const sesion of datos.sesiones) {
    (PORROL[sesion.rol] = PORROL[sesion.rol] || []).push(sesion);
}

export const COOKIE = datos.cookie;
export const ROLES_CON_SESION = Object.keys(PORROL);

export function cuantasHay(rol) {
    return (PORROL[rol] || []).length;
}

// La persona número `ordinal` del rol. Si hay menos sesiones que usuarios
// virtuales se reutilizan en círculo: la prueba corre igual, pero ya no son
// personas distintas (el resumen de la prueba lo advierte con cuantasHay()).
export function sesionDe(rol, ordinal) {
    const lista = PORROL[rol] || [];

    if (lista.length === 0) {
        throw new Error(`El archivo de sesiones no tiene ninguna del rol ${rol}: ${SESIONES}`);
    }

    return lista[Math.abs(ordinal) % lista.length];
}

// Pone la cookie de sesión en el tarro de k6 para BASE_URL. El SUT va por
// http, así que la cookie no lleva secure.
export function entrar(rol, ordinal) {
    const sesion = sesionDe(rol, ordinal);

    http.cookieJar().set(BASE_URL, COOKIE, sesion.valor, { path: '/' });

    return sesion;
}

// Reparte los usuarios virtuales entre roles según pesos: { estudiante: 60, docente: 30 ... }.
// Devuelve el rol de ese VU y su ordinal dentro del rol (0, 1, 2...), para que
// 150 VU sean 150 personas distintas mientras haya sesiones.
export function personaDelVU(mezcla, vu) {
    const roles = Object.keys(mezcla);
    const total = roles.reduce((suma, rol) => suma + mezcla[rol], 0);
    const cuenta = {};
    let elegido = roles[0];

    for (let i = 0; i < vu; i++) {
        let punto = ((i * 7919) % total);
        // Reparto determinista y proporcional: recorre los pesos acumulados.
        elegido = roles.find((rol) => {
            punto -= mezcla[rol];

            return punto < 0;
        });
        cuenta[elegido] = (cuenta[elegido] || 0) + 1;
    }

    return { rol: elegido, ordinal: (cuenta[elegido] || 1) - 1 };
}
