# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Público de la portada (`/`).** Aspirantes y estudiantes de los programas de la Facultad de Ciencias de la Salud de la UFPS (Enfermería, Licenciatura en Ciencias Naturales, Seguridad y Salud en el Trabajo, Regencia en Farmacia), profesionales de la salud que buscan talleres y cursos, y visitantes institucionales (pares académicos, acreditación, directivos). Entran desde el celular o el computador, muchas veces con conexión lenta.
- **Usuarios internos.** Unos 700 estudiantes y 150 docentes, más administrativos, coordinación y el administrador de la plataforma. Para ellos la portada es la puerta de entrada al panel (`/acceso`, solo con la cuenta institucional de Google).

## Product Purpose

Plataforma web del Laboratorio de Simulación Clínica de la Facultad de Ciencias de la Salud (UFPS). Por dentro gestiona solicitudes de escenario, preparación, inventario, evaluaciones y el formato de confidencialidad. Por fuera, la portada muestra lo que el laboratorio tiene y hace: sus escenarios clínicos, sus simuladores, la sala inmersiva, la mesa de anatomía virtual, sus cifras, su oferta académica (talleres y eventos), sus certificaciones y sus docentes.

Éxito de la portada: que quien entra entienda en segundos que el laboratorio es de primer nivel, y sepa dónde ver los escenarios y la oferta académica, o cómo ingresar a la plataforma.

## Positioning

Lo que ninguna otra página puede copiar es el propio laboratorio: su equipamiento, sus escenarios, sus cifras y su gente, cargados por el ADMIN y no escritos en el código.

## Operating Context

- Todo el contenido público lo gestiona el ADMIN desde Filament en `/admin` (RF10–RF17): textos del hero, video, contacto, cifras, galería, escenarios, talleres, eventos, certificaciones, docentes y el equipamiento destacado.
- Las imágenes que sube el ADMIN pasan por `ImagenPublicaService`: WebP de 1600 px de lado mayor. El video del hero es MP4 o WebM de hasta 15 MB.
- Producción en el servidor institucional, en Docker, con una Content-Security-Policy estricta: nada se carga de otros sitios salvo lo declarado en `CabecerasDeSeguridad`.

## Capabilities and Constraints

- Requerimientos de la portada: RF01–RF09; los del ADMIN: RF10–RF17 (`docs/requerimientos.md`).
- RNF05 (celular y computador), RNF08 (Chrome, Firefox y Edge) y RNF10 (conexiones lentas y equipos de gama baja).
- Interfaz en español de Colombia.
- Sin servicios ni dependencias de terceros nuevos: las fuentes y los logos se sirven desde el propio servidor.
- Abierto: la galería de videos (RF08) espera la subida de videos al servidor (RF17); el formulario de información por taller (RF09) no está construido.

## Brand Commitments

- Universidad Francisco de Paula Santander (UFPS), Facultad de Ciencias de la Salud. Logos oficiales en versión cuadrada y horizontal, entregados por el usuario el 9 de octubre de 2026; el horizontal incluye «Vigilada Mineducación».
- Rojo institucional `#d30f23`, usado como acento.
- Tipografía Onest (Google Fonts), elegida por el usuario.
- El vocabulario del dominio del `CLAUDE.md`: escenario clínico, sala, simulador, solicitud, formato de confidencialidad.

## Evidence on Hand

- No hay fotos reales todavía: las subirá el ADMIN. La portada tiene que verse completa sin ellas.
- Los datos de `DatosPruebaSeeder` son de prueba. No se inventan cifras, certificaciones, testimonios ni nombres reales: lo que la portada muestra sale de lo que registre el ADMIN.

## Product Principles

1. Mostrar, no afirmar: el equipamiento y los escenarios del laboratorio son la prueba.
2. Todo lo que se ve en la portada lo puede cambiar el ADMIN sin programar.
3. Rápida en conexiones lentas, y completa aunque el JavaScript no cargue.
4. Clara para el mantenedor: Blade, Tailwind y un JavaScript mínimo, sin paquetes nuevos.

## Accessibility & Inclusion

Meta WCAG 2.1 AA: contraste, navegación por teclado y animaciones desactivadas con `prefers-reduced-motion`.
