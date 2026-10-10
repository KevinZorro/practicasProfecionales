/*
 * Portada pública: lo que necesita JavaScript (DESIGN.md).
 *
 * Las animaciones solo arrancan si el layout puso la clase "animar" en
 * <html>: hay IntersectionObserver y no se pidió reducir el movimiento. Sin
 * ella el contenido ya está completo en el HTML y aquí no se toca. El video
 * y el menú funcionan en los dos casos.
 */

// Arriba de todo: el código de arranque de abajo las usa, y una const no
// existe hasta que se ejecuta su línea.
const raiz = document.documentElement;
const conCursor = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
const pantallaAncha = window.matchMedia('(min-width: 768px)').matches;

// Una cifra que se puede contar: entera, con signo o unidad alrededor y con
// separador de miles ("+700", "1.200", "98 %"). "4,5" o "24/7" no cuentan.
const CIFRA_ENTERA = /^(\D*?)(\d{1,3}(?:([.,\s])\d{3})+|\d+)(\D*)$/;
const DURACION_DEL_CONTEO = 1400;
// Cuánto se acerca la foto del equipo principal, y cuánto se inclinan las
// insignias hacia el cursor, en grados.
const ACERCAMIENTO_MAXIMO = 0.08;
const INCLINACION_MAXIMA = 10;

prepararMenu();
prepararVideo();

if (raiz.classList.contains('animar')) {
    const revelador = revelarAlEntrar();
    contarCifras();
    acercarEquipoPrincipal(revelador);
    prepararInsignias();

    // Avisa al layout de que todo quedó listo, para que no quite "animar".
    raiz.setAttribute('data-portada-lista', '');
}

/* 3. Secciones y piezas: aparecen una sola vez al entrar en pantalla. */
function revelarAlEntrar() {
    const observador = new IntersectionObserver(
        (entradas) => {
            for (const entrada of entradas) {
                if (entrada.isIntersecting) {
                    entrada.target.classList.add('visible');
                    observador.unobserve(entrada.target);
                }
            }
        },
        { rootMargin: '0px 0px -8% 0px' },
    );

    document.querySelectorAll('.revelar').forEach((elemento) => observador.observe(elemento));

    return observador;
}

/*
 * 2. Cifras: cuentan desde cero hasta su valor cuando entran en pantalla.
 * Solo las que son un número entero, con signo o unidad alrededor y con
 * separador de miles ("+700", "1.200", "98 %"). Una fracción o un texto
 * como "24/7" se queda quieto.
 */

function contarCifras() {
    const observador = new IntersectionObserver(
        (entradas) => {
            for (const entrada of entradas) {
                if (entrada.isIntersecting) {
                    observador.unobserve(entrada.target);
                    contar(entrada.target);
                }
            }
        },
        { threshold: 0.6 },
    );

    document.querySelectorAll('[data-contador]').forEach((elemento) => {
        const partes = elemento.textContent.trim().match(CIFRA_ENTERA);

        if (!partes) {
            return;
        }

        const [, prefijo, numero, separador = '', sufijo] = partes;
        elemento.dataset.prefijo = prefijo;
        elemento.dataset.sufijo = sufijo;
        elemento.dataset.separador = separador;
        elemento.dataset.final = numero.replace(/\D/g, '');
        elemento.textContent = `${prefijo}0${sufijo}`;
        observador.observe(elemento);
    });
}

function contar(elemento) {
    const { prefijo, sufijo, separador } = elemento.dataset;
    const final = Number(elemento.dataset.final);
    const inicio = performance.now();

    const paso = (ahora) => {
        const avance = Math.min((ahora - inicio) / DURACION_DEL_CONTEO, 1);
        // easeOutQuart: rápido al principio y frena al llegar al valor.
        const valor = Math.round(final * (1 - (1 - avance) ** 4));
        elemento.textContent = `${prefijo}${conMiles(valor, separador)}${sufijo}`;

        if (avance < 1) {
            requestAnimationFrame(paso);
        }
    };

    requestAnimationFrame(paso);
}

function conMiles(valor, separador) {
    const texto = String(valor);

    return separador === '' ? texto : texto.replace(/\B(?=(\d{3})+(?!\d))/g, separador);
}

/*
 * 4. El equipo principal: en escritorio la foto se acerca hasta un 8 %
 * mientras su marco entra en pantalla; al salir por arriba conserva el
 * acercamiento. En móvil, un fundido.
 */

function acercarEquipoPrincipal(revelador) {
    document.querySelectorAll('[data-acercamiento]').forEach((marco) => {
        const foto = marco.querySelector('.portada-acercamiento');

        if (!pantallaAncha || !foto) {
            marco.classList.add('revelar', 'revelar--fundido');
            revelador.observe(marco);

            return;
        }

        const umbrales = Array.from({ length: 41 }, (_, indice) => indice / 40);

        new IntersectionObserver(
            ([entrada]) => {
                const entrando = entrada.boundingClientRect.top > 0;
                const avance = entrando ? entrada.intersectionRatio : 1;
                foto.style.transform = `scale(${(1 + ACERCAMIENTO_MAXIMO * avance).toFixed(4)})`;
            },
            { threshold: umbrales },
        ).observe(marco);
    });
}

/*
 * 5. Insignias: el brillo pasa una vez al entrar en pantalla (lo hace el CSS
 * al ponerles la clase "brillo"); con cursor se inclinan hacia él.
 */

function prepararInsignias() {
    const observador = new IntersectionObserver(
        (entradas) => {
            for (const entrada of entradas) {
                if (entrada.isIntersecting) {
                    entrada.target.classList.add('brillo');
                    observador.unobserve(entrada.target);
                }
            }
        },
        { threshold: 0.6 },
    );

    document.querySelectorAll('[data-insignia]').forEach((insignia) => {
        observador.observe(insignia);

        if (!conCursor) {
            return;
        }

        insignia.addEventListener('pointermove', (evento) => {
            const caja = insignia.getBoundingClientRect();
            const x = (evento.clientX - caja.left) / caja.width - 0.5;
            const y = (evento.clientY - caja.top) / caja.height - 0.5;
            insignia.style.transform = `perspective(700px) rotateX(${(-y * INCLINACION_MAXIMA).toFixed(2)}deg) rotateY(${(x * INCLINACION_MAXIMA).toFixed(2)}deg)`;
        });

        insignia.addEventListener('pointerleave', () => {
            insignia.style.transform = '';
        });
    });
}

/*
 * Video del hero (RF02): en silencio y en bucle, como fondo. Arranca solo
 * si no se pidió reducir el movimiento ni ahorrar datos; se detiene fuera
 * de pantalla, y el botón lo pausa (WCAG 2.2.2: lo que se mueve solo tiene
 * que poder detenerse).
 */
function prepararVideo() {
    const contenedor = document.querySelector('[data-video-hero]');

    if (!contenedor) {
        return;
    }

    const video = contenedor.querySelector('video');
    const boton = contenedor.querySelector('[data-video-control]');
    const etiqueta = boton.querySelector('[data-video-etiqueta]');
    const iconoPausa = boton.querySelector('[data-video-icono="pausa"]');
    const iconoReproducir = boton.querySelector('[data-video-icono="reproducir"]');

    let detenidoPorLaPersona =
        window.matchMedia('(prefers-reduced-motion: reduce)').matches || navigator.connection?.saveData === true;

    const pintarBoton = () => {
        const enMarcha = !video.paused;
        etiqueta.textContent = enMarcha ? 'Pausar el video' : 'Reproducir el video';
        iconoPausa.classList.toggle('hidden', !enMarcha);
        iconoReproducir.classList.toggle('hidden', enMarcha);
    };

    const reproducir = () => video.play().catch(pintarBoton);

    video.addEventListener('play', pintarBoton);
    video.addEventListener('pause', pintarBoton);

    boton.addEventListener('click', () => {
        detenidoPorLaPersona = !video.paused;

        if (detenidoPorLaPersona) {
            video.pause();
        } else {
            reproducir();
        }
    });

    new IntersectionObserver(([entrada]) => {
        if (entrada.isIntersecting && !detenidoPorLaPersona) {
            reproducir();
        } else {
            video.pause();
        }
    }).observe(video);

    pintarBoton();
    boton.hidden = false;
}

/* Menú de móvil: al elegir una sección, se cierra. */
function prepararMenu() {
    document.querySelectorAll('[data-menu]').forEach((menu) => {
        menu.addEventListener('click', (evento) => {
            if (evento.target.closest('a')) {
                menu.open = false;
            }
        });
    });
}
