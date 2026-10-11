---
version: 1
slug: "resources-views-layouts-panel-blade-php"
primary_target: "resources/views/layouts/panel.blade.php"
related_targets: ["resources/views/livewire/solicitud/bandeja-revision.blade.php","resources/views/panel/solicitudes/participantes.blade.php","resources/views/components/tarjeta.blade.php","resources/views/auth/acceso.blade.php"]
---

## Scope

Panel interno (`/panel`): el armazón, la pantalla «Hoy» por rol, el expediente de cada solicitud y las demás pantallas Livewire (preparación, inventario, reposición, formatos de confidencialidad, evaluaciones, bloqueos, periodo académico, usuarios, bitácora, reportes, calendario), más las pantallas de acceso. A `/admin` (Filament) solo se le aplica la fuente y el rojo de la marca. Modo: **Operate**.

## Audience, task, states

- Administrativo: desde el celular, en el laboratorio, minutos antes de la clase: escenarios de hoy, sala, alistamiento; inventario; formatos que recibe en la puerta.
- Coordinación (y el ADMIN si coordinación no está): revisar y aprobar o rechazar solicitudes; verificar formatos; bloqueos, reportes, bitácora.
- Docente: solicitar escenarios, ver sus sesiones, gestionar participantes, evaluar.
- Estudiante: sus prácticas, sus resultados y su formato.
- Estados que importan: pendiente, revisada, aprobada, rechazada, finalizada; preparación por montar, montando, lista; formato pendiente, cargado, verificado, entrega física; bloqueos; vigencias de rol.

## Constraints

- Respuestas del usuario (11-oct-2026): panel completo y marca en `/admin`; misma marca que la portada pero compacto, cómodo en el celular; pantalla «Hoy» por rol con lo pendiente, solo con datos que ya existen y con sus tests; estructura elegida: «Expediente por solicitud».
- Reglas del `CLAUDE.md`: lo que se ve lo decide la Policy, nunca un condicional de rol; lógica en Services; móvil primero; sin dependencias nuevas; Tailwind utilitario; español del dominio.
- Sin animaciones decorativas: solo transiciones de estado de 150–250 ms, apagadas con `prefers-reduced-motion`.

## Direction contract

THESIS: El panel es el expediente del laboratorio: cada solicitud se abre como una historia clínica, con su línea de estados y sus pestañas, y las decisiones se toman ahí. Rechaza el tablero genérico de tarjetas grises con un acento azul y botones sueltos en cada fila.

OWN-WORLD: Onest en todo. Riel de navegación en tinta `#1d1d1f` con texto blanco; la sección activa, marcada en rojo `#d30f23`. Fondo niebla `#f5f5f7`, piezas blancas planas de 16 px sin sombra, filetes `#d2d2d7`. Botones en pastilla: primario tinta, peligro en rojo. Estados semánticos: verde aprobado, ámbar en curso, rojo rechazado, gris pendiente. Cifras tabulares. Movimiento propio: la línea de estados, un trazo fino con nodos (pasados en tinta, el actual en rojo, los que faltan huecos en gris), en el expediente y en miniatura en cada fila.

STORY: Quien entra ve primero «Hoy»: la ronda del día por hora y lo que espera su decisión. Abre una solicitud, entiende en su expediente en qué punto está, qué falta y quién va, y decide ahí mismo.

FIRST VIEWPORT: Escritorio: riel tinta de 248 px a la izquierda (símbolo UFPS y nombre; secciones del rol; al pie, el usuario, su rol y Salir). A la derecha, la cabecera de página con título de 28 px y la acción principal. En el expediente: banda blanca con el escenario, fecha y hora, docente, materia y estudiantes; debajo, la línea de estados con el paso actual en rojo; pestañas Detalle · Participantes · Novedades · Formato intramural; el contenido y, al pie, Rechazar y Aprobar. Móvil: barra tinta arriba con el menú; el expediente se apila en una columna.

FORM: «Expediente por solicitud», posición 7 de 7 en mi lista ordenada; clave de la tirada b4aa8250 (tirada reducida, sin retadores).

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Memorable moment

La línea de estados del expediente: de un vistazo, dónde está la solicitud y qué le falta.

## Unresolved

- Ninguno por ahora.
