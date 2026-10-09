# Matriz de trazabilidad

Estado de cada requerimiento según lo que existe en el código, no según lo que dicen los documentos.

**Corte:** 9 de octubre de 2026, rama `main` en `fe3e6a3` (incluye la PR #43, configuración de producción).

**Enunciados:** `docs/requerimientos.md`, versión del 9 de octubre de 2026. Es la primera vez que la matriz se coteja contra el enunciado real; las versiones anteriores tuvieron que deducir qué cubría cada número a partir del código.

## Cómo se hizo

- Por cada RF y RNF se leyó el enunciado y se buscó su código en `app/`, `resources/`, `routes/`, `database/` y `tests/`. Después se abrió el Service y la pantalla, y se comprobó que un test los ejercite.
- Las pantallas del ADMIN están en `/admin` (Filament) y las operativas en `/panel` (Livewire). Los tests están en `tests/Feature/`, salvo que se diga otra cosa.
- La columna **Falta** dice qué le falta al código para cumplir el enunciado. Las preguntas que hay que responder antes de construir algo van en [Preguntas abiertas](#preguntas-abiertas), con su número (P1, D1…).

## Estados

| Estado | Significa |
|---|---|
| **Completo** | Hay Service o recurso, pantalla y tests, y el enunciado se cumple de punta a punta. |
| **Solo backend** | Service y tests completos, sin pantalla: nadie lo puede usar todavía. |
| **Parcial** | Una parte está hecha y probada, otra falta. La columna Falta dice qué. |
| **Bloqueado** | No se puede terminar sin un dato o una respuesta externa. La columna Falta dice cuál. |
| **No iniciado** | No hay código, pero nada impide empezarlo (salvo una pregunta P, si se indica). |

## Resumen

| Estado | RF | RNF |
|---|---:|---:|
| Completo | 31 | 4 |
| Solo backend | 8 | 0 |
| Parcial | 14 | 6 |
| Bloqueado | 10 | 0 |
| No iniciado | 12 | 0 |
| **Total** | **75** | **10** |

De los 10 bloqueados, 9 son la landing pública (P1, el diseño) y 1 es la sincronización con la base institucional (dato externo). Todo lo demás se puede construir hoy.

---

## Landing pública (RF01–RF09)

`/` sirve `resources/views/welcome.blade.php`, que es **la página de ejemplo de Laravel**: título "Laravel" y una imagen cargada desde laravel.com, que además bloquea la Content-Security-Policy. Los datos que la landing va a mostrar sí existen y los gestiona el ADMIN (RF10–RF17).

| RF | Pide | Estado | Hecho | Falta |
|---|---|---|---|---|
| RF01 | Información institucional, cifras destacadas y galería de fotos | Bloqueado | Datos: RF10 | La vista pública. Espera P1 (diseño) |
| RF02 | Video institucional de fondo en el hero | Bloqueado | Datos: RF11 | Igual que RF01 |
| RF03 | Escenarios clínicos con sus capacidades, resumen y detalle ampliado | Bloqueado | Datos: RF12 | Igual que RF01 |
| RF04 | Talleres con imagen, tema, fecha y modalidad | Bloqueado | Datos: RF13 | Igual que RF01 |
| RF05 | Eventos con fecha, tipo e indicador de abierto al público | Bloqueado | Datos: RF14 | Igual que RF01 |
| RF06 | Certificaciones como insignias | Bloqueado | Datos: RF15 | Igual que RF01 |
| RF07 | Docentes con foto, nombre, cargo y títulos | Bloqueado | Datos: RF16 | Igual que RF01 |
| RF08 | Galería de videos institucionales | Bloqueado | — | Igual que RF01, y los datos de RF17. Ya no espera la decisión YouTube o Vimeo: el enunciado fija subida al servidor |
| RF09 | Formulario de información por taller, enviado desde el correo no-reply; la sección se puede ocultar | Bloqueado | Tabla `solicitudes_informacion` y modelo `SolicitudInformacion`, sin uso | El formulario (P1), su Service, el correo (D8 y la cuenta de correo, ver [Bloqueos externos](#bloqueos-externos)) y el interruptor para ocultar la sección en la configuración de la landing |

## Contenido público: pantallas del ADMIN (RF10–RF17)

Todas las imágenes pasan por `ImagenPublicaService` (validación, orientación EXIF, reducción y WebP; `ImagenPublicaServiceTest`).

| RF | Pide | Estado | Dónde | Tests | Falta |
|---|---|---|---|---|---|
| RF10 | Información institucional, cifras y galería de fotos | Completo | `/admin/galeria`, `/admin/estadisticas` | `PantallaGaleriaTest`, `PantallaEstadisticasTest` | |
| RF11 | Video de fondo | Completo | `ConfiguracionLandingService`, `/admin/configuracion-de-la-landing` | `ConfiguracionLandingTest` | |
| RF12 | Escenarios publicados con imagen, descripción, capacidades y equipo | Completo | `/admin/casos-clinicos` | `PantallaCasosClinicosTest` | |
| RF13 | Talleres | Completo | `/admin/talleres` | `PantallaTalleresTest`, `tests/Unit/EnumsTest` | |
| RF14 | Eventos y catálogo de tipos de evento | Completo | `/admin/eventos`, `/admin/tipos-de-evento` | `PantallaEventosTest`, `TiposDeEventoTest` | |
| RF15 | Certificaciones | Completo | `/admin/certificaciones` | `PantallaCertificacionesTest` | |
| RF16 | Docentes y títulos | Completo | `/admin/perfiles-docentes` | `PantallaPerfilesDocentesTest` | |
| RF17 | Galería de videos subidos al servidor: MP4, título, portada, orden, tamaño máximo configurable | No iniciado | Tabla `videos_institucionales` (con `url`, sin archivo ni portada) | Migración nueva (archivo, portada), recurso Filament, tope configurable que quepa en `php.ini`, nginx y `config/livewire.php` (como el video del hero), borrado del archivo reemplazado. Tamaño por defecto: D7 |

## Acceso, usuarios y estructura académica (RF18–RF26)

| RF | Pide | Estado | Dónde | Tests | Falta |
|---|---|---|---|---|---|
| RF18 | Entrada solo con Google institucional, restringida al dominio | Completo | `AccesoService`, `/acceso` | `AccesoConGoogleTest`, `AutenticacionTest`, `AccesoDeDesarrolloTest` | Probarlo con las credenciales reales (despliegue) |
| RF19 | Acceso solo a personas vigentes de los cuatro programas, cruzando matrícula y contratación | Parcial | `AccesoService::puedeEntrar()`, middleware `VerificarUsuarioActivo` | `VigenciaInstitucionalTest` | Hecho: el inactivo no entra y se le corta la sesión, también en Livewire. Falta el cruce que pone `users.estado`, que es RF20. Tampoco hay columna de programa en `users` |
| RF20 | Copia periódica de la base institucional con usuario de solo lectura, sin borrar, con freno ante desactivaciones masivas | Bloqueado | — (`UsuarioSyncService` no existe) | — | Motor, acceso y estructura de la base institucional (ver [Bloqueos externos](#bloqueos-externos)). **Se puede construir hoy con datos simulados**, como dice el propio enunciado: comando programado, frecuencia y umbral configurables, aviso al ADMIN. Solo el mapeo final de columnas espera el dato. Requiere además un servicio de tareas programadas en el compose de producción, que hoy no existe |
| RF21 | Selector de rol | Completo | `App\Support\RolActivo`, cabecera del panel | `SelectorDeRolTest`, `RolActivoEnLivewireTest`, `ComponentesAutorizanEnCadaPeticionTest` | |
| RF22 | Crear, actualizar y deshabilitar usuarios y asignarles roles | Parcial | `AsignacionDeRolService`, `/panel/usuarios` | `RolesConVigenciaTest` | Hecho: asignar y revocar roles. Falta: crear, editar y deshabilitar usuarios. Cómo convive deshabilitar con la sincronización: D6 |
| RF23 | Materias con su semestre | Completo | `/admin/materias` | `PantallaMateriasTest` | |
| RF24 | Tipos de caso clínico y sus materias | Completo | `/admin/casos-clinicos` | `PantallaCasosClinicosTest` | |
| RF25 | Simuladores y equipos de cada caso, con cantidades | Completo | `/admin/casos-clinicos` (`ItemNecesarioDelCaso`) | `PantallaCasosClinicosTest` | |
| RF26 | Tipos de evaluación con materias y checklist de al menos un ítem; el desactivado no se usa | Completo | `/admin/tipos-de-evaluacion`, `EvaluacionService` (`tipoInactivo`) | `PantallaTiposEvaluacionTest`, `EvaluacionReglasTest` | |

Las salas tienen pantalla (`/admin/salas`, `PantallaSalasTest`); su RF es el RF65.

## Solicitudes de escenario (RF27–RF35)

| RF | Pide | Estado | Dónde | Tests | Falta |
|---|---|---|---|---|---|
| RF27 | El docente solicita, con tipo práctica o evaluación | Completo | `SolicitudService::crear()`, `/panel/solicitudes/nueva` | `FlujoSolicitudTest`, `PantallaSolicitudDocenteTest` | |
| RF28 | Caso, fecha y hora, materia, **grupo** y cantidad; sin sala; fecha del registro previo en sesiones apartadas | Parcial | `SolicitudService::crear()` | `FlujoSolicitudTest`, `PantallaSolicitudDocenteTest` | No existe el grupo (letra A, B, C…): ni columna ni campo. La fecha desde el registro previo depende de RF57 |
| RF29 | Precarga del inventario del caso | Completo | `SolicitudService::itemsSugeridos()` | `PantallaSolicitudDocenteTest`, `FlujoSolicitudTest` | |
| RF30 | Revisión administrativa obligatoria **y rechazo por los administrativos** | Parcial | `SolicitudService::marcarRevisada()`, `/panel/solicitudes` | `FlujoSolicitudTest`, `PantallaBandejaSolicitudesTest` | Hoy solo rechaza coordinación (`SolicitudPolicy::rechazar()`), como dice la regla 9 del `CLAUDE.md`, que el enunciado cambió. La bandeja avisa de faltantes de inventario, pero no de cruces de horario ni del estado del espacio físico (las salas no tienen campo para eso). Si el ADMIN rechaza: D1 |
| RF31 | Aprobación de coordinación o del ADMIN, siempre con revisión previa; sin bloqueo de autoaprobación | Completo | `SolicitudService::aprobar()` | `FlujoSolicitudTest`, `SolicitudPolicyTest` | Cierra el pendiente 1 del `CLAUDE.md`. Queda borrar el bloque comentado de `SolicitudPolicy::aprobar()` |
| RF32 | Comentario opcional al rechazar | Completo | `SolicitudService::rechazar()` | `FlujoSolicitudTest`, `PantallaBandejaSolicitudesTest` | |
| RF33 | Correo al docente con el resultado | Completo | Eventos `SolicitudAprobada`/`SolicitudRechazada`, listener en cola | `FlujoSolicitudTest`, `ListenersRegistradosTest` | |
| RF34 | Calendario de aprobadas para estudiantes, docentes, administrativos y coordinación, con la sala cuando exista | Completo | `SolicitudService::paraCalendario()`, `/panel/calendario` | `CalendarioSolicitudesTest`, `PantallaCalendarioTest` | |
| RF35 | Historial del docente con estado, sala y motivo | Completo | `SolicitudService::historialDelDocente()`, `/panel/mis-solicitudes` | `PantallaSolicitudDocenteTest` | |

## Preparación e inventario (RF36–RF40)

| RF | Pide | Estado | Dónde | Tests | Falta |
|---|---|---|---|---|---|
| RF36 | Vista diaria, asignar sala **y avisar al docente por correo** | Parcial | `PreparacionService::asignarSala()`, `/panel/preparaciones` | `PreparacionEscenarioTest`, `PantallaPreparacionTest`, `PreparacionPolicyTest` | El correo al asignar o cambiar la sala. Cierra el pendiente 3 del `CLAUDE.md` |
| RF37 | Ítems alistados, estado del montaje, observaciones, avance parcial por otra persona | Completo | `PreparacionService`, `/panel/preparaciones` | `TableroDiarioTest`, `PantallaPreparacionTest` | |
| RF38 | Registrar y actualizar inventario, retirar unidades, distinguir consumibles de **accesorios y repuestos de un simulador** | Parcial | `InventarioService`, `/panel/inventario` | `InventarioTest`, `PantallaInventarioTest`, `EstadoFuncionalInventarioTest` | Los tipos son simulador, equipo clínico y equipo básico; no hay accesorio o repuesto ligado a un simulador (D13) |
| RF39 | Nivel de fidelidad, solo el ADMIN | Completo | `ItemInventarioPolicy` | `PantallaInventarioTest` | |
| RF40 | Disponibilidad para administrativos y coordinadores | Completo | `InventarioService::disponibilidadEnFranja()`, `/panel/inventario/disponibilidad` | `DisponibilidadInventarioTest`, `PantallaInventarioTest` | |

## Evaluación de habilidades (RF41–RF50)

`EvaluacionService` está completo y probado, pero no hay pantalla: `/panel/evaluaciones` muestra "Esta sección todavía no tiene pantalla". El módulo esperaba la respuesta de RF68–RF70, que el enunciado ya da: **ya no está bloqueado**.

| RF | Pide | Estado | Dónde | Tests | Falta |
|---|---|---|---|---|---|
| RF41 | Evaluación solo desde una solicitud de evaluación aprobada | Solo backend | `EvaluacionService::crear()` | `EvaluacionReglasTest` | Pantalla |
| RF42 | Impedirla sin solicitud aprobada | Solo backend | `EvaluacionService::crear()` | `EvaluacionReglasTest` | Pantalla |
| RF43 | Tipo de evaluación de la materia, sin editar el checklist | Parcial | `EvaluacionService`, `/admin/tipos-de-evaluacion` | `PantallaTiposEvaluacionTest`, `EvaluacionReglasTest` | La pantalla del docente |
| RF44 | Copia del checklist vigente | Solo backend | `EvaluacionService::crear()` | `EvaluacionReglasTest` | Pantalla |
| RF45 | Varios estudiantes; **solo los habilitados** (formato verificado o en físico, sin bloqueo) | Parcial | `EvaluacionService::agregarEstudiante()` | `EvaluacionReglasTest` | La pantalla, y la regla: `agregarEstudiante()` no llama a `ConfidencialidadService::puedeParticiparEnPracticas()` ni existe el bloqueo (RF68). De qué lista salen los estudiantes: P2 |
| RF46 | Ítems marcados y resultado decidido por el docente | Solo backend | `EvaluacionService::marcarItem()`, `registrarResultado()` | `EvaluacionReglasTest` | Pantalla |
| RF47 | Observaciones por estudiante | Solo backend | `EvaluacionService::registrarObservaciones()` | `EvaluacionReglasTest` | Pantalla |
| RF48 | Un intento por evaluación, sin límite | Solo backend | `EvaluacionService::calcularIntento()` | `EvaluacionIntentoTest` | Pantalla |
| RF49 | El estudiante consulta evaluaciones, intentos y checklist | Solo backend | `EvaluacionService::historialDelEstudiante()` | `EvaluacionConsultasTest` | Pantalla |
| RF50 | Historial del docente | Solo backend | `EvaluacionService::historialDelDocente()` | `EvaluacionConsultasTest` | Pantalla |

## Formato de confidencialidad (RF51–RF53)

| RF | Pide | Estado | Dónde | Tests | Falta |
|---|---|---|---|---|---|
| RF51 | El ADMIN carga la plantilla PDF por periodo | Completo | `ConfidencialidadService::cargarPlantilla()`, `/panel/plantillas-confidencialidad` | `FormatoConfidencialidadTest`, `PantallaConfidencialidadTest` | |
| RF52 | Estudiantes y docentes descargan, firman a mano y cargan, una vez por **periodo académico** | Parcial | `ConfidencialidadService::registrarEntrega()`, `/panel/mi-formato` | `FormatoConfidencialidadTest`, `PantallaConfidencialidadTest`, `AccesoConfidencialidadTest` | El periodo hoy se calcula del calendario (`periodoVigente()`, corte en julio o `PERIODO_ACADEMICO_VIGENTE`). Tiene que leer el periodo que abre el laboratorio (RF75) |
| RF53 | Estado por persona, organizado por programa, materia y grupo; la entrega física habilita; verifican los administrativos | Parcial | `ConfidencialidadService::verificar()`, `registrarEntregaFisica()`, `estadoDeLosFirmantes()` | `EntregaFisicaConfidencialidadTest`, `PantallaConfidencialidadTest` | Hecho: estados, entrega física y verificación. Falta: agrupar por programa (no hay programa en `users`), por materia (no hay matrícula) y por grupo (P2); y que el estado condicione el ingreso (RF45, RF70) |

"Entregado en físico" aparece en el enunciado como un estado más. En el código es un hecho que convive con el estado del escaneo (`recibido_fisico_at`, regla 7 del `CLAUDE.md`). El comportamiento es el que pide el enunciado —habilita el ingreso y deja la carga pendiente—; solo cambia cómo se guarda, y así no se pierde al avanzar de estado.

## Reportes (RF54–RF56)

| RF | Pide | Estado | Dónde | Tests | Falta |
|---|---|---|---|---|---|
| RF54 | Uso de escenarios, con horas atribuidas al docente que dictó e indicando sustitución | Parcial | `ReporteService::usoDeEscenarios()`, `/panel/reportes` | `ReporteUsoDeEscenariosTest`, `PantallaDeReportesTest`, `ReporteExportacionTest` | La atribución por sustitución, que depende de RF73 |
| RF55 | Resultados de evaluación, PDF y Excel | Completo | `ReporteService::resultadosDeEvaluacion()` | `ReporteEvaluacionesTest`, `PantallaDeReportesTest` | Saldrá vacío hasta que exista la pantalla de evaluaciones |
| RF56 | Sesiones de evaluación aprobadas sin evaluación registrada | Completo | `ReporteService::evaluacionesNoRegistradas()` | `ReporteEvaluacionesTest`, `PantallaDeReportesTest` | Mientras no haya pantalla de evaluaciones, lista todas |

## Programación de sesiones (RF57–RF61, RF73)

Ninguno está construido. Todos dependen del mismo modelo: una sesión apartada es una solicitud que nace sin formato intramural y lo recibe después. P3 y P4 deciden ese modelo.

| RF | Pide | Estado | Falta |
|---|---|---|---|
| RF57 | Los administrativos registran las sesiones apartadas antes del semestre, a mano y en parte, conviviendo con las solicitudes del semestre | No iniciado | Todo. Estado inicial de la sesión apartada: P3 |
| RF58 | Validar sala y simuladores disponibles al registrar, con aviso de cruces; sin tiempo de montaje impuesto | No iniciado | Qué significa "sala disponible" si la sala se asigna en la preparación: P4 |
| RF59 | Formato intramural (insumos, equipos, simuladores) como paso posterior a la fecha | No iniciado | Quién lo llena: P3 |
| RF60 | Alerta a administrativos y docente si una sesión próxima no tiene formato intramural, con antelación configurable | No iniciado | Tarea programada, correo y la antelación (D5). El compose de producción no tiene servicio de tareas programadas |
| RF61 | Reprogramar una sesión aprobada con motivo, constancia de comunicación y correo al docente | No iniciado | Qué es la constancia: D4 |
| RF73 | Sustituir al docente de una sesión, con el original y el reemplazo | No iniciado | Quién la registra: D11 |

## Periodo académico (RF75)

| RF | Pide | Estado | Falta |
|---|---|---|---|
| RF75 | Administrativos, coordinación y ADMIN abren y cierran el periodo, sin fechas impuestas | No iniciado | Tabla de periodos, pantalla y que `ConfidencialidadService::periodoVigente()` lea de ahí en vez del calendario. Qué pasa entre un cierre y la siguiente apertura: D3 |

## Auditoría y roles (RF62–RF64)

| RF | Pide | Estado | Dónde | Tests | Falta |
|---|---|---|---|---|---|
| RF62 | Bitácora de aprobaciones, rechazos, reprogramaciones, retiros y cambios de rol, con usuario, fecha y motivo | Parcial | `asignaciones_de_rol` (roles), `cambios_estado_item` (retiros de inventario), `revisada_por`/`resuelta_por` en `solicitudes` | `RolesConVigenciaTest`, `EstadoFuncionalInventarioTest` | Una bitácora consultable. Hoy el rastro existe pero repartido, la aprobación no guarda motivo y no hay reprogramaciones ni retiros de participantes. Alcance de "retiro" y quién la consulta: D9 |
| RF63 | Elevar a coordinador temporalmente, con vigencia y registro | Completo | `AsignacionDeRolService`, `/panel/usuarios` | `RolesConVigenciaTest`, `RolActivoEnLivewireTest` | Hoy la fecha de fin es opcional también para coordinador (D12) |
| RF64 | Rol administrativo temporal para pasantes | Completo | `AsignacionDeRolService`, filtro en `User::roles()` | `RolesConVigenciaTest` | |

## Espacios, inventario y preparación (RF65–RF67, RF72, RF74)

| RF | Pide | Estado | Dónde | Tests | Falta |
|---|---|---|---|---|---|
| RF65 | Crear, editar y reubicar salas, vincularlas con escenarios, con histórico de la reubicación | Parcial | `/admin/salas` | `PantallaSalasTest` | El vínculo sala–escenario y su histórico. Qué se reubica: P5 |
| RF66 | Estado funcional por ítem con motivo y responsable | Completo | `InventarioService::cambiarEstado()`, `retirarUnidades()`, `reponerUnidades()`, `darDeBaja()` | `EstadoFuncionalInventarioTest`, `InventarioTest`, `DisponibilidadInventarioTest` | |
| RF67 | Lista de reposición que alimentan los administrativos y confirma la coordinadora, exportable una vez confirmada | Completo | `ReposicionService`, `/panel/reposicion` | `ListaDeReposicionTest`, `PantallaDeReportesTest` | |
| RF72 | Distinguir en la preparación lo que monta el administrativo de lo que requiere al ingeniero | No iniciado | — | — | Criterio: D10 |
| RF74 | Capacidad máxima de estudiantes por caso clínico; la de la sala solo informa | Completo | `SolicitudService::crear()` (`CapacidadDeEstudiantesExcedida`), `/admin/casos-clinicos` | `CapacidadDeEscenarioTest`, `PantallaCasosClinicosTest` | Aplicarla también al registrar sesiones apartadas (RF57) |

## Control de acceso de participantes (RF68–RF71)

| RF | Pide | Estado | Dónde | Tests | Falta |
|---|---|---|---|---|---|
| RF68 | Coordinación bloquea a un estudiante o docente, con motivo | No iniciado | — | — | Todo. Efecto sobre un docente bloqueado o sin formato: D2 |
| RF69 | Retirar a un participante de una sesión, con motivo y responsable | No iniciado | — | — | Quiénes son los participantes de una sesión: P2 |
| RF70 | Mostrar al docente quién no puede asistir y por qué | No iniciado | `ConfidencialidadService::puedeParticiparEnPracticas()` existe, sin uso | — | La pantalla y la lista de participantes (P2) |
| RF71 | Verificar el formato de un grupo completo en una pantalla, con búsqueda por persona | Parcial | `ConfidencialidadService::estadoDeLosFirmantes()`, `/panel/formatos-confidencialidad/estado` | `FormatoConfidencialidadTest`, `PantallaConfidencialidadTest` | Hecho: búsqueda por nombre, correo y código. Falta el filtro por grupo (P2) |

## Requerimientos no funcionales (RNF01–RNF10)

| RNF | Pide | Estado | Dónde vive | Falta |
|---|---|---|---|---|
| RNF01 | ~700 estudiantes y ~150 docentes | Parcial | Paginación, índices (`2026_09_23_120000_indexa_las_llaves_que_se_consultan`), exportación por lotes, tests de consultas N+1 | Una prueba de carga con ese volumen |
| RNF02 | Disponible en el horario académico | Parcial | `restart` en los servicios del compose de producción, copias de seguridad probadas en la CI | Depende del servidor. No hay monitoreo ni aviso si la plataforma cae |
| RNF03 | Docker sobre Debian 13 | Completo | `docker-compose.produccion.yml`, `docker/php/Dockerfile`, job de Docker en la CI | Falta desplegarlo en el servidor real (ver [Bloqueos externos](#bloqueos-externos)) |
| RNF04 | Acceso por rol, herencia coordinador → administrativo, control total del ADMIN | Completo | Policies, `RecursoDelAdmin`, middleware persistentes de Livewire | |
| RNF05 | Responsiva en computador y celular | Parcial | Panel con Tailwind, móvil primero | La landing no existe, y no hay revisión sistemática a ancho de celular |
| RNF06 | Arquitectura por capas documentada para terceros | Parcial | `CLAUDE.md`, `README.md`, `arquitectura-y-modelo-datos.md`, Larastan nivel 6 | El documento de arquitectura está desfasado (ver [Diferencias](#diferencias-entre-los-documentos-y-el-código)) |
| RNF07 | Información de estudiantes y evaluaciones solo para roles autorizados | Completo | Disco privado, `DescargaConfidencialidadController`, Policies, `EvaluacionEstudiantePolicy` | |
| RNF08 | Chrome, Firefox y Edge | Parcial | Sin dependencias exóticas de JavaScript | Nunca se ha probado en Firefox ni en Edge |
| RNF09 | Agregar módulos sin afectar los existentes | Completo | Capas Service/Policy, tests por módulo | |
| RNF10 | Conexiones lentas y equipos de gama baja | Parcial | `ImagenPublicaService` (WebP a 1600 px), exportación por lotes, FullCalendar solo en su pantalla | La landing, que es donde más pesa (videos, fotos); nunca se ha medido el peso real de las páginas |

---

## Preguntas abiertas

### Las que bloquean (sin respuesta no se construye)

| # | Pregunta | Afecta | Recomendación |
|---|---|---|---|
| P1 | ¿La landing tiene un diseño aprobado que haya que seguir (maqueta, Figma, referencia), o lo propone el desarrollo con la identidad de la universidad? | RF01–RF09 | Que lo proponga el desarrollo y el laboratorio lo apruebe sobre la página funcionando |
| P2 | **Grupos y participantes.** ¿Quién decide qué estudiantes forman el grupo A, B o C de una materia, y de dónde sale la lista de estudiantes de la materia? | RF28, RF45, RF53, RF69, RF70, RF71 | El docente arma los grupos de cada materia una vez por periodo, buscando estudiantes; cada sesión toma la lista de su grupo. Si la base institucional trae la matrícula por materia, la lista viene precargada y el docente solo reparte |
| P3 | **Sesiones apartadas.** La sesión que el administrativo registra desde el formato físico, ¿pasa por aprobación de coordinación como cualquier solicitud, o nace aprobada? Y el formato intramural, ¿lo llena el docente en la plataforma o el administrativo desde el papel? | RF57, RF59, RF60 | Nace revisada (el administrativo ya la revisó al registrarla) y coordinación la aprueba, para no romper la regla "sin revisión no aprueba nadie". El formato intramural lo llena el docente, y el administrativo puede llenarlo por él si llega en papel |
| P4 | **"Sala disponible" al registrar una sesión.** La sala se asigna en la preparación, minutos antes (RF36). ¿RF58 pide comprobar que haya al menos una sala apta para ese escenario libre en la franja, o que el administrativo elija la sala al registrar la sesión apartada? | RF58, RF65 | Comprobar que quede al menos una sala vinculada al escenario libre en esa franja, sin elegirla |
| P5 | **"Reubicar" un espacio físico.** ¿Qué cambia: el escenario se monta en otra sala (la incubadora pasa de la sala 2 a la 3), o la sala cambia de lugar (bloque, piso)? | RF65 | Lo primero: el vínculo sala–escenario guarda historial con fechas |

### Las que tienen decisión por defecto (se aplica si no se corrige)

| # | Decisión por defecto | Afecta |
|---|---|---|
| D1 | Rechazan los administrativos (y coordinación, por herencia) mientras la solicitud está pendiente o revisada. El ADMIN también puede rechazar, igual que aprueba en ausencia de la coordinadora | RF30, RF32 |
| D2 | El bloqueo no tiene fecha de fin: dura hasta que coordinación lo levante, con motivo. El ADMIN también puede bloquear. Un docente bloqueado no puede crear solicitudes nuevas, y sus sesiones aprobadas se marcan para que coordinación las reprograme o lo sustituya. Al docente sin formato no se le cancela la sesión: se avisa al docente, en la bandeja y en la preparación | RF68, RF70, regla 7 del `CLAUDE.md` |
| D3 | Sin periodo abierto no se reciben entregas del formato, y sigue valiendo lo del último periodo hasta que se abra el siguiente. El nombre del periodo lo escriben ellos ("2026-2") y no se repite | RF52, RF75 |
| D4 | La constancia de comunicación es un texto obligatorio (medio, fecha, con quién habló). La sesión reprogramada sigue aprobada, se revalidan capacidad y disponibilidad, y si cambia la fecha se libera la sala asignada | RF61 |
| D5 | La antelación del aviso la edita el ADMIN en la plataforma, con 3 días por defecto. El aviso es un correo diario y un indicador en la vista de sesiones | RF60 |
| D6 | Deshabilitar a mano es una marca propia que la sincronización no revierte. Los usuarios creados a mano (por ejemplo, un pasante externo) no los desactiva la sincronización | RF20, RF22 |
| D7 | Tamaño máximo de cada video: 100 MB por defecto, configurable por variable de entorno | RF17 |
| D8 | "Se dirige a un correo no-reply" se lee como: el correo **sale** de la cuenta no-reply y **llega** al correo de contacto del laboratorio (RF11), con responder-a del interesado. También se guarda en `solicitudes_informacion` | RF09 |
| D9 | "Retiro" en la bitácora cubre el retiro de participantes (RF69) y el de unidades de inventario (RF38). La bitácora la consultan coordinación y el ADMIN | RF62 |
| D10 | Lo que requiere al ingeniero se deduce del nivel de fidelidad alta. El ingeniero no tiene cuenta: el administrativo marca el ítem cuando él termina | RF72 |
| D11 | La sustitución la registran los administrativos (y coordinación). El reemplazo debe ser un docente con cuenta | RF73 |
| D12 | Elevar a coordinador exige fecha de fin, porque el enunciado dice "temporalmente" | RF63 |
| D13 | Accesorio o repuesto es un tipo nuevo de ítem de inventario, ligado a un simulador del inventario | RF38 |

---

## Bloqueos externos

Dependen de la universidad, no del laboratorio.

**Base de datos institucional (RF19, RF20).** Se desarrolla con datos simulados; para cerrarla hace falta:

1. **El motor** (Oracle, SQL Server, MySQL, PostgreSQL…). Decide qué extensión de PHP hay que instalar en la imagen de producción; Oracle y SQL Server necesitan librerías del fabricante.
2. Que el servidor del laboratorio llegue a la base por red (host, puerto, firewall).
3. El usuario de solo lectura.
4. La estructura: personas (correo, documento, código), programa, vigencia de matrícula o contrato y, si existe, la matrícula por materia (resolvería la mitad de P2).

**Despliegue.**

1. Dominio o subdominio definitivo, para la dirección de retorno en Google.
2. Quién crea las credenciales de Google.
3. Si el HTTPS lo pone un proxy de la universidad o el propio servidor.
4. Destino externo de las copias de seguridad.
5. **La cuenta de correo no-reply y sus datos de envío (SMTP).** No estaba en la lista y sin ella no sale ningún correo: RF09, RF33, RF36, RF60 y RF61.
6. **Espacio en disco del servidor.** Los videos de RF17 y sus copias de seguridad son lo que más va a ocupar.

---

## Diferencias entre los documentos y el código

1. **La portada pública es la página de ejemplo de Laravel**, con una imagen de laravel.com que la Content-Security-Policy bloquea. Si se despliega antes de la landing, eso verá el público.
2. **Quién rechaza.** La regla 9 del `CLAUDE.md` y `SolicitudPolicy::rechazar()` dicen que rechaza coordinación; RF30 dice que rechazan los administrativos durante la revisión.
3. **El periodo académico.** `ConfidencialidadService::periodoVigente()` y `config/laboratorio.php` lo calculan del calendario. RF52 y RF75 lo dejan en manos del laboratorio.
4. **Pendientes del `CLAUDE.md` ya cerrados por el enunciado:** el 1 (autoaprobación, RF31: no se bloquea) y el 3 (cómo se entera el docente de la sala, RF36: por correo). Siguen en `SolicitudPolicy::aprobar()` un bloque comentado y un aviso de "pendiente con el cliente" sobre esto.
5. **Números de RF19 y RF20.** La versión anterior de esta matriz los tenía al revés: RF19 es el criterio de acceso y RF20 la sincronización.
6. **Número de RF30.** `FormularioSolicitud` y `SolicitudController` dicen que el formulario del docente cubre "RF27–RF30". RF30 es la revisión administrativa: el formulario cubre RF27–RF29.
7. **`items_inventario` en el modelo de datos** (arquitectura §4.3) todavía tiene la columna `estado` y no los tres contadores. La migración `2026_09_21_180000_pasa_el_estado_del_inventario_a_cantidades` la reemplazó (regla 11 del `CLAUDE.md`).
8. **`casos_clinicos` en el modelo de datos** (arquitectura §4.2) no tiene `capacidad_maxima_estudiantes`, que existe (RF74).
9. **Tablas que el modelo de datos no describe** (arquitectura §4): `listas_reposicion`, y sin sus campos `lineas_reposicion`, `necesidades_reposicion` y `cambios_estado_item`.
10. **El encabezado del documento de arquitectura** dice que deriva de "RF01–RF56"; hoy son RF01–RF75.
11. **La ruta de los formatos de confidencialidad.** La arquitectura (§3) dice `storage/app/confidencialidad/`; la real es `storage/app/private/confidencialidad/{plantillas,firmados}`.
12. **"Bloquear prácticas si está pendiente"** (arquitectura §2) se le atribuye a `ConfidencialidadService`, que no bloquea nada todavía.
13. **Las tablas de Services** de la arquitectura (§2) omiten `AccesoService`, `ConfiguracionLandingService`, `ReposicionService` y `GeneradorDeReportes`, y presentan `UsuarioSyncService` sin advertir que no existe.
14. **RF22 en Filament.** El `CLAUDE.md`, la arquitectura y `AdminPanelProvider` agrupan "RF22–RF26" en `/admin`. RF22 (usuarios) no está en Filament: lo que hay es la pantalla de roles en `/panel/usuarios`.
15. **El compose de producción no tiene tareas programadas.** RF20 (sincronización) y RF60 (alertas) las necesitan; hay que añadir un servicio que corra `php artisan schedule:work`.
