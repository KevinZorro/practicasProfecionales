# Arquitectura y Modelo de Datos
## Plataforma de Gestión del Laboratorio de Simulación Clínica

Documento técnico de referencia. Deriva de los requerimientos funcionales RF01–RF75 y no funcionales RNF01–RNF10 (`docs/requerimientos.md`). El estado de cada uno contra el código está en `docs/trazabilidad.md`; las reglas de trabajo, en `CLAUDE.md`.

---

## 1. Visión general

Aplicación web monolítica en Laravel, con renderizado del lado del servidor y componentes reactivos puntuales. Se despliega en contenedores Docker sobre el servidor institucional (Debian 13 Trixie).

Se eligió monolito y no arquitectura de servicios separados porque el sistema tiene un único dominio de negocio, un solo equipo de desarrollo y un volumen de tráfico moderado. Separar backend y frontend agregaría complejidad de despliegue y mantenimiento sin beneficio real a esta escala.

### Stack

**Instalado** — lo que está en `composer.json` y `package.json` y se usa hoy:

| Capa | Tecnología |
|---|---|
| Lenguaje | PHP 8.3 |
| Framework | Laravel 12 LTS (12.60 o superior) |
| Vistas | Blade |
| Interactividad | Livewire 3 + Alpine.js (el que trae Livewire) |
| Estilos | Tailwind CSS 3 |
| Base de datos | PostgreSQL 16 |
| Permisos | spatie/laravel-permission ^7.1 |
| Calendario | FullCalendar 6 |
| Exportación PDF | barryvdh/laravel-dompdf |
| Exportación Excel | maatwebsite/excel |
| Tests | Pest |
| Análisis estático | Larastan, nivel 6, con línea base |
| Pantallas del ADMIN | Filament 3.3, en `/admin` |
| Autenticación | Laravel Socialite 5 (Google OAuth, RF18). Sin `GOOGLE_CLIENT_ID` la entrada no existe |
| Servidor web | Nginx |
| Contenedores | Docker + Docker Compose |

**Previsto, todavía sin instalar:**

| Capa | Tecnología | Estado |
|---|---|---|
| Métricas | Plausible o Matomo (contenedor aparte) | Sin decidir |

**Solo PostgreSQL.** El plan inicial admitía MySQL 8 como alternativa; ya no es posible. Las invariantes críticas se garantizan con `CHECK` de PostgreSQL (regla 11 del `CLAUDE.md`: las cantidades del inventario siempre suman el total) y hay migraciones con SQL propio de PostgreSQL.

**Por qué Filament, y solo para el ADMIN:** el ADMIN gestiona 8 módulos de contenido público (RF10–RF17) más la estructura académica (RF23–RF26). Son pantallas de alta, baja y edición sin reglas de negocio, y construirlas a mano consumiría gran parte del presupuesto de horas. Filament las genera a partir de los modelos, con carga de imágenes, orden y filtros, y respeta las Policies de Laravel, así que la disciplina de permisos se mantiene. Los flujos operativos —solicitudes, preparación, inventario, formato de confidencialidad, evaluaciones— tienen lógica de dominio propia y siguen en Livewire. Las cuentas de usuario (RF22) también: deshabilitar y repartir roles llevan motivo, bitácora y vigencia, así que viven en `/panel/usuarios` y no en Filament.

La versión es la 3.3: la 4 exige Tailwind 4 para cualquier tema propio, y el panel usa Tailwind 3. Filament trae su CSS compilado y no toca la configuración de Tailwind del panel. Las condiciones con las que se instaló —clase base que deniega lo no definido, acceso solo del ADMIN con el rol activo, sin entrada por contraseña, assets fuera del repositorio— están en el §2 del `CLAUDE.md`.

---

## 2. Arquitectura por capas

```
HTTP Request
    │
    ▼
Route ──► Middleware (auth, usuario activo, rol activo)
    │
    ▼
Form Request ──────► validación de entrada
    │
    ▼
Controller / Livewire Component  (delgado: recibe y responde)
    │
    ▼
Policy ────────────► autorización por rol
    │
    ▼
Service ───────────► lógica de negocio
    │
    ▼
Model / Eloquent ──► persistencia
    │
    ▼
Event ─────────────► Listener ──► Mail (notificaciones)
```

### Responsabilidad de cada capa

- **Form Request** — valida formato y obligatoriedad de los datos que entran. Casi siempre lo hace la validación de Livewire (ver el §3).
- **Policy** — decide si el usuario puede ejecutar la acción según su rol activo. Aquí se implementa la herencia coordinador → administrativo (RNF04). Los componentes Livewire vuelven a pedir el permiso de su pantalla en cada petición (`AutorizaEnCadaPeticion`).
- **Service** — concentra las reglas de negocio: aprobar una solicitud, calcular el número de intento, copiar el checklist al crear una evaluación, precargar inventario desde un caso clínico. No se escriben en controladores ni en modelos. Quien actúa llega como parámetro (`User $actor`); un Service no lee la sesión ni la petición, para funcionar igual desde una pantalla, un comando o la cola.
- **Model** — relaciones, scopes y accessors. Sin lógica de negocio.
- **Event / Listener** — el correo de resultado de solicitud (RF33) y el de sala asignada (RF36) se disparan como evento. Los demás correos (reprogramación, sustitución, aviso del formato intramural, sincronización detenida) los encola el propio Service con `Mail::queue`. Ninguno bloquea la respuesta HTTP.
- **Bitácora** — las acciones sensibles (aprobar, rechazar, reprogramar, sustituir, retirar, dar de baja, repartir roles, bloquear, deshabilitar) dejan una fila en `bitacora` dentro de la misma transacción que las hace (RF62).

### Reglas que viven en Services

| Service | Reglas que encapsula |
|---|---|
| `SolicitudService` | Crear solicitud con su grupo y estudiantes, completar o retirar estudiantes de la sesión, precargar inventario del caso clínico, transiciones de estado (pendiente → revisada → aprobada/rechazada, con rechazo en las dos fases), disparar notificaciones, capacidad máxima del escenario (RF74) |
| `RegistroPrevioService` | Sesiones apartadas antes del semestre (RF57): nacen aprobadas y queda quién las registró; avisos de cruce (RF58), formato intramural (RF59) y aviso diario de las que no lo tienen (RF60) |
| `NovedadesDeSesionService` | Reprogramar una sesión aprobada (RF61) y sustituir a su docente (RF73), con rastro de solo añadir y correo |
| `PreparacionService` | Crear preparación al aprobarse una solicitud, asignar sala entre las libres (las vinculadas al escenario primero) y avisar al docente (RF36), marcar ítems alistados, cambiar estado de montaje, señalar lo que monta el ingeniero (RF72) |
| `SalaService` | Rastro de la ubicación de las salas: bloque, piso y número (RF65) |
| `EvaluacionService` | Validar que exista solicitud aprobada de tipo evaluación, copiar ítems del checklist, calcular número de intento por estudiante |
| `InventarioService` | Altas y ediciones, movimiento de unidades entre estados con cantidad, motivo y responsable (RF66), retiro y baja, accesorios ligados a un simulador (RF38), disponibilidad por fecha y franja horaria |
| `ReposicionService` | Lista de insumos por pedir: borrador calculado, necesidades anotadas a mano, cierre que congela las líneas (RF67) |
| `PeriodoAcademicoService` | Abrir, cerrar y reabrir el periodo académico (RF75). Única fuente del periodo vigente |
| `ConfidencialidadService` | Plantillas, entregas, verificación y entrega en físico del formato de confidencialidad; estado por persona, filtrable por sesión, materia y programa (RF51–RF53) |
| `ParticipacionService` | Quién puede entrar al laboratorio y por qué no: formato al día y sin bloqueo (RF45, RF70). Lo consultan la evaluación y la lista de cada sesión |
| `BloqueoService` | Bloquear y levantar el bloqueo de estudiantes y docentes, siempre con motivo (RF68) |
| `AccesoService` | Quién puede entrar: vigencia institucional y cuenta sin deshabilitar (regla 8); a qué cuenta corresponde quien vuelve de Google (RF18) |
| `AsignacionDeRolService` | Asignar y revocar roles con o sin vigencia, y registrar el rastro. Única puerta de escritura de roles (RF63, RF64) |
| `UsuarioService` | Cuentas que el ADMIN crea y edita a mano (origen `manual`); deshabilitar y volver a habilitar, con motivo (RF22) |
| `UsuarioSyncService` | Sincronizar con la base institucional a través de una `FuenteInstitucional`: altas, cambios, desactivar sin borrar, roles permanentes, freno ante desactivaciones masivas (RF19, RF20). Hoy solo existe la fuente simulada |
| `BitacoraService` | Escribir y consultar la bitácora de auditoría (RF62) |
| `AjustesService` | Valores que el ADMIN cambia sin desplegar, como la antelación del aviso del RF60 |
| `ReporteService` y `GeneradorDeReportes` | Agregaciones de uso de escenarios y de resultados de evaluación; una sola consulta alimenta pantalla, PDF y Excel (RF54–RF56) |
| `ConfiguracionLandingService` | Textos del hero, video y contacto de la landing (RF11) |
| `PortadaService` | Lo que muestra la portada pública (RF01–RF07): solo lo publicado, talleres y eventos de hoy en adelante, sin imágenes cuyo archivo falta; el detalle de cada escenario publicado (RF03) |
| `ImagenPublicaService` | Imágenes del contenido público: validar tipo y tamaño, enderezar según el EXIF, reducir a 1600 px de lado mayor y guardar en WebP (RNF10); borrar la reemplazada al confirmar la transacción |

---

## 3. Estructura de carpetas

Lo que existe hoy. Lo previsto va aparte, abajo, para que el árbol no afirme lo que no hay.

```
proyecto/
├── app/
│   ├── Enums/                                 # estados y tipos del dominio
│   ├── Events/                                # SolicitudAprobada, SolicitudRechazada, SalaAsignada
│   ├── Exceptions/                            # una por familia de regla rota
│   ├── Exports/                               # Excel de reportes y de la lista de reposición
│   ├── Filament/
│   │   ├── RecursoDelAdmin.php                # base de todo recurso: deniega lo que la Policy no define
│   │   ├── Formularios/CampoDeImagen.php      # toda imagen pública pasa por ImagenPublicaService
│   │   ├── Concerns/                          # BorraLasImagenesReemplazadas
│   │   └── Resources/                         # pantallas del ADMIN (RF10–RF16, RF23–RF26)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── PortadaController.php          # portada pública y detalle de escenario (RF01–RF07)
│   │   │   ├── Auth/
│   │   │   │   ├── AccesoConGoogleController.php      # entrada con Google (RF18)
│   │   │   │   ├── AccesoDeDesarrolloController.php   # solo en local, hasta probar Google con credenciales reales
│   │   │   │   └── SalirController.php
│   │   │   └── Panel/                         # entregan la vista; la lógica va en Livewire y Services
│   │   ├── Requests/
│   │   │   └── FiltroDeReporteRequest.php     # filtros de las descargas de reportes, los mismos de la pantalla
│   │   └── Middleware/
│   │       ├── CabecerasDeSeguridad.php       # CSP y demás cabeceras, probadas en Laravel
│   │       ├── EstablecerRolActivo.php        # selector de vista RF21
│   │       ├── SoloEnDesarrollo.php
│   │       └── VerificarUsuarioActivo.php     # regla 8: corta al inactivo, también en Livewire
│   ├── Listeners/                             # registrados a mano en AppServiceProvider, en cola
│   ├── Livewire/
│   │   ├── Concerns/AutorizaEnCadaPeticion.php
│   │   ├── Bitacora/                          # consulta de la bitácora RF62
│   │   ├── Bloqueo/                           # bloqueos de acceso RF68
│   │   ├── Confidencialidad/                  # formato de confidencialidad RF51–RF53
│   │   ├── Evaluacion/                        # evaluaciones y resultados RF41–RF50
│   │   ├── Inventario/                        # RF38–RF40, RF66
│   │   ├── PeriodoAcademico/                  # abrir y cerrar el periodo RF75
│   │   ├── Preparacion/                       # tablero diario RF36–RF37, RF72
│   │   ├── Reportes/                          # pantalla de reportes RF54–RF56
│   │   ├── Reposicion/                        # lista de insumos por pedir RF67
│   │   ├── Solicitud/                         # formulario, bandeja, participantes, sesiones apartadas, novedades
│   │   └── Usuario/                           # cuentas RF22 y roles con vigencia RF63–RF64
│   ├── Mail/
│   ├── Models/
│   ├── Policies/
│   ├── Providers/
│   │   └── Filament/AdminPanelProvider.php    # panel /admin: solo ADMIN, sin login propio
│   ├── Services/                              # también los objetos de datos (Datos*) y la FuenteInstitucional
│   └── Support/                               # menú del panel y rol activo
├── database/
│   ├── datos/institucional-simulada.json      # fuente simulada de la sincronización (RF20)
│   ├── factories/
│   ├── migrations/
│   └── seeders/                               # RolSeeder, DatosPruebaSeeder
├── docker/
│   ├── nginx/default.conf
│   ├── php/                                   # Dockerfile, php.ini, www.conf
│   └── postgres/
├── public/
│   ├── fonts/onest/                           # Onest servida localmente, con su licencia OFL
│   └── marca/                                 # logos oficiales de la UFPS
├── resources/js/portada.js                    # las animaciones de la portada (entrada propia de Vite)
├── resources/views/
│   ├── layouts/                               # panel y publico
│   ├── portada/                               # portada pública y detalle de escenario
│   ├── components/portada/                    # piezas de la portada: pulso, cifra, credencial…
│   ├── panel/                                 # una carpeta por sección del menú
│   ├── livewire/
│   ├── emails/
│   ├── reportes/pdf/                          # plantillas Blade para dompdf
│   └── components/
├── lang/es/ y lang/es.json                   # la aplicación en español: validación, paginación, páginas de error
├── routes/
│   ├── web.php
│   └── console.php                            # comandos y tareas programadas (RF20, RF60)
├── storage/app/private/confidencialidad/      # plantillas/ y firmados/: privado, nunca público
├── tests/
├── docker-compose.yml                         # entorno de desarrollo, con trabajador de cola y programador
├── docker-compose.produccion.yml              # producción: imágenes con el código, programador, copias de seguridad
└── .env.example
```

**Previsto, todavía sin construir:**

| Pieza | Para qué | Depende de |
|---|---|---|
| Una `FuenteInstitucional` real | Conectar la sincronización (RF20) a la base de la universidad: traducir sus columnas a `PersonaInstitucional` y elegirla con `SINCRONIZACION_FUENTE` | Motor, acceso y estructura de la base institucional (pendiente 2 del `CLAUDE.md`) |
| Recurso de Filament de la galería de videos y su sección en la portada | Videos subidos al servidor, con portada y tope configurable (RF17), y la galería pública que no los descarga hasta reproducirlos (RF08) | — |
| Formulario de información por taller (RF09) | Hoy «Pedir información» abre un correo al laboratorio | — |

**Un solo Form Request.** El diagrama de capas los nombra, pero en este proyecto su papel lo cumple casi siempre la validación de Livewire (`#[Validate]` y `validate()`): las pantallas que reciben datos son componentes Livewire. La excepción son las descargas de reportes, que son enlaces normales con los filtros en la URL: `FiltroDeReporteRequest` las valida, y la pantalla de reportes usa sus mismas reglas y su mismo método para armar el filtro, así que la tabla y el archivo no pueden entender los filtros de forma distinta.

**Nota sobre `storage`:** los formatos de confidencialidad firmados contienen datos personales y no deben quedar en la carpeta pública (RNF07). Viven en el disco `local` (`storage/app/private/confidencialidad/`, carpetas configurables en `config/laboratorio.php`) y se sirven mediante rutas protegidas por Policy, nunca por enlace directo.

---

## 4. Modelo relacional

### 4.1 Usuarios y roles

**`users`**

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| google_id | string, nullable, unique | identificador devuelto por Google |
| email | string, unique | correo institucional |
| nombre | string | |
| documento | string, nullable, indexado | proviene de la vista institucional; con él se reconoce a la persona al sincronizar |
| codigo_institucional | string, nullable | código de estudiante o docente |
| programa | string, nullable, indexado | programa académico (RF19, RF53) |
| estado | enum(`activo`,`inactivo`) | vigencia institucional: solo la escribe la sincronización (RF20) |
| origen | enum(`matriculado`,`contratado`,`manual`), nullable | las dos primeras las trae la sincronización; `manual` es una cuenta creada por el ADMIN, que la sincronización no toca (RF22) |
| ultima_sincronizacion | timestamp, nullable | |
| deshabilitado_at | timestamp, nullable | marca del ADMIN, aparte de `estado`; la sincronización no la revierte (RF22, D6) |
| deshabilitado_por | FK → users, nullable | |
| motivo_deshabilitacion | text, nullable | |
| timestamps | | |

Puede entrar quien tiene `estado = activo` **y** `deshabilitado_at` nulo; lo decide `AccesoService::puedeEntrar()`. La sincronización nunca borra: quien deja de estar vigente queda inactivo, y sus solicitudes, evaluaciones y formatos siguen apuntando a él.

**Roles y permisos** — los gestiona `spatie/laravel-permission` con sus propias tablas (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`). El proyecto no define tablas de roles propias: mantener dos catálogos en paralelo generaría inconsistencias. A la tabla `roles` del paquete se le añadió la columna `descripcion`.

Los cinco roles se siembran en `RolSeeder`: `admin`, `coordinador`, `administrativo`, `docente`, `estudiante`. Un usuario puede tener varios simultáneamente (RF22). La sincronización da el rol permanente según la vinculación (matriculado → estudiante, contratado → docente) y nunca revoca; los demás roles los reparte el ADMIN.

> El selector de vista (RF21) no se persiste como columna: el rol activo se guarda en sesión y lo aplica el middleware `EstablecerRolActivo`.

### 4.2 Estructura académica

**`materias`** — `id`, `codigo`, `nombre`, `semestre` (int), `activo`, timestamps (RF23)

**`casos_clinicos`** (RF24, RF12)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| nombre | string | ej. atención de parto |
| descripcion | text | |
| imagen | string, nullable | para la landing |
| visible_publico | boolean | RF03 |
| orden | int | orden de aparición pública |
| activo | boolean | |
| capacidad_maxima_estudiantes | int, nullable | RF74. Nulo = sin definir, no limita. No confundir con `salas.capacidad` ni con `capacidades` |

**`caso_clinico_materia`** — `caso_clinico_id`, `materia_id` · muchos a muchos (RF24)

**`capacidades`** — `id`, `nombre` (sangrado, llanto, signos vitales, convulsión…), `icono`

**`caso_clinico_capacidad`** — pivote · alimenta las etiquetas públicas del RF03

**`caso_clinico_item`** — `caso_clinico_id`, `item_inventario_id`, `cantidad` (RF25)
Esta tabla es la que permite la precarga automática de equipos al crear una solicitud (RF29).

**`caso_clinico_sala`** — `caso_clinico_id`, `sala_id` · salas donde suele montarse el escenario; al asignar sala se ofrecen primero (D14)

**`periodos_academicos`** — `nombre` (ej. `2026-2`), `abierto_at`, `abierto_por`, `cerrado_at`, `cerrado_por` (RF75). Un índice único parcial impide dos periodos abiertos a la vez. El periodo vigente es el abierto, o entre semestres el último cerrado; nunca se deriva del calendario.

### 4.3 Inventario y salas

**`items_inventario`** (RF38, RF39, RF66)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| nombre | string | |
| tipo | enum(`simulador`,`equipo_clinico`,`equipo_basico`,`accesorio`) | `accesorio` es «accesorio o repuesto» de un simulador |
| nivel_fidelidad | enum(`baja`,`media`,`alta`), nullable | solo aplica a simuladores; solo lo edita el ADMIN (RF39) |
| simulador_id | FK → items_inventario, nullable | el simulador del que es accesorio o repuesto (RF38) |
| cantidad_total | int | |
| cantidad_operativa | int | |
| cantidad_en_revision | int | |
| cantidad_defectuosa | int | |
| descripcion | text, nullable | |
| activo | boolean | |

Se modela en una sola tabla con discriminador `tipo` en lugar de tablas separadas, porque comparten los mismos atributos y se solicitan de la misma forma. Dos `CHECK` de PostgreSQL:

- `cantidad_total = cantidad_operativa + cantidad_en_revision + cantidad_defectuosa`: el estado funcional es de las unidades, no del ítem (RF66). No hay columna `estado`.
- `(tipo = 'accesorio') = (simulador_id IS NOT NULL)`: todo accesorio tiene simulador y nada más lo tiene.

Las cantidades y `nivel_fidelidad` están fuera de `$fillable`: solo las mueve `InventarioService`. La disponibilidad por franja no se almacena: se calcula como `cantidad_operativa` menos lo comprometido en solicitudes aprobadas.

**`cambios_estado_item`** — `item_inventario_id`, `estado_anterior` (nulo = entrada), `estado_nuevo` (`operativo`, `en_revision`, `defectuoso`, `dado_de_baja`), `cantidad`, `motivo`, `registrado_por`. Historial de solo añadir: reconstruye los contadores por sí solo.

**`salas`** — `id`, `nombre`, `codigo`, `capacidad` (cuánta gente cabe), `bloque`, `piso`, `numero`, `activo` (RF65)

**`ubicaciones_sala`** — `sala_id`, `bloque`, `piso`, `numero`, `registrada_por`, `created_at`. Una fila cada vez que cambia la ubicación (`SalaService`).

**Lista de insumos por pedir (RF67)**

| Tabla | Campos principales |
|---|---|
| `listas_reposicion` | `desde`, `hasta`, `observaciones`, `cerrada_por`, `cerrada_at`. En borrador no guarda líneas; cada lista arranca el día siguiente al cierre de la anterior |
| `lineas_reposicion` | `lista_reposicion_id`, `item_inventario_id` (nullable), `descripcion` (congelada), `motivo`, `cantidad`. Se escriben al cerrar y no cambian después |
| `necesidades_reposicion` | `item_inventario_id` (nullable), `descripcion` (nullable), `cantidad`, `justificacion`, `fecha`, `registrada_por`, `atendida_at`, `atendida_por`, `motivo_atencion`. `CHECK`: ítem o descripción. Lo que se pidió y no había; nunca un ítem con cero unidades |
| `lista_reposicion_necesidad` | qué necesidades entraron en qué lista cerrada |

### 4.4 Solicitudes de escenario

**`solicitudes`** (RF27–RF35)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| docente_id | FK → users | |
| materia_id | FK → materias | |
| caso_clinico_id | FK → casos_clinicos | |
| tipo | enum(`practica`,`evaluacion`) | RF27 |
| fecha | date | |
| hora_inicio | time | |
| hora_fin | time | |
| grupo | string, nullable | A, B, C… (RF28) |
| cantidad_estudiantes | int | sale de la lista de estudiantes |
| estado | enum(`pendiente`,`revisada`,`aprobada`,`rechazada`) | |
| origen | enum(`docente`,`registro_previo`) | `registro_previo` = sesión apartada antes del semestre, nace aprobada (RF57) |
| registrada_por | FK → users, nullable | el administrativo que cargó la sesión apartada |
| formato_intramural_at | timestamp, nullable | RF59 |
| formato_intramural_por | FK → users, nullable | |
| docente_que_dicta_id | FK → users, nullable | reemplazo vigente (RF73): evalúa, gestiona la lista y suma las horas |
| revisada_por | FK → users, nullable | administrativo (RF30) |
| revisada_at | timestamp, nullable | |
| resuelta_por | FK → users, nullable | coordinador (RF31) |
| resuelta_at | timestamp, nullable | |
| motivo_rechazo | text, nullable | RF32 |
| observaciones | text, nullable | |
| timestamps | | |

> **No lleva `sala_id`.** La sala la asigna el administrativo durante la preparación, después de la aprobación del coordinador.

**`solicitud_item`** — `solicitud_id`, `item_inventario_id`, `cantidad` (RF28, RF29)

**`estudiante_solicitud`** — `solicitud_id`, `estudiante_id`, `retirado_at`, `retirado_por`, `motivo_retiro`. Los estudiantes de la sesión (RF28, RF69). Retirar no borra la fila.

**`reprogramaciones`** — `solicitud_id`, fecha, horas y caso clínico anteriores y nuevos, `motivo`, `constancia_comunicacion`, `reprogramada_por`, `created_at`. Solo añadir (RF61).

**`sustituciones`** — `solicitud_id`, `docente_anterior_id`, `docente_nuevo_id`, `motivo`, `registrada_por`, `created_at`. Solo añadir (RF73).

### 4.5 Preparación de escenarios

**`preparaciones`** (RF36–RF37)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| solicitud_id | FK → solicitudes, unique | uno a uno |
| sala_id | FK → salas, nullable | asignada por el administrativo |
| estado | enum(`pendiente`,`en_preparacion`,`preparado`) | |
| preparado_por | FK → users, nullable | |
| preparado_at | timestamp, nullable | |
| observaciones | text, nullable | |

Se crea automáticamente cuando el coordinador aprueba la solicitud. La sala llega nula y se completa el día de la práctica.

**`preparacion_item`** — `preparacion_id`, `item_inventario_id`, `cantidad`, `alistado` (boolean)

### 4.6 Evaluación de habilidades

**`tipos_evaluacion`** — `id`, `nombre`, `descripcion`, `activo` (RF26)

**`materia_tipo_evaluacion`** — pivote muchos a muchos (RF26)

**`items_checklist`** — `id`, `tipo_evaluacion_id`, `descripcion`, `orden`
Plantilla maestra definida por el ADMIN. No editable por el docente (RF43).

**`evaluaciones`** (RF41–RF44)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| solicitud_id | FK → solicitudes, **NOT NULL**, unique | RF41, RF42 |
| tipo_evaluacion_id | FK → tipos_evaluacion | RF43 |
| docente_id | FK → users | |
| estado | enum(`borrador`,`finalizada`) | |
| timestamps | | |

Restricción de integridad: la solicitud referenciada debe tener `tipo = evaluacion` y `estado = aprobada`. Se valida en `EvaluacionService`, no solo en base de datos.

**`evaluacion_items`** — `id`, `evaluacion_id`, `descripcion`, `orden`

Copia congelada de los `items_checklist` en el momento de crear la evaluación (RF44). Es intencional que se dupliquen: si el ADMIN edita la plantilla después, las evaluaciones históricas conservan los ítems con los que realmente se evaluó.

**`evaluacion_estudiantes`** (RF45–RF48)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| evaluacion_id | FK → evaluaciones | |
| estudiante_id | FK → users | |
| resultado | enum(`aprobado`,`no_aprobado`) | lo decide el docente (RF46) |
| intento | int | RF48 |
| observaciones | text, nullable | RF47 |

**`evaluacion_estudiante_item`** — `evaluacion_estudiante_id`, `evaluacion_item_id`, `cumplido` (boolean)

> `resultado` es independiente de los ítems marcados. El sistema no lo calcula (RF46).
> `intento` se resuelve en `EvaluacionService` contando las evaluaciones previas del mismo estudiante para el mismo `tipo_evaluacion`.

### 4.7 Formato de confidencialidad

Es el documento que el laboratorio rotula así en el Drive, e incluye la autorización de captación de imágenes. Antes se llamaba en el código "consentimiento informado", que era nuestro nombre y no el del cliente; se renombró entero (tabla, modelo, Service, Policy, rutas y vistas).

**`plantillas_confidencialidad`** — `id`, `nombre`, `archivo_path`, `version`, `activo`, `subido_por` (RF51)

**`formatos_confidencialidad`** (RF52–RF53)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| firmante_id | FK → users | estudiante o docente |
| plantilla_id | FK → plantillas_confidencialidad | |
| periodo_academico | string | ej. `2026-2` |
| archivo_firmado_path | string, nullable | |
| estado | enum(`pendiente`,`cargado`,`verificado`) | del documento escaneado |
| recibido_fisico_at | timestamp, nullable | RF53, eje aparte del estado |
| recibido_fisico_por | FK → users, nullable | |
| motivo_rechazo | text, nullable | |
| verificado_por | FK → users, nullable | administrativo, coordinación o ADMIN |
| verificado_at | timestamp, nullable | |

Índice único sobre (`firmante_id`, `periodo_academico`): el formato se entrega una sola vez por semestre y se renueva al iniciar el siguiente (RF52).

La columna se llama `firmante_id` y no `estudiante_id` porque lo firma todo el que entra a la práctica, docente incluido (RF51–RF52). Qué roles son esos lo dice `Rol::queFirmanElFormato()`, y de ahí leen la Policy y el Service.

### 4.7.1 Roles con vigencia (RF63–RF64)

El pivote de spatie gana dos columnas, y son las que se hacen cumplir:

**`model_has_roles`** — `role_id`, `model_type`, `model_id`, **`desde`** (date, nullable), **`hasta`** (date, nullable)

Nulo significa "sin límite por ese lado": `hasta` nulo es un rol permanente, `desde` nulo es un rol que siempre ha valido. `User::roles()` filtra por esas dos columnas en SQL, así que el vencimiento alcanza todos los caminos de lectura de permisos sin que corra ningún job.

**`asignaciones_de_rol`** — el rastro, de solo añadir:

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| user_id | FK → users | a quién |
| role_id | FK → roles | qué rol |
| desde | date, nullable | |
| hasta | date, nullable | nulo = permanente |
| motivo | text, nullable | obligatorio al elevar a coordinador |
| asignado_por | FK → users, nullable | nulo = anterior al historial |
| revocada_at | timestamp, nullable | |
| revocada_por | FK → users, nullable | |

`CHECK (hasta IS NULL OR hasta >= desde)`.

Hace falta aparte del pivote porque la llave primaria de este es (`role_id`, `model_id`, `model_type`): solo cabe una fila por usuario y rol, así que un segundo paso del mismo pasante pisaría las fechas del primero.

### 4.7.2 Control de acceso y auditoría

**`bloqueos`** — `user_id`, `motivo`, `bloqueado_por`, `levantado_at`, `levantado_por`, `motivo_levantamiento` (RF68). Un bloqueo se levanta, no se borra. Un índice único parcial deja un solo bloqueo vigente por persona.

**`bitacora`** — `accion` (enum `AccionAuditada`), `user_id` (quién), morph `auditable` (sobre qué), `descripcion`, `motivo`, `created_at` (RF62). Solo añadir; la escribe el Service en la misma transacción que la acción.

**`ajustes_laboratorio`** — `clave`, `valor`. Claves fijas en el enum `AjusteDelLaboratorio`; las cambia el ADMIN sin desplegar.

### 4.8 Contenido público (CMS)

| Tabla | Campos principales | RF |
|---|---|---|
| `configuracion_landing` | `clave`, `valor` (pares clave-valor). Las claves las fija el enum `ClaveConfiguracionLanding`: título y subtítulo del hero, ruta del video del hero en el disco público, correo, teléfono y dirección de contacto. Se editan en una sola página de Filament | RF02, RF11 |
| `estadisticas_landing` | `etiqueta`, `valor` (texto, se publica tal cual: `22`, `+700`), `orden`, `activo`. Valores manuales del ADMIN, no calculados de las tablas | RF01, RF10 |
| `galeria_fotos` | `titulo`, `imagen_path`, `orden`, `activo` | RF01, RF10 |
| `videos_institucionales` | `titulo`, `url`, `orden`, `activo`. Le faltan el archivo y la portada que pide el RF17 | RF08, RF17 |
| `equipos_destacados` | `nombre`, `resumen` (una frase), `imagen`, `caracteristicas` (jsonb: hasta cuatro frases cortas), `orden`, `activo`. Los protagonistas de la portada: simuladores, sala inmersiva, mesa de anatomía virtual; el primero es el principal. No es el inventario: no tiene unidades ni estado | RF01, RF10 |
| `talleres` | `titulo`, `descripcion`, `imagen`, `tema`, `fecha`, `modalidad` (`virtual` \| `presencial`), `muestra_formulario`, `orden`, `activo` | RF04, RF13 |
| `eventos` | `titulo`, `descripcion`, `imagen`, `fecha`, `tipo_evento_id` (FK → `tipos_evento`, restrict), `abierto_publico`, `orden`, `activo` | RF05, RF14 |
| `tipos_evento` | `nombre` (único), `activo`. Catálogo que gestiona el ADMIN desde Filament; no se borran, se desactivan | RF05, RF14 |
| `certificaciones` | `nombre`, `entidad`, `imagen_insignia`, `descripcion`, `orden`, `activo` | RF06, RF15 |
| `perfiles_docentes` | `user_id` (nullable), `nombre`, `cargo`, `foto`, `orden`, `activo` | RF07, RF16 |
| `titulos_docente` | `perfil_docente_id`, `titulo`, `institucion`, `orden` | RF07 |
| `solicitudes_informacion` | `taller_id`, `nombre`, `email`, `telefono`, `mensaje`, `enviado_at` | RF09 |

Todas las tablas de contenido llevan `activo` y `orden`: publicar, despublicar y reordenar se hace desde el panel, sin tocar código.

---

## 5. Relaciones principales

```
users ──< model_has_roles >── roles                (vigencia: desde / hasta)
users ──< asignaciones_de_rol >── roles            (rastro, solo añadir)
users ──< bloqueos
users ──< bitacora ──> auditable                   (morph: solicitud, ítem, usuario, bloqueo…)

materias ──< caso_clinico_materia >── casos_clinicos
materias ──< materia_tipo_evaluacion >── tipos_evaluacion

casos_clinicos ──< caso_clinico_capacidad >── capacidades
casos_clinicos ──< caso_clinico_item >── items_inventario
casos_clinicos ──< caso_clinico_sala >── salas
items_inventario ──> items_inventario              (accesorio → su simulador)
items_inventario ──< cambios_estado_item
salas ──< ubicaciones_sala

solicitudes ──> users (docente)
solicitudes ──> materias
solicitudes ──> casos_clinicos
solicitudes ──> users (docente que dicta, si hubo sustitución)
solicitudes ──< solicitud_item >── items_inventario
solicitudes ──< estudiante_solicitud >── users (estudiante)
solicitudes ──< reprogramaciones
solicitudes ──< sustituciones
solicitudes ──1:1── preparaciones ──> salas
preparaciones ──< preparacion_item >── items_inventario

solicitudes ──1:1── evaluaciones ──> tipos_evaluacion
tipos_evaluacion ──< items_checklist
evaluaciones ──< evaluacion_items                (copia congelada)
evaluaciones ──< evaluacion_estudiantes ──> users (estudiante)
evaluacion_estudiantes ──< evaluacion_estudiante_item >── evaluacion_items

users ──< formatos_confidencialidad >── plantillas_confidencialidad

listas_reposicion ──< lineas_reposicion            (copia congelada al cerrar)
listas_reposicion ──< lista_reposicion_necesidad >── necesidades_reposicion
```

---

## 6. Reglas de negocio que el modelo garantiza

1. **Ninguna evaluación existe sin escenario apartado.** `evaluaciones.solicitud_id` es obligatorio y único, y la solicitud debe ser de tipo evaluación y estar aprobada (RF41, RF42).
2. **El histórico de evaluaciones es inmutable.** Los ítems se copian a `evaluacion_items` al momento de crear la evaluación (RF44).
3. **El resultado lo decide el docente.** `resultado` no se deriva de `evaluacion_estudiante_item.cumplido` (RF46).
4. **La sala no se elige al solicitar.** Solo aparece en `preparaciones`, completada por el administrativo (RF28, RF36).
5. **La disponibilidad de inventario no la ve el docente.** El acceso a `items_inventario` se restringe por Policy a administrativo y coordinador (RF40).
6. **El acceso depende de la vigencia institucional.** `users.estado` se actualiza por sincronización, no manualmente (RF19, RF20). La deshabilitación del ADMIN es otra columna, que la sincronización no revierte (RF22).
7. **El formato de confidencialidad se renueva cada periodo académico**, el que abre el laboratorio (RF75). El índice único por firmante y periodo impide duplicados dentro del mismo periodo (RF52). Lo firman estudiantes y docentes.
8. **Quien no tiene el formato o está bloqueado no entra ni puede ser evaluado** (RF45, RF70). Lo decide `ParticipacionService`.
9. **Ningún escenario admite más estudiantes de los que el ADMIN le registró** (RF74), salvo que el dato esté sin definir.
10. **Las unidades del inventario siempre suman el total**, por `CHECK` (RF66), y todo movimiento queda en `cambios_estado_item`.
11. **Una lista de insumos por pedir cerrada no cambia** (RF67): sus líneas se congelan, como los ítems del checklist.
12. **Un rol con fecha de fin vence solo** (RF63, RF64): el filtro está en `User::roles()`, en SQL.

---

## 6.1 Matriz de permisos

Resume qué rol ejecuta cada acción sensible. El coordinador hereda todo lo del administrativo, por lo que las filas marcadas para administrativo también aplican a coordinación.

| Acción | ADMIN | Coordinador | Administrativo | Docente | Estudiante |
|---|:--:|:--:|:--:|:--:|:--:|
| Gestionar contenido de la landing | ✓ | | | | |
| Crear, editar y deshabilitar cuentas | ✓ | | | | |
| Gestionar materias, casos clínicos y tipos de evaluación | ✓ | | | | |
| Registrar nivel de fidelidad de simuladores | ✓ | | | | |
| Registrar y actualizar inventario | ✓ | ✓ | ✓ | | |
| Preparar la lista de insumos por pedir | ✓ | ✓ | ✓ | | |
| Cerrar la lista de insumos por pedir | ✓ | ✓ | | | |
| Cambiar el estado funcional de un ítem | ✓ | ✓ | ✓ | | |
| Dar de baja un ítem | ✓ | ✓ | | | |
| Consultar disponibilidad de inventario | ✓ | ✓ | ✓ | | |
| Solicitar escenario | | | | ✓ | |
| Revisar o rechazar solicitudes pendientes | | ✓ | ✓ | | |
| Aprobar o rechazar solicitudes revisadas | ✓ | ✓ | | | |
| Registrar sesiones apartadas y su formato intramural | | ✓ | ✓ | | |
| Reprogramar una sesión o sustituir a su docente | | ✓ | ✓ | | |
| Asignar sala y preparar escenario | | ✓ | ✓ | | |
| Abrir, cerrar y reabrir el periodo académico | ✓ | ✓ | ✓ | | |
| Ver calendario de reservas aprobadas | ✓ | ✓ | ✓ | ✓ | ✓ |
| Crear y registrar evaluaciones | | | | ✓ | |
| Consultar resultados propios | | | | | ✓ |
| Cargar plantilla del formato de confidencialidad | ✓ | | | | |
| Entregar el formato de confidencialidad firmado | | | | ✓ | ✓ |
| Verificar un formato de confidencialidad entregado | ✓ | ✓ | ✓ | | |
| Bloquear y levantar bloqueos | ✓ | ✓ | | | |
| Consultar la bitácora | ✓ | ✓ | | | |
| Generar reportes | ✓ | ✓ | | | |
| Asignar o revocar roles | ✓ | | | | |

Notas de implementación:

- El **nivel de fidelidad** (RF39) es el único atributo del inventario reservado al ADMIN. Los administrativos y coordinadores editan el resto de campos, por lo que la restricción se aplica a nivel de campo dentro de la Policy de `ItemInventario`, no al recurso completo.
- La **verificación del formato de confidencialidad** (RF52) la ejerce el administrativo, que es quien recibe las entregas a diario; coordinación y ADMIN conservan el permiso para supervisar. Como el documento firmado lleva datos personales, quien verifica también lo descarga (RNF07).
- **Quién firma el formato** (RF51–RF52): estudiantes y docentes, porque el docente dirige la sesión pero está dentro de ella y la autorización de captación de imágenes lo cubre igual. Quien verifica no firma, y nadie entrega el formato en nombre de otro. La pantalla de estado los lista juntos, con el docente marcado, y busca por nombre, correo o código institucional (RF71).
- La **aprobación de una solicitud** exige que esté en estado `revisada`: sin revisión administrativa previa no aprueba nadie. El ADMIN aprueba en ausencia de la coordinadora, pero no revisa, así que aprobador y revisor nunca son la misma persona.
- La **lista de insumos por pedir** (RF67) es un documento que se cierra, no una consulta: mientras está en borrador se calcula desde el historial de inventario y las necesidades anotadas, y al cerrarla sus líneas se congelan en `lineas_reposicion`. Es el soporte de una carta institucional, así que no puede cambiar después de entregarse. Lo que se pidió y no existe en el catálogo vive en `necesidades_reposicion` con la llave foránea nula, nunca como un ítem de inventario con cero unidades. Los movimientos de inventario se filtran por rango porque son un flujo del periodo; las necesidades son un saldo pendiente y entran en todos los borradores hasta que alguien las atienda. Cada lista arranca donde terminó la anterior, y el corte por día se calcula en la zona horaria de Colombia.
- El **estado funcional del inventario** (RF66) es de las unidades, no del ítem: tres contadores en `items_inventario` y el historial de movimientos en `cambios_estado_item`, con cantidad, motivo y responsable. Un `CHECK` garantiza que los contadores sumen el total. No se confunde con la disponibilidad, que no se almacena: se calcula por franja horaria sobre las unidades operativas. La baja descuenta del total, es irreversible y la reserva la Policy a coordinación y ADMIN.
- La **entrega en físico del formato** (RF53) se modela como dos columnas de `formatos_confidencialidad` (`recibido_fisico_at`, `recibido_fisico_por`), no como un caso del enum `EstadoFormatoConfidencialidad`. Son dos ejes distintos que se cruzan libremente: el estado describe el ciclo del documento escaneado y la entrega física describe un hecho del mundo que sobrevive a todas sus transiciones. Marcarla es del administrativo, y habilita el ingreso a prácticas igual que un documento verificado. Vale igual para un docente: llega a la misma puerta, con el mismo papel.
- La **capacidad máxima de estudiantes** de un escenario (RF74) es parte de la gestión de casos clínicos, reservada al ADMIN. Se comprueba en `SolicitudService` al crear la solicitud. Un caso sin capacidad registrada no limita: `null` se lee como "sin definir".
- Los **roles con vigencia** (RF63–RF64) se hacen cumplir en `User::roles()`, que filtra `desde`/`hasta` del pivote en SQL: el vencimiento alcanza `hasRole()`, `can()`, las Policies, el scope `role()` y el `loadMissing()` de spatie, sin ningún job de por medio. Revocar borra la fila del pivote en vez de acortar `hasta`, porque con vigencia por día acortarla dejaría el rol vivo hasta medianoche. Toda escritura pasa por `AsignacionDeRolService`: `assignRole()` de spatie revienta contra la llave primaria si queda una fila vencida. Asignar y revocar es solo del ADMIN, y elevar a coordinador exige motivo. El selector de rol (RF21) no ofrece roles vencidos y entra por el permanente, no por el temporal.
- La **vigencia institucional** (regla 8) se decide en `AccesoService` y se comprueba en la entrada y en cada petición, con `VerificarUsuarioActivo`. Ese middleware también está registrado como persistente en Livewire, porque las acciones de una pantalla ya abierta van a `/livewire/update` y no pasan por las rutas del panel. Una cuenta deshabilitada por el ADMIN queda fuera por la misma puerta, con su propio mensaje.
- La **sincronización institucional** (RF20) solo toca cuentas de origen `matriculado` o `contratado`, no borra, solo da roles permanentes y se frena si una pasada fuera a desactivar más del umbral configurado, avisando a cada ADMIN. La fuente es intercambiable (`FuenteInstitucional`); hoy solo existe la simulada, y la pasada programada está apagada por defecto.
- El **calendario** (RF34) es la única vista compartida por los cinco roles.

---

## 7. Consultas de reportes

**RF54 — uso de escenarios:** agregación sobre `solicitudes` aprobadas unida a `preparaciones`, `materias`, `casos_clinicos` y `salas`, agrupando por docente, materia, semestre, caso clínico, sala y tipo de sesión. Las horas se calculan desde `hora_inicio` y `hora_fin`.

**RF55 — resultados de evaluación:** agregación sobre `evaluacion_estudiantes` unida a `evaluaciones`, `solicitudes` y `materias`. Exportable a PDF y Excel.

**RF56 — evaluaciones no registradas:** `solicitudes` con `tipo = evaluacion`, `estado = aprobada`, `fecha` anterior a hoy y sin registro asociado en `evaluaciones`.

### Exportación

Los reportes se consultan en pantalla (`/panel/reportes`, coordinación y ADMIN, Gate `generarReportes`) y se descargan en dos formatos: PDF mediante plantillas Blade renderizadas con dompdf, y Excel mediante clases `Export` que reutilizan la misma consulta del `ReporteService`. La consulta se escribe una sola vez y alimenta las tres salidas (pantalla, PDF y Excel) para evitar divergencias entre lo que se ve y lo que se descarga.

Los reportes con muchos registros se exportan mediante consultas por lotes (`chunk`), no cargando todo en memoria, para no comprometer el rendimiento del contenedor.

### Índices recomendados

- `solicitudes`: (`fecha`, `estado`), (`docente_id`), (`materia_id`)
- `evaluacion_estudiantes`: (`estudiante_id`), (`evaluacion_id`)
- `preparaciones`: (`sala_id`, `estado`)
- `users`: (`email`, `estado`), (`documento`), (`programa`)

---

## 8. Despliegue

Dos compose sobre un mismo `docker/php/Dockerfile` de varias etapas:

- `docker-compose.yml`, desarrollo: el código montado desde el disco, Vite con recarga en caliente y la base publicada en el puerto 5432.
- `docker-compose.produccion.yml`, producción: el código, `vendor/` sin dependencias de desarrollo y los assets compilados van dentro de las imágenes. El servidor solo necesita Docker.

| Servicio de producción | Imagen | Función |
|---|---|---|
| `app` | etapa `produccion` (php:8.3-fpm) | la aplicación. Al arrancar guarda en caché configuración, rutas y vistas (`docker/produccion/arrancar.sh`) |
| `queue` | la misma | `queue:work`, reiniciado cada hora |
| `programador` | la misma | `schedule:work`: aviso diario del formato intramural (RF60) y, si se activa, la sincronización de usuarios (RF20) |
| `web` | etapa `web` (nginx:alpine) | los archivos de `public/` y `/storage`; lo demás a PHP-FPM |
| `db` | postgres:16 | sin puertos publicados fuera de Docker |
| `copias` | postgres:16-alpine | copia diaria de la base, las imágenes públicas y los documentos privados, con retención y sumas SHA-256 |
| `restauracion` | postgres:16-alpine | solo con `run`: restaura una copia, comprobando las sumas antes de borrar nada |

Tres volúmenes: `datos_postgres`, `archivos_publicos` (`storage/app/public`) y `archivos_privados` (`storage/app/private`, el formato de confidencialidad firmado). Nada de lo que suben los usuarios entra en una imagen (`.dockerignore`). Variables sensibles en `.env`, a partir de `.env.produccion.example`, fuera del control de versiones.

El HTTPS lo termina quien esté delante del nginx del compose; su IP va en `PROXIES_DE_CONFIANZA` (`config/trustedproxy.php`). Las cabeceras de seguridad las pone el middleware `CabecerasDeSeguridad` (`config/seguridad.php`), y la CI levanta el compose de producción y comprueba cabeceras, assets, que los errores no enseñen trazas y una copia restaurada de punta a punta.

La sincronización de usuarios es el comando `usuarios:sincronizar`, que el servicio `programador` corre con la frecuencia de `SINCRONIZACION_FRECUENCIA` solo si `SINCRONIZACION_PROGRAMADA=true`. Está apagada por defecto mientras la fuente sea la simulada.

Las métricas del sitio público (Plausible o Matomo) siguen sin decidir.

---

## 9. Pendientes que pueden afectar el modelo

1. ~~**Autoaprobación.**~~ Resuelto (RF31): no se bloquea, porque siempre media la revisión administrativa.
2. **Estructura de la vista institucional:** los campos exactos que entregue la universidad pueden obligar a ajustar `users.documento`, `codigo_institucional`, `programa` y `origen`. El contrato con la sincronización es `PersonaInstitucional`: la fuente real traduce sus columnas a ese formato.
3. ~~**Aviso de sala al docente.**~~ Resuelto (RF36): evento `SalaAsignada` y correo al asignar o cambiar la sala.
4. ~~**Volumen real de usuarios.**~~ Resuelto: el cliente confirmó ~700 estudiantes y ~150 docentes (RNF01 actualizado). El dimensionamiento no cambia la arquitectura ni el esquema; si alguna vez hiciera falta más capacidad, la vía sigue siendo Laravel Octane, que es configuración del contenedor.
