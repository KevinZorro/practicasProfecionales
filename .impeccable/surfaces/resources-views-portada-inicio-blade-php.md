---
version: 1
slug: "resources-views-portada-inicio-blade-php"
primary_target: "resources/views/portada/inicio.blade.php"
related_targets: ["resources/views/portada/escenario.blade.php","resources/views/layouts/publico.blade.php","resources/js/portada.js"]
---

## Scope

Portada pública del laboratorio (`/`) y el detalle de cada escenario clínico (`/escenarios/{caso}`). Modo: **Persuade**.

## Audience, action, proof

- Quién: aspirantes, estudiantes, profesionales de la salud que buscan talleres, visitantes institucionales; usuarios internos que entran a la plataforma.
- Acción: conocer los escenarios y la oferta académica; los usuarios internos, «Ingresar» (entrada con Google).
- Prueba: el equipamiento destacado (simuladores, sala inmersiva, mesa de anatomía virtual), las cifras, los escenarios con sus capacidades, las certificaciones y los docentes. Todo viene del ADMIN; nada se inventa.

## Constraints

- Brief del usuario (9-oct-2026): estética tipo Apple y Xiaomi, titulares muy grandes y cortos, mucho blanco, fondos que alternan blanco y gris muy claro, una idea fuerte por sección; equipamiento como productos protagonistas con fotos grandes sobre fondo neutro; rejilla bento de tamaños distintos para cifras, escenarios y oferta académica, una columna en móvil y de 2 a 4 en tablet y escritorio; rojo solo como acento; línea de pulso ECG y cifras de monitor con moderación. Prohibido: gradientes morados, tarjetas idénticas con ícono arriba, glassmorphism, emojis, sombras exageradas, textos de relleno.
- Exactamente seis animaciones, en CSS (transform y opacity) con IntersectionObserver, sin librerías, desactivadas con `prefers-reduced-motion`, contenido completo sin ellas: (1) fade-up del titular y pulso dibujado una vez; (2) conteo de cifras; (3) reveal escalonado de secciones y tarjetas, máximo 400 ms; (4) acercamiento leve del simulador principal al hacer scroll, fade en móvil; (5) brillo metálico de insignias al entrar y leve inclinación con el cursor; (6) credenciales de docentes: la foto se eleva y aparecen los títulos al pasar el cursor, siempre visibles en móvil. Ninguna otra sin consultar.
- Sin fotos reales por ahora: cada bloque necesita un estado sin foto bien resuelto.
- Fuera de esta entrega: galería de videos (RF08, espera RF17) y formulario por taller (RF09).

## Direction contract

THESIS: La portada es la página de producto del laboratorio: su equipamiento y sus escenarios se presentan como productos, una idea fuerte por sección. Rechaza el hero oscuro con texto sobre video, la rejilla de tarjetas iguales con ícono y cualquier degradado decorativo.

OWN-WORLD: Blanco y gris `#f5f5f7` alternados por sección; tinta `#1d1d1f`, gris `#6e6e73`; rojo `#d30f23` solo en botones, enlaces, detalles y cifras. Onest: 800 para titulares enormes y apretados, 400 para el texto, 300 con cifras tabulares para los números de monitor. Esquinas de 28 px, sin sombras duras ni vidrio. Firma: una línea de pulso ECG en SVG, usada pocas veces.

STORY: El visitante ve el laboratorio en marcha, cree que es de primer nivel por su equipamiento, sus cifras y su gente, recorre los escenarios y la oferta, y sabe dónde ingresar o pedir información.

FIRST VIEWPORT: Cabecera blanca fina: símbolo UFPS y nombre del laboratorio a la izquierda, enlaces de sección y «Ingresar» en rojo a la derecha. Titular centrado de hasta 6rem, subtítulo de dos líneas, botón rojo «Conoce los escenarios» y enlace a la oferta académica. La línea de pulso cruza todo el ancho bajo los botones y se dibuja una vez. Debajo, el video institucional en un marco redondeado casi a todo el ancho.

FORM: Fijada por el brief del usuario (página de producto tipo Apple/Xiaomi con rejilla bento). Sin tirada de concept-seed: la dirección la fijó el brief.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Memorable moment

La línea de pulso que se dibuja bajo el titular, una sola vez, y el simulador principal que se acerca al entrar en pantalla.

## Unresolved

- Fotos reales del equipamiento, escenarios, docentes e insignias (las sube el ADMIN).
- Video institucional del hero (lo sube el ADMIN).
