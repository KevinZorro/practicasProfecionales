// RESISTENCIA (ISTQB: endurance / soak test; ISO/IEC 25023 PRu-2-G uso medio de
// memoria y PTb-1-G tiempo medio de respuesta a lo largo del tiempo).
//
// Carga moderada y constante durante DURACION con la mezcla realista de la
// hora pico. Las métricas van por ventana de tiempo (VENTANA) para comparar el
// p95 del principio con el del final: una deriva hacia arriba apunta a una
// fuga (memoria de PHP-FPM, conexiones de PostgreSQL, tabla de sesiones,
// caché o cola que crecen). Pareja natural de muestrear-recursos.sh, que da
// la memoria de cada contenedor en el mismo lapso.
//
// Umbrales: p(95) < 2 s en cada ventana, errores 0 (sin 429 ni 5xx). La deriva
// no es un umbral de k6: se calcula en el análisis (p95 de la última ventana
// completa contra la primera) y se marca "con deriva" si sube más de DERIVA_MAXIMA %.
//
// Variables: DURACION (30m), VUS (50), VENTANA (5m), DERIVA_MAXIMA (25),
// BASE_URL, SESIONES, IP_POR_USUARIO.
import { LIMITE_P95_MS, aMilisegundos, entero, texto } from './lib/config.js';
import { nombresDeEventos, nombresDePantallas, ACTIVOS } from './lib/catalogo.js';
import { MEZCLA, etiquetar, mezclaDisponible, recorridoDelRol, transcurridoMs } from './lib/recorridos.js';
import { crearResumen } from './lib/resumen.js';
import { entrar, personaDelVU } from './lib/sesiones.js';
import { ESTADISTICAS, umbralesBase, umbralesPorEtiqueta, umbralesRnf01, unir } from './lib/umbrales.js';

const DURACION = texto('DURACION', '30m');
const VENTANA = texto('VENTANA', '5m');
const DERIVA_MAXIMA = entero('DERIVA_MAXIMA', 25);
const msVentana = aMilisegundos(VENTANA);
const cuantas = Math.max(1, Math.ceil(aMilisegundos(DURACION) / msVentana));
const ventanas = Array.from({ length: cuantas }, (_, i) => `w${String(i).padStart(2, '0')}`);

export const options = {
    scenarios: {
        constante: { executor: 'constant-vus', vus: entero('VUS', 50), duration: DURACION, gracefulStop: '30s' },
    },
    thresholds: unir(
        umbralesBase(),
        umbralesRnf01([...nombresDePantallas(), ...nombresDeEventos()]),
        umbralesPorEtiqueta('name', ACTIVOS),
        umbralesPorEtiqueta('ventana', ventanas, LIMITE_P95_MS),
    ),
    summaryTrendStats: ESTADISTICAS,
    noCookiesReset: true,
};

export default function () {
    etiquetar('ventana', ventanas[Math.min(cuantas - 1, Math.floor(transcurridoMs() / msVentana))]);

    const { rol, ordinal } = personaDelVU(mezclaDisponible(MEZCLA), __VU);

    entrar(rol, ordinal);
    recorridoDelRol(rol);
}

export const handleSummary = crearResumen('resistencia', {
    tipo: 'resistencia (soak)',
    medida: 'PRu-2-G uso medio de memoria (con muestrear-recursos.sh); PTb-1-G tiempo medio de respuesta en el tiempo',
    extra: (data, informe) => {
        // Una ventana parcial al final sesga el p95: se compara con la última completa.
        const con = ventanas.filter((v) => informe.por_ventana[v]);
        const primera = informe.por_ventana[con[0]];
        const ultima = informe.por_ventana[con.length > 1 ? con[con.length - 1] : con[0]];

        if (!primera || !ultima) {
            return { deriva: 'sin datos suficientes' };
        }

        const porcentaje = primera.p95 > 0 ? ((ultima.p95 - primera.p95) / primera.p95) * 100 : 0;

        return {
            p95_primera_ventana_ms: primera.p95,
            p95_ultima_ventana_ms: ultima.p95,
            deriva_p95_porcentaje: Math.round(porcentaje * 10) / 10,
            deriva_maxima_porcentaje: DERIVA_MAXIMA,
            veredicto_de_deriva: porcentaje > DERIVA_MAXIMA ? 'CON DERIVA' : 'ESTABLE',
            p95_por_ventana_ms: Object.fromEntries(con.map((v) => [v, informe.por_ventana[v].p95])),
        };
    },
});
