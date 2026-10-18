/*
 * Calendario de escenarios aprobados (RF34).
 *
 * Solo se carga en la pantalla del calendario, no en todo el panel: es la
 * única dependencia pesada de JavaScript del sistema y el RNF10 pide no
 * arrastrarla donde no hace falta.
 */
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import esLocale from '@fullcalendar/core/locales/es';

const contenedor = document.getElementById('calendario');

if (contenedor) {
    const detalle = document.getElementById('detalle-evento');

    const calendario = new Calendar(contenedor, {
        plugins: [dayGridPlugin, timeGridPlugin],
        locale: esLocale,
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay',
        },
        height: 'auto',
        // Varias sesiones a la misma hora en salas distintas son válidas:
        // en las vistas por horas van lado a lado y en la mensual se
        // recortan con «+N más» en vez de empujar el resto de la semana.
        scrollTime: '06:00:00',
        slotEventOverlap: false,
        dayMaxEvents: 3,
        moreLinkClick: 'timeGridDay',
        events: (info, exito, fallo) => {
            const url = new URL(contenedor.dataset.eventos, window.location.origin);
            url.searchParams.set('desde', info.startStr.slice(0, 10));
            url.searchParams.set('hasta', info.endStr.slice(0, 10));

            fetch(url, { headers: { Accept: 'application/json' } })
                .then((r) => r.json())
                .then(exito)
                .catch(fallo);
        },
        eventClick: (info) => {
            info.jsEvent.preventDefault();
            mostrarDetalle(detalle, info.event);
        },
    });

    calendario.render();
}

function mostrarDetalle(contenedorDetalle, evento) {
    if (!contenedorDetalle) return;

    const d = evento.extendedProps;
    const filas = [
        ['Tipo de sesión', d.tipo],
        ['Fecha', d.fecha],
        ['Hora', d.hora],
        ['Docente que dicta', d.enReemplazoDe ? `${d.docente} (en reemplazo de ${d.enReemplazoDe})` : d.docente],
        ['Materia', d.materia],
        ['Grupo', d.grupo ?? 'Sin grupo'],
        ['Estudiantes', d.estudiantes],
        ['Sala', d.sala],
    ];

    contenedorDetalle.querySelector('[data-titulo]').textContent = d.casoClinico;
    contenedorDetalle.querySelector('[data-cuerpo]').innerHTML = filas
        .map(
            ([etiqueta, valor]) =>
                `<div class="min-w-0"><dt class="text-[11px] font-semibold uppercase tracking-[0.08em] text-gray-500">${etiqueta}</dt>` +
                `<dd class="mt-1 break-words text-[15px] leading-snug text-gray-900">${escapar(valor)}</dd></div>`,
        )
        .join('');
    contenedorDetalle.hidden = false;
    contenedorDetalle.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function escapar(texto) {
    const div = document.createElement('div');
    div.textContent = texto ?? '';
    return div.innerHTML;
}
