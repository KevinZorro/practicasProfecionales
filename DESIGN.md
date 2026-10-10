---
name: "Laboratorio de Simulación Clínica · UFPS"
description: "La portada pública como página de producto: blanco y niebla alternados, Onest, piezas planas de 28 px y el rojo institucional solo como acento."
colors:
  rojo: "#d30f23"
  rojo-hondo: "#ad0c1c"
  tinta: "#1d1d1f"
  gris: "#6e6e73"
  linea: "#d2d2d7"
  niebla: "#f5f5f7"
  blanco: "#ffffff"
typography:
  display:
    fontFamily: "Onest, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(2.75rem, 1.2rem + 6.4vw, 6rem)"
    fontWeight: 800
    lineHeight: 1.02
    letterSpacing: "-0.038em"
  headline:
    fontFamily: "Onest, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(2.25rem, 1.35rem + 3.6vw, 4.5rem)"
    fontWeight: 800
    lineHeight: 1.04
    letterSpacing: "-0.032em"
  title-producto:
    fontFamily: "Onest, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(1.5rem, 0.9rem + 2.4vw, 3rem)"
    fontWeight: 800
    lineHeight: 1.04
    letterSpacing: "-0.032em"
  title-lg:
    fontFamily: "Onest, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(1.875rem, 1.4rem + 1.8vw, 3rem)"
    fontWeight: 700
    lineHeight: 1.1
    letterSpacing: "-0.025em"
  title:
    fontFamily: "Onest, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 700
    lineHeight: 1.1
    letterSpacing: "-0.025em"
  body-lg:
    fontFamily: "Onest, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 400
    lineHeight: 1.625
  body:
    fontFamily: "Onest, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 400
    lineHeight: 1.625
  label:
    fontFamily: "Onest, ui-sans-serif, system-ui, sans-serif"
    fontSize: "13px"
    fontWeight: 600
    letterSpacing: "0.14em"
  boton:
    fontFamily: "Onest, ui-sans-serif, system-ui, sans-serif"
    fontSize: "17px"
    fontWeight: 600
  enlace:
    fontFamily: "Onest, ui-sans-serif, system-ui, sans-serif"
    fontSize: "15px"
    fontWeight: 600
  cifra-principal:
    fontFamily: "Onest, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(4.5rem, 2.6rem + 7vw, 9rem)"
    fontWeight: 300
    lineHeight: 1
    letterSpacing: "-0.04em"
    fontFeature: '"tnum" 1'
  cifra:
    fontFamily: "Onest, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(3.25rem, 2.5rem + 2.6vw, 4.75rem)"
    fontWeight: 300
    lineHeight: 1
    letterSpacing: "-0.04em"
    fontFeature: '"tnum" 1'
rounded:
  pieza: "28px"
  marco: "20px"
  pastilla: "9999px"
spacing:
  margen-del-marco: "8px"
  rejilla: "16px"
  pieza: "28px"
  pieza-amplia: "40px"
  lateral-movil: "16px"
  lateral-tablet: "24px"
  lateral-escritorio: "32px"
  columnas: "48px"
  bajo-la-cabecera: "64px"
  seccion-movil: "96px"
  seccion-tablet: "128px"
  seccion-escritorio: "144px"
  contenedor: "1180px"
  contenedor-medios: "1400px"
components:
  boton-primario:
    backgroundColor: "{colors.rojo}"
    textColor: "{colors.blanco}"
    typography: "{typography.boton}"
    rounded: "{rounded.pastilla}"
    padding: "14px 28px"
  boton-primario-hover:
    backgroundColor: "{colors.rojo-hondo}"
  boton-cabecera:
    backgroundColor: "{colors.rojo}"
    textColor: "{colors.blanco}"
    rounded: "{rounded.pastilla}"
    padding: "8px 16px"
  boton-cabecera-hover:
    backgroundColor: "{colors.rojo-hondo}"
  enlace:
    textColor: "{colors.rojo}"
    typography: "{typography.enlace}"
  boton-icono:
    backgroundColor: "{colors.blanco}"
    textColor: "{colors.tinta}"
    rounded: "{rounded.pastilla}"
    size: "44px"
  boton-icono-hover:
    backgroundColor: "{colors.niebla}"
  cabecera:
    backgroundColor: "{colors.blanco}"
    textColor: "{colors.tinta}"
    height: "64px"
  etiqueta-capacidad:
    backgroundColor: "{colors.blanco}"
    textColor: "{colors.tinta}"
    rounded: "{rounded.pastilla}"
    padding: "4px 12px"
  pieza:
    backgroundColor: "{colors.niebla}"
    textColor: "{colors.tinta}"
    rounded: "{rounded.pieza}"
    padding: "28px"
  pieza-sobre-niebla:
    backgroundColor: "{colors.blanco}"
  pieza-destacada:
    padding: "40px"
  marco-foto:
    backgroundColor: "{colors.blanco}"
    rounded: "{rounded.marco}"
  cifra:
    backgroundColor: "{colors.niebla}"
    textColor: "{colors.rojo}"
    typography: "{typography.cifra}"
    rounded: "{rounded.pieza}"
    padding: "32px"
  pulso:
    textColor: "{colors.rojo}"
    height: "80px"
  ficha-caracteristicas:
    textColor: "{colors.tinta}"
    padding: "16px 0"
  sello:
    backgroundColor: "{colors.niebla}"
    textColor: "{colors.tinta}"
    rounded: "{rounded.pastilla}"
    size: "176px"
  credencial:
    backgroundColor: "{colors.niebla}"
    textColor: "{colors.tinta}"
    rounded: "{rounded.pieza}"
    padding: "8px"
  pie:
    backgroundColor: "{colors.niebla}"
    textColor: "{colors.gris}"
---

# Design System: Laboratorio de Simulación Clínica · UFPS

## Overview

**Norte creativo: «La vitrina clínica»**

La portada es la página de producto del laboratorio. Su equipamiento, sus escenarios, sus cifras y su gente se exhiben como productos en una vitrina limpia: superficies blancas y niebla que se alternan, titulares enormes y cortos, mucho aire y una sola idea fuerte por sección. El contenido lo carga el ADMIN; el diseño pone la vitrina y deja que el laboratorio sea la prueba.

La firma clínica es escasa a propósito: una línea de pulso ECG que se dibuja una sola vez bajo los botones del hero, y cifras rojas, ligeras y tabulares, como la lectura de un monitor de signos vitales. El rojo institucional de la UFPS aparece solo donde se pulsa o se lee un dato. La densidad es baja: pocas piezas grandes, de esquinas generosas y tamaños distintos, planas, sin sombras ni vidrio; la profundidad sale del contraste de tono entre una pieza y el fondo de su sección.

Todavía no hay fotos reales, y cada módulo tiene que verse terminado sin ellas. La aspiración es el nivel de las páginas de producto de Apple y Xiaomi sin parecer una plantilla. Rechazos confirmados: gradientes morados, tarjetas idénticas con ícono arriba, glassmorphism, emojis, sombras exageradas, textos de relleno.

Alcance: las páginas públicas, la portada (`/`) y el detalle de cada escenario (`/escenarios/{id}`), sobre el layout `layouts/publico.blade.php`. El panel (`/panel`) y `/admin` no usan estos tokens.

**Rasgos clave:**
- Blanco y niebla alternados entre las secciones presentes; el rojo, solo como acento.
- Una sola familia, Onest, servida desde el propio servidor: 800 apretada para afirmar, 300 tabular y roja para medir.
- Piezas planas de 28 px con marcos concéntricos de 20 px a 8 px del borde.
- Rejilla bento de tamaños distintos que cierra sus filas: una columna en móvil, de 2 a 4 desde 640 px.
- Aperturas de sección decididas por el ritmo de la página, nunca por la cantidad de piezas.
- Una línea de pulso ECG, dibujada una sola vez.
- Seis animaciones, ni una más; sin ellas el contenido está completo.
- Ningún módulo depende de una foto para verse terminado.

## Colors

Neutros fríos de vitrina (blanco, niebla, una tinta casi negra) y un único rojo institucional que nunca ocupa un fondo.

### Primario
- **Rojo UFPS** (#d30f23): el rojo de los logos oficiales. Botones rellenos, enlaces de texto, cifras de monitor, el trazo del pulso, los trazos cortos de las fichas y los sellos, el punto de «Abierto al público», el contorno de foco y, al 15 %, la selección de texto.
- **Rojo hondo** (#ad0c1c): solo el hover de un botón rojo relleno.

### Neutros
- **Tinta** (#1d1d1f): titulares y texto principal. Al 80 %, los enlaces de la cabecera; al 30 %, las iniciales de una credencial sin foto. Es también el fondo del enlace «Saltar al contenido».
- **Gris de apoyo** (#6e6e73): subtítulos, descripciones, cargos, metadatos y etiquetas de datos.
- **Filete** (#d2d2d7): líneas de 1 px entre las filas de una ficha, del contacto, del menú móvil y del pie, y el círculo interior del sello. Nunca texto.
- **Niebla** (#f5f5f7): una de cada dos secciones, el tono de las piezas sobre secciones blancas y el fondo del pie.
- **Blanco** (#ffffff): la página, el hero, la cabecera, la otra mitad de las secciones y las piezas sobre secciones niebla.

### Reglas
**La regla del acento.** El rojo marca lo que se pulsa y lo que se lee como dato; nunca es el fondo de una sección ni de una pieza. La superficie roja más grande que se permite es un botón en pastilla.

**La regla de la pieza que se voltea.** Las secciones alternan blanco y niebla entre las que de verdad se pintan (la primera después del hero es niebla), así que ninguna pieza fija su color: lee `--pieza` (niebla sobre blanco, blanco sobre niebla), y lo que va dentro de ella, como marcos de foto y etiquetas, lee `--pieza-inversa`. Las definen `.fondo-blanco` y `.fondo-niebla` en `resources/css/app.css`.

**La regla del contraste.** El gris es el tono más claro que se admite para texto: 5,1:1 sobre blanco y 4,7:1 sobre niebla. El rojo aguanta texto de 15 px en los dos fondos (5,4:1 y 5,0:1), y el texto blanco sobre rojo da 5,4:1. El filete (1,5:1) es solo para líneas.

## Typography

**Familia:** Onest (con ui-sans-serif, system-ui, sans-serif) para titulares, texto y cifras. Es una fuente variable (100–900) servida desde `public/fonts/onest/onest-latin.woff2`: subconjunto latino, `font-display: swap` y precarga desde el layout.

**Carácter:** una familia con dos voces opuestas. Lo que se afirma va en 800, enorme y apretado; lo que se mide va en 300, rojo y con cifras tabulares, como un monitor. Entre las dos, el texto en 400 y gris, corto y equilibrado (`text-balance` en titulares, `text-pretty` en párrafos).

### Jerarquía
- **Titular del hero** (`display`: 800, clamp(2.75rem, 1.2rem + 6.4vw, 6rem), 1.02, -0.038em): centrado, hasta 15ch. En el detalle de un escenario la misma voz se alinea a la izquierda, topa en clamp(2.75rem, 1.4rem + 5.4vw, 5.5rem) y llega a 16ch.
- **Titular de sección** (`headline`: 800, clamp(2.25rem, 1.35rem + 3.6vw, 4.5rem), 1.04, -0.032em): 36 px en móvil y 72 px en escritorio ancho, hasta 18ch. Dentro del detalle de escenario, los titulares bajan a 1.875rem → 2.25rem (800, -0.03em), y el de la invitación a solicitarlo, centrado, a clamp(2rem, 1.4rem + 2.4vw, 3.25rem).
- **Nombre del equipo protagonista** (`title-producto`: 800, clamp(1.5rem, 0.9rem + 2.4vw, 3rem), 1.04, -0.032em): 24 px en móvil y 48 px en escritorio; es el titular de sección dividido por 1,5 en cualquier ancho. Los demás equipos usan el mismo peso a clamp(1.25rem, 0.8rem + 1.5vw, 2.125rem), con interlineado 1.08 y -0.028em.
- **Título de pieza destacada** (`title-lg`: 700, clamp(1.875rem, 1.4rem + 1.8vw, 3rem), 1.1, -0.025em): la primera pieza de escenarios y de talleres cuando se destaca.
- **Título de pieza** (`title`: 700, 1.5rem, 1.1, -0.025em): las piezas comunes de escenario y taller. Los grupos de la oferta («Talleres y cursos», «Próximos eventos») van en 700 a 1.5rem → 1.75rem; el título de un evento y el nombre de un docente, en 700 a 1.25rem.
- **Subtítulo** (`body-lg`: 400, 1.25rem, 1.625): bajo cada titular de sección, en gris, hasta 36rem; 1.125rem por debajo de 640 px. El del hero sube a 1.375rem y 40rem.
- **Texto** (`body`: 400, 1.125rem, 1.625): descripciones dentro de las piezas, en gris. La pieza común de escenario baja a 1rem y se recorta a tres líneas.
- **Cifra principal** (`cifra-principal`: 300, clamp(4.5rem, 2.6rem + 7vw, 9rem), 1, -0.04em, tabular): la primera cifra cuando hay tres o más.
- **Cifra** (`cifra`: 300, clamp(3.25rem, 2.5rem + 2.6vw, 4.75rem), 1, -0.04em, tabular): las demás cifras. El día de un evento usa la misma voz a 4rem.
- **Etiqueta de dato** (`label`: 600, 13px, 0.14em, en mayúsculas): solo el `dt` de una cifra y de cada dato de contacto, en gris.
- **Botón y enlace** (`boton`: 600, 17px; `enlace`: 600, 15px): acciones. El enlace que acompaña al botón del hero sube a 17 px; «Ingresar», en la cabecera, va a 14 px.

### Reglas
**La regla de las dos voces.** 800 para afirmar, 300 tabular para medir. El resto de la escala sirve a la interfaz: 700 en títulos de pieza, 600 en botones, enlaces y etiquetas, 500 en fichas y capacidades, 400 en el texto. El 200 queda reservado a las iniciales de una credencial sin foto. Nada en 900.

**La regla del producto.** Los nombres de equipo hablan con el peso de los titulares (800) porque son el producto; los títulos de escenarios, talleres, eventos y docentes van en 700.

**La regla del titular corto.** Los titulares de sección son frases de dos a cinco palabras con punto final: «En cifras.», «Practicar sin riesgo.», «Ven a conocerlo.». Una idea por titular.

**La regla de la fuente propia.** Onest se sirve desde el propio servidor y ninguna fuente llega de otro sitio. `PortadaTest` falla si la portada nombra `fonts.googleapis.com` o `fonts.bunny.net`.

## Layout

- **Contenedor:** 1180px, con márgenes laterales de 16, 24 y 32 px (móvil, desde 640 y desde 1024). Los marcos de medios a casi todo el ancho (el video del hero, la foto del detalle de escenario) se abren a 1400px.
- **Ritmo vertical:** cada sección lleva 96, 128 o 144 px arriba y abajo (móvil, desde 768 y desde 1024); en el detalle de escenario, 80 y 112 px. Entre la cabecera de una sección y su contenido, 48 px, y 64 px desde 768.
- **Hero:** blanco y centrado. Titular, subtítulo a 24 px, acciones a 40 px (el botón rojo y el enlace, a 32 px entre sí), el pulso a 48 o 64 px y el video a 24 px.
- **Puntos de quiebre:** 640, 768 y 1024 px, los de Tailwind. Las animaciones distinguen además el cursor fino (`(hover: hover) and (pointer: fine)`) de la pantalla táctil.
- **Aperturas de sección** (`x-portada.seccion`, propiedad `disposicion`):
  - **apilada:** el titular arriba y el contenido debajo, a todo el ancho. Cifras, equipamiento, escenarios, oferta y galería.
  - **lateral:** desde 1024 px, la cabecera a la izquierda y el contenido a la derecha, mitad y mitad (6 + 6 columnas, 48 px entre ellas). Certificaciones y contacto.
  - **centrada:** titular, subtítulo y contenido centrados. Docentes, y certificaciones cuando no hay docentes.
- **Rejillas bento**, todas con 16 px de separación:
  - Cifras: 1, 2 y 4 columnas. Con tres o más, la primera ocupa 2 × 2 desde 1024 px (26rem de alto como mínimo), y los tramos de las demás están calculados para no dejar huecos de 1 a 6 cifras.
  - Equipamiento: el protagonista arriba, con nombre y frase en 7 columnas y la ficha en 5 desde 1024 px, alineados arriba; debajo, los demás equipos en 2 columnas desde 768 px, y si quedan impares el último ocupa la fila.
  - Escenarios: 1, 2 y 3 columnas. Con más de dos, el primero se destaca (2 columnas, y también 2 filas si tiene foto) y el último se estira para cerrar su fila en 2 y en 3 columnas. Si en escritorio queda solo en su fila, pone el texto a la izquierda y las capacidades a la derecha.
  - Oferta: filas flexibles en las que cada pieza crece hasta cerrar la suya (base de 100 %, 50 % desde 640 y 25 % desde 1024; el primer taller destacado, al 50 % desde 1024).
  - Galería: solo fotos, en 2 columnas también en móvil y 4 desde 1024 px, con filas de 11rem (15rem desde 640). La primera foto ocupa 2 × 2 y la última cierra su fila.
  - Docentes: credenciales de ancho fijo (100 %, 50 % desde 640 y 25 % desde 1024) en filas centradas.
- **Medidas de lectura:** subtítulos hasta 36–40rem; descripciones hasta 42–46ch; la frase de un equipo, hasta 30–34ch.
- **Detalle de escenario:** cabecera blanca alineada a la izquierda con el enlace de vuelta, la foto en su marco, capacidades y equipo en dos columnas desde 1024 px, la invitación a solicitarlo centrada sobre niebla y los otros escenarios en la rejilla de 1, 2 y 3 columnas.

### Reglas
**La regla del ritmo de aperturas.** Cómo abre cada sección lo decide el ritmo de la página, nunca la cantidad de piezas, y la asignación es fija por sección: el hero centrado; las secciones de contenido (cifras, equipamiento, escenarios, oferta, galería) apiladas; y el cierre alterna certificaciones en lateral, docentes centrada y contacto en lateral, para que dos aperturas iguales no se sigan al final. Si no hay docentes, certificaciones abre centrada por el mismo motivo.

**La regla del borde compartido.** En una sección lateral, el contenido de la columna derecha arranca en el borde izquierdo de esa columna: las insignias y la lista de contacto comparten ese borde. Nada se centra dentro de la columna.

**La regla de la fila cerrada.** Ninguna fila queda con un hueco a un lado. En las rejillas apiladas, la primera pieza se destaca y la última se estira hasta cerrar su fila; en la sección centrada, las piezas conservan su ancho y la fila incompleta se centra.

**La regla de la sección presente.** Una sección sin contenido no se pinta, y los fondos se recalculan sobre las que quedan: dos secciones vecinas nunca comparten color.

## Elevation & Depth

Plana por defecto. La profundidad es tonal: una pieza niebla sobre una sección blanca, o blanca sobre niebla, y dentro de la pieza, marcos y etiquetas en el tono contrario. En reposo no hay sombras. Los únicos contornos son filetes de 1 px (en el color filete, o en negro al 6 % bajo la cabecera) y el anillo de los sellos y del control del video. El único degradado del sistema es el brillo que cruza los sellos una vez (animación 5), invisible en reposo.

### Vocabulario de sombras
- **Anillo** (`box-shadow: 0 0 0 1px rgb(0 0 0 / 0.06)`): el contorno de los discos de las insignias y del botón de pausa del video, para que no se disuelvan sobre un fondo de su mismo tono.
- **Elevación de la credencial** (`box-shadow: 0 24px 40px -24px rgb(0 0 0 / 0.45)`): solo bajo la foto de una credencial mientras se eleva (animación 6), con cursor fino. Vive en una capa propia cuya opacidad se anima, nunca la sombra misma. Esa capa no puede quedar dentro de un contenedor con `overflow: hidden`: el recorte la borra.

### Reglas
**La regla de la vitrina plana.** Nada proyecta sombra en reposo, y nada usa vidrio ni desenfoque. La única sombra con cuerpo es la de la credencial, y aparece como respuesta al cursor o al foco.

## Shapes

- **Esquina de pieza** (28px): toda pieza de una rejilla (cifra, equipo, escenario, taller, evento, credencial) y los marcos de medios a casi todo el ancho: el video del hero, la foto del equipo protagonista y la foto del detalle de escenario.
- **Esquina de marco** (20px): la foto dentro de una pieza, a 8 px de su borde, y las fotos de la galería, que son piezas solo de imagen.
- **Pastilla:** botones, etiquetas de capacidad, el botón del menú (40 px), el control del video (44 px) y el enlace «Saltar al contenido».
- **Círculos:** los discos de las insignias (128 px, 176 px desde 640), con el círculo interior del sello a 12 px del borde, y los puntos de estado de un evento (8 px).
- **Trazo corto rojo:** 2 px de alto; 12 px en cada fila de una ficha, a la altura del texto, y 24 px bajo las siglas de un sello. Es el único adorno geométrico.
- **Recorte:** piezas y marcos recortan su contenido para que la foto tome la esquina. Las fotos cubren su marco; los logos de las insignias se contienen, con 12–16 px de aire y fusión multiplicar para que el blanco de un JPG se funda con el disco.
- **Proporciones:** video del hero y foto del detalle, 4:3 y 16:9 desde 640; foto del protagonista, 4:5, 16:10 desde 640 y 2:1 desde 1024; fotos de pieza, 16:10 (escenario, taller) o 4:3 (equipo); credencial, 4:5.

### Reglas
**La regla concéntrica.** Lo que va dentro de una pieza de 28 px se separa 8 px de su borde y lleva 20 px de esquina (28 − 8 = 20). Un marco interior con la misma esquina que su pieza se ve torcido.

## Components

### Botones
Pastillas llenas y seguras, que cambian de color y nunca de forma.
- **Forma:** pastilla.
- **Primario:** rojo con texto blanco, 600 a 17 px y 14 × 28 px de relleno («Conoce los escenarios», «Solicitar el escenario»). En la cabecera, «Ingresar» es la versión compacta, 14 px y 8 × 16 px, y está siempre visible.
- **Hover y foco:** el fondo pasa a rojo hondo en 150 ms. El foco visible es un contorno rojo de 2 px separado 4 px. No se mueven, no crecen, no proyectan sombra.
- **Enlace de texto:** la acción secundaria. Rojo, 600 a 15 px (17 px junto al botón del hero), con un chevrón en SVG de 0,7 em; se subraya a 4 px al pasar el cursor. Nunca hay dos botones rellenos juntos.
- **Botón de icono:** círculo blanco de 44 px con anillo e ícono de 16 px en tinta, que pasa a niebla al pasar el cursor. Es la pausa del video del hero.

### Etiquetas de capacidad
- **Estilo:** pastilla en el tono contrario a su pieza (`--pieza-inversa`), sin borde, 500 a 13 px, 4 × 12 px. En el detalle de escenario crecen a 17 px y 8 × 16 px, sobre niebla.
- **Estado:** son informativas: sin hover ni selección.

### Piezas
Bloques grandes y callados: el contenido manda y la pieza solo lo agrupa.
- **Esquina:** 28 px.
- **Fondo:** `var(--pieza)`, nunca un color fijo.
- **Sombra:** ninguna (ver Elevation & Depth).
- **Borde:** ninguno.
- **Relleno interno:** 28 px; 40 px desde 640 en las piezas destacadas y en los equipos; 32 px en las cifras desde 640.
- **Foto:** en un marco de 20 px a 8 px del borde, sobre `var(--pieza-inversa)`; arriba en escenarios y talleres, abajo en equipos.
- **Pieza enlace:** la de escenario es entera el enlace a su detalle. Al pasar el cursor solo se subraya «Ver el escenario», y el foco rodea toda la pieza.
- **Sin foto:** la pieza queda de solo texto y se ve terminada. La destacada de escenarios ocupa entonces 2 celdas en lugar de 4.

### Navegación
- Cabecera blanca de 64 px con filete inferior en negro al 6 %, sin fijar al hacer scroll. A la izquierda, el símbolo UFPS (32 px) y el nombre del laboratorio (600, 15 px); la línea de la facultad (12 px, gris) se omite en móvil, y el nombre parte en dos líneas antes que cortarse con puntos suspensivos.
- Desde 1024 px, los enlaces de las secciones presentes (14 px, tinta al 80 % que pasa a tinta) y «Ingresar» en rojo.
- Por debajo de 1024 px, un `<details>`: botón circular de 40 px y un panel blanco a todo el ancho con enlaces de 24 px (600) separados por filetes. Abre sin animación, funciona sin JavaScript y se cierra al elegir una sección.
- «Saltar al contenido»: pastilla de tinta con texto blanco que aparece arriba a la izquierda al enfocarla.

### Pie
Niebla con filete superior (el filete al 70 %), para separarse aunque la última sección también sea niebla. Lleva el logo horizontal oficial con «Vigilada Mineducación» (256 px, 288 desde 640, en multiplicar), textos de 14 y 12 px en gris y «Ingresar a la plataforma» en tinta.

### Video del hero
Con video, el hero es el video: ocupa la pantalla entera bajo la cabecera, de fondo, con un velo negro al 45 % y el titular, el subtítulo y las acciones encima, en blanco, arriba a la derecha (titular de 36 a 68 px). Decisión del usuario del 10-oct-2026: el video es lo central y el texto le deja el espacio. Es contenido, no una de las seis animaciones: en silencio y en bucle, arranca solo si no se pidió reducir el movimiento ni ahorrar datos, se detiene fuera de pantalla y siempre muestra el botón para pausarlo o reanudarlo. Sin JavaScript no arranca: muestra su primer cuadro. El pulso pasa a cerrar el hero sobre blanco, porque rojo sobre video no se lee. Sin video, el hero es blanco con el texto oscuro y el pulso debajo.

### Línea de pulso
La firma clínica. Tres trazos de 2 px en rojo y 80 px de alto: dos rectas que se estiran con la ventana y un latido de 168 px de ancho fijo que nunca se deforma. Es decorativa (`aria-hidden`). Aparece en dos sitios y en ninguno más: bajo las acciones del hero, de borde a borde de la ventana, dibujándose una vez (animación 1); y estática, al 30 %, sobre la lectura de la cifra principal, de borde a borde de su pieza.

### Cifra de monitor
Pieza de la rejilla de cifras: la etiqueta de dato arriba y la lectura abajo, separadas por el alto de la pieza (144 px como mínimo, 176 desde 640). La lectura reserva su ancho final para que el conteo no desplace nada, y los lectores de pantalla oyen el valor real (animación 2).

### Ficha de características
Las frases cortas de un equipo, como una ficha técnica: filas de 500 a 17 px (18 desde 640) separadas por filetes arriba y abajo, cada una con su trazo corto rojo. La usan el equipo protagonista, los demás equipos y el detalle de escenario («Con qué equipo.»).

### Equipo protagonista
El primero del equipamiento destacado, presentado como producto: su nombre en la voz del producto, su frase en gris (18 px, 24 desde 640, hasta 30ch) y la ficha al lado, alineados arriba; debajo, la foto en un marco de 28 px a casi todo el ancho (animación 4). Sin foto, nombre, frase y ficha bastan.

### Sello de certificación
Un disco plano (128 px, 176 desde 640) en `var(--pieza)` con anillo. Con imagen, el logo contenido. Sin imagen, un sello: doble filete (el anillo y un círculo interior a 12 px), las siglas de la entidad (600, 1.625rem → 2rem, 0.06em, hasta 4 letras y sin conectores: «American Heart Association» da AHA) y el trazo rojo de 24 px. Debajo, el nombre (600, 15 px) y la entidad (14 px, gris). Animación 5.

### Credencial de docente
Pieza de 28 px con 8 px de relleno y la foto en un marco 4:5 de 20 px. Sin foto, las iniciales (200, 5rem, tinta al 30 %). Debajo, el nombre (700, 20 px), el cargo (15 px, gris) y la lista de títulos con filete superior (título en 500 a 15 px, institución en 14 px y gris). Se puede enfocar con el teclado. Animación 6.

### Evento
Pieza de 28 px con 28 px de relleno, en la que manda la fecha: el día como cifra de monitor (4rem), el mes (600, 15 px) y el año en gris. Luego el título, el tipo y el estado: un punto rojo de 8 px con «Abierto al público», o un punto con filete gris con «Para la comunidad universitaria».

### Movimiento: las seis animaciones
Pocas y bien hechas, acordadas con el usuario. `resources/css/app.css` y `resources/js/portada.js` las numeran igual que esta lista.

1. **Titular y pulso del hero.** Al cargar la página. El titular pasa de opacity 0 y translateY(24px) a su sitio en 700 ms con la curva de salida. El pulso se traza una vez con stroke-dashoffset, de 1 a 0, a velocidad constante, como el barrido de un monitor: la recta izquierda en 560 ms desde los 300 ms, el latido en 320 ms desde los 860 ms y la recta derecha en 560 ms desde los 1180 ms; termina a 1,74 s. Es CSS puro y no depende de portada.js. El pulso es la única excepción a transform y opacity.
2. **Cifras que cuentan.** Cuando la cifra está visible en un 60 %. El texto cuenta desde cero hasta su valor en 1400 ms, con salida cuártica (1 − (1 − t)⁴). Solo cuentan los enteros, con signo, unidad o separador de miles («+700», «1.200», «98 %»); «4,5» o «24/7» se quedan quietos. Cambia el texto, no la geometría.
3. **Revelado al hacer scroll.** Las cabeceras de sección y las piezas, una sola vez, cuando entran un 8 % por encima del borde inferior de la ventana: opacity de 0 a 1 y translateY de 12 px a 0 en 300 ms con la curva de salida, escalonadas 40 ms hasta dos pasos. Ninguna termina después de 380 ms.
4. **Acercamiento del equipo protagonista.** Desde 768 px, la foto escala de 1 a 1,08 a medida que su marco entra en pantalla (41 umbrales de IntersectionObserver, suavizados con 160 ms lineales) y conserva el acercamiento al salir por arriba. Por debajo de 768 px, solo un fundido de opacidad de 300 ms.
5. **Brillo y giro de los sellos.** Cuando el sello está visible en un 60 %, una banda blanca al 90 %, inclinada a 105°, lo cruza una vez: translateX de −70 % a 70 % en 1100 ms con la curva de recorrido, escalonada 90 ms por sello hasta el sexto. Con cursor fino, el disco se inclina siguiendo el cursor (perspectiva de 700 px, hasta ±5° por eje) y vuelve al soltarlo, en 400 ms con la curva de salida.
6. **Credenciales.** Con cursor fino, al pasar el cursor o al llegar con el teclado: la foto sube 6 px en 300 ms con la curva de salida y su sombra aparece por opacidad; los títulos, en una placa blanca sobre el pie de la foto, pasan de opacity 0 y translateY(12px) a −6 px en 250 ms, 60 ms después. En pantallas táctiles, y con movimiento reducido, los títulos van debajo del cargo y se ven siempre.

**Curvas.** La de salida, cubic-bezier(0.23, 1, 0.32, 1), arranca rápido y frena largo, y es la de todo lo que llega (en Tailwind, `ease-llegada`). La de recorrido, cubic-bezier(0.77, 0, 0.175, 1), es para lo que cruza la pantalla. La lineal, para el trazo del pulso.

**La regla de las seis.** Exactamente estas seis. Ninguna otra sin consultarlo con el usuario, ni siquiera un hover que desplace, escale o gire algo. Los cambios de color de 150 ms en botones y enlaces no mueven nada y no cuentan; el menú móvil abre sin animación.

**La regla del contenido completo.** Solo transform y opacity (salvo el trazo del pulso y el texto del conteo), con CSS e IntersectionObserver y sin librerías. `prefers-reduced-motion` las apaga todas. Lo que se revela al hacer scroll solo empieza oculto bajo `html.animar`, que el layout pone antes de pintar si hay IntersectionObserver y no se pidió reducir el movimiento, y que retira a los 2,5 s si portada.js no avisó que está listo (`data-portada-lista`). Las animaciones 1 y 6 son CSS puro; de la 2 a la 5 necesitan además `html.animar`. Sin JavaScript, sin IntersectionObserver o con movimiento reducido, la página se ve completa y en su estado final.

## Do's and Don'ts

### Haz:
- **Usa** el rojo (#d30f23) solo en botones, enlaces, cifras, el pulso y los trazos cortos; el rojo hondo (#ad0c1c), solo en el hover de un botón relleno.
- **Alterna** blanco y niebla entre las secciones que se pintan, y deja que las piezas lean `var(--pieza)` y `var(--pieza-inversa)`.
- **Escribe** los titulares de sección como frases de dos a cinco palabras con punto final, en Onest 800 con interletrado negativo y `text-balance`.
- **Pon** las cifras en Onest 300, rojas, tabulares y con interlineado 1, y reserva su ancho final.
- **Mantén** los radios concéntricos: pieza de 28 px, margen de 8 px, marco de 20 px; botones y etiquetas en pastilla.
- **Cierra** cada fila: en las rejillas apiladas, con la primera pieza destacada y la última estirada; en la sección centrada, con la fila incompleta centrada. Una columna en móvil y de 2 a 4 desde 640 px.
- **Elige** la apertura de cada sección (apilada, lateral o centrada) por el ritmo de la página.
- **Alinea** el contenido de una sección lateral al borde izquierdo de su columna.
- **Diseña** el estado sin foto de cada módulo: siglas en el sello, iniciales en la credencial, piezas de solo texto.
- **Sirve** fuentes, logos e imágenes desde el propio servidor (`public/fonts`, `public/marca`, `/storage`).
- **Comprueba** cada estado oculto contra `html.animar` y `prefers-reduced-motion`: sin ellos, la página se ve completa.
- **Da** foco visible a todo lo que se pulsa: un contorno rojo de 2 px, separado 4 px.

### No hagas:
- **No** agregues una séptima animación, ni siquiera un hover que desplace, escale o gire algo, sin consultarlo con el usuario.
- **No** uses el rojo como fondo de una sección o de una pieza, ni en una superficie mayor que un botón en pastilla.
- **No** uses gradientes morados ni degradados decorativos, tarjetas idénticas con ícono arriba, glassmorphism ni desenfoques, emojis, sombras exageradas ni textos de relleno.
- **No** quites el velo del video: sin él, el texto blanco no se lee sobre un cuadro claro.
- **No** pongas rótulos en mayúsculas encima de un titular: las mayúsculas espaciadas (13 px, 0,14 em) son solo para etiquetas de datos (`dt`).
- **No** fijes el color de una pieza en blanco o niebla: sobre el fondo equivocado desaparece.
- **No** uses el filete (#d2d2d7) para texto ni aclares el gris (#6e6e73): sobre niebla ya está en 4,7:1.
- **No** pongas la línea de pulso fuera del hero y de la cifra principal.
- **No** decidas la apertura de una sección por la cantidad de piezas.
- **No** dejes celdas vacías en una rejilla ni marcos de foto vacíos.
- **No** proyectes sombras en reposo.
- **No** cargues fuentes, scripts ni imágenes de otro sitio: la Content-Security-Policy de `CabecerasDeSeguridad` bloquea scripts e imágenes de otros orígenes, y `PortadaTest` falla si la portada pide una fuente a Google o a Bunny.
