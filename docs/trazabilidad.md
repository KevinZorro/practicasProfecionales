# Matriz de trazabilidad

Estado de cada requerimiento según lo que existe en el código, no según lo que dicen los documentos.

**Corte:** 29 de septiembre de 2026, rama `main` en `5dc32b0` (fusión de la PR #42, entrada con Google). No incluye la PR #43, la configuración de producción, que sigue abierta.

## Cómo se hizo

- Por cada RF y RNF se buscó su código en `app/`, `resources/`, `routes/`, `database/` y `tests/`. Después se abrió el Service y la pantalla, y se comprobó que un test los ejercite.
- **El enunciado de los requerimientos no está en el repositorio.** La columna "Qué cubre" es lo que el código y `arquitectura-y-modelo-datos.md` le atribuyen a cada número. En los RF sin ninguna mención, el enunciado no se pudo verificar: hay que cotejarlos con el documento de requerimientos (ver [Requerimientos sin rastro](#requerimientos-sin-rastro)).
- Las pantallas del ADMIN están en `/admin` (Filament) y las operativas en `/panel` (Livewire). Los tests están en `tests/Feature/`, salvo que se diga otra cosa.

## Estados

| Estado | Significa |
|---|---|
| **Completo** | Hay Service o recurso, pantalla y tests, y el flujo se puede usar de punta a punta. |
| **Solo backend** | Service y tests completos, sin pantalla: nadie lo puede usar todavía. |
| **Parcial** | Una parte está hecha y probada, otra falta. La columna de notas dice qué falta. |
| **Bloqueado** | No se puede terminar sin una respuesta del cliente o del usuario. La columna de notas dice por qué. |
| **No iniciado** | No hay código. |

## Resumen

| Estado | RF | RNF |
|---|---:|---:|
| Completo | 38 | 2 |
| Solo backend | 9 | 0 |
| Parcial | 4 | 2 |
| Bloqueado | 14 | 0 |
| No iniciado | 9 | 6 |
| **Total** | **74** | **10** |

Los 9 RF y los 6 RNF no iniciados son todos requerimientos sin ninguna mención en el código ni en los documentos del repositorio. Puede que alguno esté cubierto con otro nombre: hay que revisarlos contra el enunciado.

---

## Landing pública (RF01–RF09)

La parte pública de la landing no existe. `/` sirve `resources/views/welcome.blade.php`, que es **la página de ejemplo de Laravel**: título "Laravel" y una imagen cargada desde laravel.com. Los datos que la landing va a mostrar sí existen y los gestiona el ADMIN (RF10–RF17).

| RF | Qué cubre | Estado | Service | Pantalla | Tests | Notas |
|---|---|---|---|---|---|---|
| RF01 | Presentación del laboratorio: cifras destacadas y galería de fotos | Bloqueado | — | — | — | La vista pública espera la aprobación del diseño. Datos listos: RF10 |
| RF02 | Hero (títulos y video) y datos de contacto | Bloqueado | — | — | — | Igual que RF01. Datos listos: RF11 |
| RF03 | Escenarios clínicos visibles al público, con sus etiquetas de capacidades | Bloqueado | — | — | — | Igual que RF01. Datos listos: RF12 |
| RF04 | Talleres ofertados, con modalidad virtual o presencial | Bloqueado | — | — | — | Igual que RF01. Datos listos: RF13 |
| RF05 | Eventos, con su tipo | Bloqueado | — | — | — | Igual que RF01. Datos listos: RF14 |
| RF06 | Certificaciones del laboratorio | Bloqueado | — | — | — | Igual que RF01. Datos listos: RF15 |
| RF07 | Perfiles docentes con sus títulos | Bloqueado | — | — | — | Igual que RF01. Datos listos: RF16 |
| RF08 | Videos institucionales | Bloqueado | — | — | — | Dos esperas: el diseño de la landing y la confirmación del cliente sobre YouTube o Vimeo (RF17). Existen la tabla `videos_institucionales` y el modelo `VideoInstitucional`, sin uso |
| RF09 | Formulario de interés en un taller | Bloqueado | — | — | `PantallaTalleresTest` (solo que un taller con solicitudes no se borra) | Igual que RF01. Existen la tabla `solicitudes_informacion` y el modelo `SolicitudInformacion`, pero no hay formulario, Service ni **pantalla donde el laboratorio lea las solicitudes**. Esa pantalla no depende del diseño público |

## Contenido público: pantallas del ADMIN (RF10–RF17)

Todas las imágenes pasan por `ImagenPublicaService` (validación, orientación EXIF, reducción y WebP; `ImagenPublicaServiceTest`).

| RF | Qué cubre | Estado | Service | Pantalla | Tests | Notas |
|---|---|---|---|---|---|---|
| RF10 | Galería de fotos y estadísticas de la landing | Completo | `ImagenPublicaService` | `/admin/galeria`, `/admin/estadisticas` | `PantallaGaleriaTest`, `PantallaEstadisticasTest` | Las estadísticas son valores manuales, como se acordó |
| RF11 | Configuración de la landing: hero, video y contacto | Completo | `ConfiguracionLandingService`, `ImagenPublicaService` | `/admin/configuracion-de-la-landing` | `ConfiguracionLandingTest` | |
| RF12 | Campos públicos de los casos clínicos | Completo | — (recurso Filament) | `/admin/casos-clinicos` | `PantallaCasosClinicosTest` | |
| RF13 | Talleres | Completo | `ImagenPublicaService` | `/admin/talleres` | `PantallaTalleresTest`, `tests/Unit/EnumsTest` | |
| RF14 | Eventos y catálogo de tipos de evento | Completo | `ImagenPublicaService` | `/admin/eventos`, `/admin/tipos-de-evento` | `PantallaEventosTest`, `TiposDeEventoTest` | |
| RF15 | Certificaciones | Completo | `ImagenPublicaService` | `/admin/certificaciones` | `PantallaCertificacionesTest` | |
| RF16 | Perfiles docentes con títulos | Completo | `ImagenPublicaService` | `/admin/perfiles-docentes` | `PantallaPerfilesDocentesTest` | |
| RF17 | Galería de videos | Bloqueado | — | — | — | Hay una propuesta (enlaces de YouTube o Vimeo) que el usuario debe confirmar con el cliente antes de construirla. La tabla ya existe |

## Acceso, usuarios y estructura académica (RF18–RF26)

| RF | Qué cubre | Estado | Service | Pantalla | Tests | Notas |
|---|---|---|---|---|---|---|
| RF18 | Entrada solo con la cuenta institucional de Google | Completo | `AccesoService` | `/acceso` | `AccesoConGoogleTest`, `AutenticacionTest`, `AccesoDeDesarrolloTest` | Probado con un doble de Google. **Falta probarlo con las credenciales reales**: sin `GOOGLE_CLIENT_ID` la entrada no existe |
| RF19 | Sincronización programada con la base institucional | Bloqueado | — (`UsuarioSyncService` no existe) | — | — | Pendiente 2 del `CLAUDE.md`: la universidad no ha entregado la estructura de la vista institucional |
| RF20 | Estado activo o inactivo del usuario según su vigencia institucional | Parcial | `AccesoService::puedeEntrar()` | — (middleware `VerificarUsuarioActivo`) | `VigenciaInstitucionalTest` | Hecho: el inactivo no entra y se le corta la sesión, también en Livewire. Falta: quién actualiza `users.estado`, que es la sincronización bloqueada del RF19 |
| RF21 | Selector del rol activo | Completo | `App\Support\RolActivo` | Selector en la cabecera del panel (`POST /panel/rol-activo`) | `SelectorDeRolTest`, `RolActivoEnLivewireTest`, `ComponentesAutorizanEnCadaPeticionTest` | |
| RF22 | Gestión de usuarios | Parcial | `AsignacionDeRolService` | `/panel/usuarios` | `RolesConVigenciaTest` | Hay un listado de usuarios con sus roles, para asignarlos y revocarlos (RF63–RF64). No hay alta, edición ni activación de usuarios: el propio componente dice que eso llegará con la sincronización (RF19), así que depende del mismo bloqueo |
| RF23 | Materias | Completo | — (recurso Filament) | `/admin/materias` | `PantallaMateriasTest` | |
| RF24 | Casos clínicos y sus materias | Completo | — (recurso Filament) | `/admin/casos-clinicos` | `PantallaCasosClinicosTest` | |
| RF25 | Inventario que necesita cada caso clínico | Completo | — (recurso Filament, modelo `ItemNecesarioDelCaso`) | `/admin/casos-clinicos` | `PantallaCasosClinicosTest` | Alimenta la precarga del RF29 |
| RF26 | Tipos de evaluación con su checklist | Completo | — (recurso Filament) | `/admin/tipos-de-evaluacion` | `PantallaTiposEvaluacionTest` | |

Las salas también tienen pantalla (`/admin/salas`, `PantallaSalasTest`), pero el código no les asigna un RF propio.

## Solicitudes de escenario (RF27–RF35)

| RF | Qué cubre | Estado | Service | Pantalla | Tests | Notas |
|---|---|---|---|---|---|---|
| RF27 | El docente solicita un escenario | Completo | `SolicitudService::crear()` | `/panel/solicitudes/nueva` | `FlujoSolicitudTest`, `PantallaSolicitudDocenteTest` | |
| RF28 | Equipos de la solicitud, sin elegir sala | Completo | `SolicitudService::crear()` | `/panel/solicitudes/nueva` | `FlujoSolicitudTest`, `PantallaSolicitudDocenteTest` | |
| RF29 | Precarga del inventario del caso clínico | Completo | `SolicitudService::itemsSugeridos()` | `/panel/solicitudes/nueva` | `PantallaSolicitudDocenteTest`, `FlujoSolicitudTest` | |
| RF30 | El formulario del docente o la revisión del administrativo: el código y la arquitectura no coinciden (diferencia 10) | Completo | `SolicitudService::crear()` / `marcarRevisada()` | `/panel/solicitudes/nueva` / `/panel/solicitudes` | `FlujoSolicitudTest`, `PantallaBandejaSolicitudesTest` | Las dos lecturas están implementadas, así que el estado no cambia; el número sí está mal atribuido en un sitio |
| RF31 | Bandeja: revisión y aprobación | Completo | `SolicitudService::bandeja()`, `marcarRevisada()`, `aprobar()` | `/panel/solicitudes` | `PantallaBandejaSolicitudesTest`, `FlujoSolicitudTest`, `SolicitudPolicyTest` | |
| RF32 | Rechazo con motivo | Completo | `SolicitudService::rechazar()` | `/panel/solicitudes` | `FlujoSolicitudTest`, `PantallaBandejaSolicitudesTest` | El motivo es opcional (`nullable`). Si el enunciado lo exige, esto hay que cambiarlo |
| RF33 | Correo al docente con el resultado | Completo | `SolicitudService` → eventos `SolicitudAprobada`/`SolicitudRechazada` → listener en cola | — (correo) | `FlujoSolicitudTest`, `ListenersRegistradosTest` | |
| RF34 | Calendario de escenarios aprobados, visible para los cinco roles | Completo | `SolicitudService::paraCalendario()` | `/panel/calendario` | `CalendarioSolicitudesTest`, `PantallaCalendarioTest` | |
| RF35 | Historial de solicitudes del docente | Completo | `SolicitudService::historialDelDocente()` | `/panel/mis-solicitudes` | `PantallaSolicitudDocenteTest` | |

## Preparación e inventario (RF36–RF40)

| RF | Qué cubre | Estado | Service | Pantalla | Tests | Notas |
|---|---|---|---|---|---|---|
| RF36 | Preparación: el administrativo asigna la sala | Completo | `PreparacionService::crearDesdeSolicitud()`, `asignarSala()` | `/panel/preparaciones` | `PreparacionEscenarioTest`, `PantallaPreparacionTest`, `PreparacionPolicyTest` | |
| RF37 | Tablero diario: ítems alistados y estado del montaje | Completo | `PreparacionService::tableroDelDia()`, `marcarItemAlistado()`, `cambiarEstado()` | `/panel/preparaciones` | `TableroDiarioTest`, `PantallaPreparacionTest` | |
| RF38 | Inventario: altas, bajas y edición | Completo | `InventarioService` | `/panel/inventario`, `/panel/inventario/nuevo` | `InventarioTest`, `PantallaInventarioTest` | |
| RF39 | Nivel de fidelidad, editable solo por el ADMIN | Completo | `InventarioService`, `ItemInventarioPolicy` | `/panel/inventario/{item}/editar` | `PantallaInventarioTest` | |
| RF40 | Disponibilidad por fecha y franja, oculta a los docentes | Completo | `InventarioService::disponibilidadEnFranja()` | `/panel/inventario/disponibilidad` | `DisponibilidadInventarioTest`, `PantallaInventarioTest`, `PantallaSolicitudDocenteTest` | |

## Evaluación de habilidades (RF41–RF50)

`EvaluacionService` está completo y probado, pero no hay ninguna pantalla que lo use. `/panel/evaluaciones` aparece en el menú y muestra "Esta sección todavía no tiene pantalla". El documento de arquitectura hace depender la pantalla de la pregunta de RF68–RF70, y el usuario dejó el módulo en espera.

| RF | Qué cubre | Estado | Service | Pantalla | Tests | Notas |
|---|---|---|---|---|---|---|
| RF41 | Evaluación sobre una solicitud aprobada | Solo backend | `EvaluacionService::crear()` | — | `EvaluacionReglasTest` | |
| RF42 | Solo sobre solicitudes de tipo `evaluacion` | Solo backend | `EvaluacionService::crear()` | — | `EvaluacionReglasTest` | |
| RF43 | Checklist maestro del ADMIN, restringido por materia | Parcial | `EvaluacionService` | `/admin/tipos-de-evaluacion` | `PantallaTiposEvaluacionTest`, `EvaluacionReglasTest` | Hechos: la pantalla del ADMIN que define el checklist y sus materias, y la regla que lo exige. Falta: la pantalla donde el docente lo usa |
| RF44 | Copia congelada del checklist | Solo backend | `EvaluacionService::crear()` | — | `EvaluacionReglasTest` | |
| RF45 | Estudiantes evaluados | Solo backend | `EvaluacionService::agregarEstudiante()`, `quitarEstudiante()` | — | `EvaluacionReglasTest` | |
| RF46 | Resultado decidido por el docente, no calculado | Solo backend | `EvaluacionService::marcarItem()`, `registrarResultado()` | — | `EvaluacionReglasTest` | |
| RF47 | Observaciones | Solo backend | `EvaluacionService::registrarObservaciones()` | — | `EvaluacionReglasTest` | |
| RF48 | Número de intento | Solo backend | `EvaluacionService::calcularIntento()` | — | `EvaluacionIntentoTest` | |
| RF49 | El estudiante consulta sus resultados | Solo backend | `EvaluacionService::historialDelEstudiante()`, `EvaluacionEstudiantePolicy` | — | `EvaluacionConsultasTest` | |
| RF50 | Historial de evaluaciones del docente | Solo backend | `EvaluacionService::historialDelDocente()` | — | `EvaluacionConsultasTest` | |

## Formato de confidencialidad y reportes (RF51–RF56)

| RF | Qué cubre | Estado | Service | Pantalla | Tests | Notas |
|---|---|---|---|---|---|---|
| RF51 | Plantilla del formato y entrega por semestre | Completo | `ConfidencialidadService::cargarPlantilla()`, `registrarEntrega()` | `/panel/plantillas-confidencialidad`, `/panel/mi-formato` | `FormatoConfidencialidadTest`, `PantallaConfidencialidadTest` | |
| RF52 | Verificación por el administrativo; lo firman estudiantes y docentes | Completo | `ConfidencialidadService::verificar()`, `rechazar()`, `estadoDeLosFirmantes()` | `/panel/formatos-confidencialidad`, `/panel/formatos-confidencialidad/estado` | `FormatoConfidencialidadTest`, `PantallaConfidencialidadTest`, `AccesoConfidencialidadTest` | |
| RF53 | Entrega en físico | Completo | `ConfidencialidadService::registrarEntregaFisica()` | `/panel/formatos-confidencialidad/estado` | `EntregaFisicaConfidencialidadTest` | |
| RF54 | Reporte de uso de escenarios | Completo | `ReporteService::usoDeEscenarios()`, `GeneradorDeReportes` | `/panel/reportes` (PDF y Excel) | `ReporteUsoDeEscenariosTest`, `PantallaDeReportesTest`, `ReporteExportacionTest` | |
| RF55 | Reporte de resultados de evaluación | Completo | `ReporteService::resultadosDeEvaluacion()` | `/panel/reportes` | `ReporteEvaluacionesTest`, `PantallaDeReportesTest` | Completo como reporte, pero saldrá vacío hasta que exista la pantalla de evaluaciones (RF41–RF50) |
| RF56 | Reporte de evaluaciones no registradas | Completo | `ReporteService::evaluacionesNoRegistradas()` | `/panel/reportes` | `ReporteEvaluacionesTest`, `PantallaDeReportesTest` | Mientras no haya pantalla de evaluaciones, lista todas las solicitudes de evaluación aprobadas y pasadas |

## Requerimientos añadidos después (RF57–RF74)

| RF | Qué cubre | Estado | Service | Pantalla | Tests | Notas |
|---|---|---|---|---|---|---|
| RF57–RF62 | Sin enunciado disponible | No iniciado | — | — | — | Sin ninguna mención. Ver [Requerimientos sin rastro](#requerimientos-sin-rastro) |
| RF63 | Roles con fecha de fin | Completo | `AsignacionDeRolService`, filtro en `User::roles()` | `/panel/usuarios` | `RolesConVigenciaTest`, `RolActivoEnLivewireTest` | |
| RF64 | Delegación temporal de la aprobación y rastro de asignaciones | Completo | `AsignacionDeRolService` | `/panel/usuarios` | `RolesConVigenciaTest` | |
| RF65 | Sin enunciado disponible | No iniciado | — | — | — | Sin ninguna mención |
| RF66 | Estado funcional por unidades del inventario | Completo | `InventarioService::cambiarEstado()`, `retirarUnidades()`, `reponerUnidades()`, `darDeBaja()` | `/panel/inventario` | `EstadoFuncionalInventarioTest`, `InventarioTest`, `DisponibilidadInventarioTest` | |
| RF67 | Lista de reposición que se cierra | Completo | `ReposicionService` | `/panel/reposicion` (PDF y Excel de listas cerradas) | `ListaDeReposicionTest`, `PantallaDeReportesTest` | |
| RF68 | Qué pasa con quien no tiene el formato de confidencialidad | Bloqueado | `ConfidencialidadService::puedeParticiparEnPracticas()` existe, pero nadie lo llama | — | — | Pendiente con el cliente: qué significa que a un docente le falte el formato (regla 7 del `CLAUDE.md`) |
| RF69 | Sin enunciado propio en el repositorio | Bloqueado | — | — | — | El documento de arquitectura lo cita dentro del rango RF68–RF70, que espera la misma respuesta. Confirmar con el enunciado |
| RF70 | Igual que RF68 | Bloqueado | — | — | — | Misma pregunta pendiente que RF68 |
| RF71 | Verificación del formato por grupo o sesión, con búsqueda por nombre o código | Parcial | `ConfidencialidadService::estadoDeLosFirmantes()` | `/panel/formatos-confidencialidad/estado` | `FormatoConfidencialidadTest`, `PantallaConfidencialidadTest` | Hecho: la pantalla de estado busca por nombre, correo y código institucional, con los docentes incluidos. Falta: la lista nominal por sesión y la pantalla por grupo, bloqueadas hasta que el cliente diga qué son los grupos A/B/C |
| RF72–RF73 | Sin enunciado disponible | No iniciado | — | — | — | Sin ninguna mención |
| RF74 | Capacidad máxima de estudiantes del escenario | Completo | `SolicitudService::crear()` (excepción `CapacidadDeEstudiantesExcedida`) | `/admin/casos-clinicos` (el dato); `/panel/solicitudes/nueva` (el tope) | `CapacidadDeEscenarioTest`, `PantallaCasosClinicosTest` | |

## Requerimientos no funcionales (RNF01–RNF10)

| RNF | Qué cubre | Estado | Dónde vive | Tests | Notas |
|---|---|---|---|---|---|
| RNF01 | Volumen: ~700 estudiantes y ~150 docentes | Parcial | Paginación en los listados, índices (`2026_09_23_120000_indexa_las_llaves_que_se_consultan`), exportación por lotes en `ReporteService` | `EvaluacionConsultasTest` y los tests de consultas N+1 de las pantallas | Nunca se ha hecho una prueba de carga con ese volumen |
| RNF02 | Sin enunciado disponible | No iniciado | — | — | Sin ninguna mención |
| RNF03 | Sin enunciado disponible | No iniciado | — | — | Sin ninguna mención |
| RNF04 | Herencia de permisos coordinador → administrativo | Completo | Policies (`SolicitudPolicy`, `PreparacionPolicy`, `FormatoConfidencialidadPolicy` y las demás) | `SolicitudPolicyTest`, `PreparacionPolicyTest`, `PantallaInventarioTest` | |
| RNF05 | Sin enunciado disponible | No iniciado | — | — | Sin ninguna mención |
| RNF06 | Sin enunciado disponible | No iniciado | — | — | Sin ninguna mención |
| RNF07 | Documentos del formato de confidencialidad fuera del almacenamiento público | Completo | Disco `local` (`storage/app/private`), `DescargaConfidencialidadController`, `FormatoConfidencialidadPolicy` | `AccesoConfidencialidadTest`, `FormatoConfidencialidadTest` | |
| RNF08 | Sin enunciado disponible | No iniciado | — | — | Sin ninguna mención |
| RNF09 | Sin enunciado disponible | No iniciado | — | — | Sin ninguna mención |
| RNF10 | Conexiones lentas y equipos modestos | Parcial | `ImagenPublicaService` (WebP a 1600 px), exportación por lotes, FullCalendar solo en su pantalla | `ImagenPublicaServiceTest`, `ReporteExportacionTest` | Cumplido en el panel. Sin verificar en la landing pública, que no existe; y nunca se ha medido el peso real de las páginas |

La PR #43 (cabeceras de seguridad, copias de seguridad, imágenes de producción) probablemente cubre alguno de los RNF sin enunciado. No se puede atribuir sin el texto del requerimiento.

---

## Requerimientos sin rastro

Estos requerimientos no aparecen ni en el código ni en `CLAUDE.md` ni en `arquitectura-y-modelo-datos.md`, y su enunciado no está en el repositorio:

- **RF57, RF58, RF59, RF60, RF61, RF62, RF65, RF72, RF73**
- **RNF02, RNF03, RNF05, RNF06, RNF08, RNF09**
- **RF69** solo aparece dentro del rango "RF68–RF70" del documento de arquitectura.

Se marcan como no iniciados porque ningún código los cita, pero alguno puede estar hecho con otro nombre (sobre todo los RNF de seguridad, disponibilidad o mantenibilidad). Hace falta el documento de requerimientos para cerrarlos.

## Diferencias entre los documentos y el código

1. **La portada pública es la página de ejemplo de Laravel.** El documento de arquitectura (§3, "Previsto") dice "Hoy solo hay `welcome.blade.php`", sin aclarar que es la plantilla de Laravel, con el título "Laravel" y una imagen de laravel.com. Si se despliega antes de construir la landing, eso es lo que verá el público. Además, con la Content-Security-Policy de la PR #43, esa imagen externa quedaría bloqueada.
2. **`items_inventario` en el modelo de datos** (arquitectura §4.3) todavía tiene la columna `estado` (`disponible`, `mantenimiento`, `baja`) y no tiene los tres contadores. En el código la columna ya no existe: la migración `2026_09_21_180000_pasa_el_estado_del_inventario_a_cantidades` la reemplazó por `cantidad_operativa`, `cantidad_en_revision` y `cantidad_defectuosa`, como dice la regla 11 del `CLAUDE.md`.
3. **`casos_clinicos` en el modelo de datos** (arquitectura §4.2) no tiene `capacidad_maxima_estudiantes`. La columna existe (RF74) y el mismo documento la menciona en el §6.1.
4. **Tablas que el modelo de datos no describe** (arquitectura §4). `listas_reposicion` no aparece en ninguna parte. `lineas_reposicion`, `necesidades_reposicion` y `cambios_estado_item` solo se nombran de pasada en el §6.1, sin sus campos.
5. **El encabezado del documento de arquitectura** dice que deriva de "RF01–RF56", pero el mismo documento trata RF63, RF64, RF66, RF67, RF71 y RF74.
6. **La ruta de los formatos de confidencialidad.** El documento de arquitectura (§3) la pone en `storage/app/confidencialidad/`. El disco `local` tiene su raíz en `storage/app/private`, así que la ruta real es `storage/app/private/confidencialidad/{plantillas,firmados}`, la misma que usan el `CLAUDE.md` y el volumen `archivos_privados`.
7. **"Bloquear prácticas si está pendiente."** La tabla de Services de la arquitectura (§2) se lo atribuye a `ConfidencialidadService`. No bloquea nada: `puedeParticiparEnPracticas()` no lo llama ningún código. El `CLAUDE.md` (regla 7) sí lo dice bien.
8. **Las tablas de Services no coinciden con `app/Services`.**
   - La de la arquitectura (§2) omite `AccesoService`, `ConfiguracionLandingService` y `ReposicionService`.
   - Esa misma tabla presenta `UsuarioSyncService` sin advertir que no existe. El `CLAUDE.md` sí lo advierte.
   - Ninguna de las dos nombra `GeneradorDeReportes`, que arma las tres salidas de los reportes (pantalla, PDF y Excel).
9. **RF22 en Filament.** El `CLAUDE.md` (§2), la arquitectura (§1) y el comentario de `AdminPanelProvider` agrupan "estructura académica (RF22–RF26)" en `/admin`. RF22 es la gestión de usuarios: no está en Filament. Lo que hay es la pantalla de roles en Livewire (`/panel/usuarios`), y su componente aclara que la gestión completa no vive ahí.
10. **Número de RF30.** `FormularioSolicitud` y `SolicitudController` dicen que el formulario del docente cubre "RF27–RF30". El modelo de datos de la arquitectura (§4.4) asigna el RF30 a `revisada_por`, la revisión del administrativo. Una de las dos atribuciones está mal. Las dos funciones existen, así que no cambia el estado, pero conviene corregir la que no coincida con el enunciado.
