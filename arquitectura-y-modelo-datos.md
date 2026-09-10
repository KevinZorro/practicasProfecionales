# Arquitectura y Modelo de Datos
## Plataforma de Gestión del Laboratorio de Simulación Clínica

Documento técnico de referencia. Deriva de los requerimientos funcionales RF01–RF56 y no funcionales RNF01–RNF10.

---

## 1. Visión general

Aplicación web monolítica en Laravel, con renderizado del lado del servidor y componentes reactivos puntuales. Se despliega en contenedores Docker sobre el servidor institucional (Debian 13 Trixie).

Se eligió monolito y no arquitectura de servicios separados porque el sistema tiene un único dominio de negocio, un solo equipo de desarrollo y un volumen de tráfico moderado. Separar backend y frontend agregaría complejidad de despliegue y mantenimiento sin beneficio real a esta escala.

### Stack

| Capa | Tecnología |
|---|---|
| Lenguaje | PHP 8.3 |
| Framework | Laravel 12 LTS (12.60 o superior) |
| Vistas | Blade |
| Interactividad | Livewire 3 + Alpine.js |
| Estilos | Tailwind CSS |
| Panel administrativo | Filament 3 |
| Base de datos | PostgreSQL 16 (o MySQL 8) |
| Autenticación | Laravel Socialite (Google OAuth) |
| Permisos | spatie/laravel-permission ^7.1 |
| Calendario | FullCalendar.js |
| Exportación PDF | barryvdh/laravel-dompdf |
| Exportación Excel | maatwebsite/excel |
| Métricas | Plausible o Matomo (contenedor aparte) |
| Servidor web | Nginx |
| Contenedores | Docker + Docker Compose |

**Por qué Filament:** el ADMIN gestiona 8 módulos de contenido público (RF10–RF17) más la estructura académica (RF22–RF26). Construir esos CRUD a mano consumiría gran parte del presupuesto de horas de desarrollo. Filament los genera a partir de los modelos, incluyendo carga de imágenes, ordenamiento y filtros.

---

## 2. Arquitectura por capas

```
HTTP Request
    │
    ▼
Route ──► Middleware (auth, rol activo)
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

- **Form Request** — valida formato y obligatoriedad de los datos que entran.
- **Policy** — decide si el usuario puede ejecutar la acción según su rol activo. Aquí se implementa la herencia coordinador → administrativo (RNF04).
- **Service** — concentra las reglas de negocio: aprobar una solicitud, calcular el número de intento, copiar el checklist al crear una evaluación, precargar inventario desde un caso clínico. No se escriben en controladores ni en modelos.
- **Model** — relaciones, scopes y accessors. Sin lógica de negocio.
- **Event / Listener** — el correo de resultado de solicitud (RF33) se dispara como evento para no bloquear la respuesta HTTP.

### Reglas que viven en Services

| Service | Reglas que encapsula |
|---|---|
| `SolicitudService` | Crear solicitud, precargar inventario del caso clínico, transiciones de estado (pendiente → revisada → aprobada/rechazada), disparar notificación |
| `PreparacionService` | Crear preparación al aprobarse una solicitud, asignar sala, marcar ítems alistados, cambiar estado de montaje |
| `EvaluacionService` | Validar que exista solicitud aprobada de tipo evaluación, copiar ítems del checklist, calcular número de intento por estudiante |
| `InventarioService` | Altas y bajas, cálculo de disponibilidad por fecha y franja horaria |
| `ConsentimientoService` | Determinar el periodo académico vigente, verificar si el estudiante ya entregó consentimiento en ese periodo, bloquear prácticas si está pendiente |
| `ReporteService` | Agregaciones de uso de escenarios y de resultados de evaluación, y generación de los archivos PDF y Excel |
| `UsuarioSyncService` | Sincronizar contra la vista institucional, activar y desactivar usuarios |

---

## 3. Estructura de carpetas

```
proyecto/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       └── SincronizarUsuarios.php      # tarea programada RF19–RF20
│   ├── Events/
│   │   ├── SolicitudAprobada.php
│   │   └── SolicitudRechazada.php
│   ├── Exports/
│   │   ├── UsoEscenariosExport.php          # Excel RF54
│   │   └── ResultadosEvaluacionExport.php   # Excel RF55
│   ├── Filament/
│   │   └── Resources/                        # CRUD del panel ADMIN
│   │       ├── MateriaResource.php
│   │       ├── CasoClinicoResource.php
│   │       ├── TipoEvaluacionResource.php
│   │       ├── ItemInventarioResource.php
│   │       ├── SalaResource.php
│   │       ├── UserResource.php
│   │       ├── TallerResource.php
│   │       ├── EventoResource.php
│   │       ├── CertificacionResource.php
│   │       ├── PerfilDocenteResource.php
│   │       ├── GaleriaFotoResource.php
│   │       ├── VideoInstitucionalResource.php
│   │       └── ConsentimientoPlantillaResource.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   └── GoogleController.php
│   │   │   ├── Public/
│   │   │   │   ├── LandingController.php
│   │   │   │   └── SolicitudInformacionController.php
│   │   │   ├── Docente/
│   │   │   ├── Estudiante/
│   │   │   ├── Administrativo/
│   │   │   └── Coordinador/
│   │   ├── Middleware/
│   │   │   ├── VerificarUsuarioActivo.php
│   │   │   └── EstablecerRolActivo.php        # selector de vista RF21
│   │   └── Requests/
│   │       ├── Solicitud/
│   │       ├── Evaluacion/
│   │       └── Inventario/
│   ├── Listeners/
│   │   └── EnviarCorreoResultadoSolicitud.php
│   ├── Livewire/
│   │   ├── Solicitud/
│   │   │   ├── FormularioSolicitud.php        # precarga de inventario RF29
│   │   │   └── BandejaRevision.php
│   │   ├── Preparacion/
│   │   │   └── TableroDiario.php              # RF36–RF37
│   │   ├── Evaluacion/
│   │   │   └── ChecklistEvaluacion.php        # multi-estudiante RF45–RF47
│   │   ├── Calendario/
│   │   │   └── CalendarioEscenarios.php
│   │   └── Reportes/
│   ├── Mail/
│   │   ├── SolicitudAprobadaMail.php
│   │   ├── SolicitudRechazadaMail.php
│   │   └── SolicitudInformacionMail.php
│   ├── Models/
│   ├── Policies/
│   ├── Providers/
│   └── Services/
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
│       ├── RolSeeder.php
│       └── DatosInicialesSeeder.php
├── docker/
│   ├── php/
│   │   └── Dockerfile
│   ├── nginx/
│   │   └── default.conf
│   └── postgres/
├── public/
├── resources/
│   ├── css/
│   ├── js/
│   └── views/
│       ├── layouts/
│       │   ├── public.blade.php
│       │   └── panel.blade.php
│       ├── public/                            # landing RF01–RF09
│       │   ├── index.blade.php
│       │   └── secciones/
│       ├── docente/
│       ├── estudiante/
│       ├── administrativo/
│       ├── coordinador/
│       ├── livewire/
│       ├── reportes/
│       │   └── pdf/                           # plantillas Blade para dompdf
│       └── components/
├── routes/
│   ├── web.php
│   ├── auth.php
│   └── console.php
├── storage/
│   └── app/
│       ├── public/
│       │   └── landing/
│       │       ├── hero/
│       │       ├── galeria/
│       │       ├── talleres/
│       │       ├── eventos/
│       │       ├── docentes/
│       │       ├── certificaciones/
│       │       └── casos-clinicos/
│       └── consentimientos/                   # privado, no público
├── tests/
├── docker-compose.yml
└── .env.example
```

**Nota sobre `storage`:** los consentimientos firmados contienen datos personales de estudiantes y no deben quedar en la carpeta pública (RNF07). Se sirven mediante una ruta protegida por Policy, nunca por enlace directo.

---

## 4. Modelo relacional

### 4.1 Usuarios y roles

**`users`**

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| google_id | string, nullable, unique | identificador devuelto por Google |
| email | string, unique | correo institucional |
| password | string, nullable | vestigio de Breeze; la autenticación es por Google |
| nombre | string | |
| documento | string, nullable | proviene de la vista institucional |
| codigo_institucional | string, nullable | código de estudiante o docente |
| estado | enum(`activo`,`inactivo`) | RF20 |
| origen | enum(`matriculado`,`contratado`), nullable | según la vista institucional |
| ultima_sincronizacion | timestamp, nullable | |
| timestamps | | |

**Roles y permisos** — los gestiona `spatie/laravel-permission` con sus propias tablas (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`). El proyecto no define tablas de roles propias: mantener dos catálogos en paralelo generaría inconsistencias. A la tabla `roles` del paquete se le añadió la columna `descripcion`.

Los cinco roles se siembran en `RolSeeder`: `admin`, `coordinador`, `administrativo`, `docente`, `estudiante`. Un usuario puede tener varios simultáneamente (RF22).

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

**`caso_clinico_materia`** — `caso_clinico_id`, `materia_id` · muchos a muchos (RF24)

**`capacidades`** — `id`, `nombre` (sangrado, llanto, signos vitales, convulsión…), `icono`

**`caso_clinico_capacidad`** — pivote · alimenta las etiquetas públicas del RF03

**`caso_clinico_item`** — `caso_clinico_id`, `item_inventario_id`, `cantidad` (RF25)
Esta tabla es la que permite la precarga automática de equipos al crear una solicitud (RF29).

### 4.3 Inventario y salas

**`items_inventario`** (RF38, RF39)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| nombre | string | |
| tipo | enum(`simulador`,`equipo_clinico`,`equipo_basico`) | |
| nivel_fidelidad | enum(`baja`,`media`,`alta`), nullable | solo aplica a simuladores |
| cantidad_total | int | |
| descripcion | text, nullable | |
| estado | enum(`disponible`,`mantenimiento`,`baja`) | |
| activo | boolean | |

Se modela en una sola tabla con discriminador `tipo` en lugar de tres tablas separadas, porque los tres comparten los mismos atributos y se solicitan de la misma forma. `nivel_fidelidad` queda nulo para equipos.

**`salas`** — `id`, `nombre`, `codigo`, `capacidad`, `activo`

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
| cantidad_estudiantes | int | |
| estado | enum(`pendiente`,`revisada`,`aprobada`,`rechazada`) | |
| revisada_por | FK → users, nullable | administrativo (RF30) |
| revisada_at | timestamp, nullable | |
| resuelta_por | FK → users, nullable | coordinador (RF31) |
| resuelta_at | timestamp, nullable | |
| motivo_rechazo | text, nullable | RF32 |
| observaciones | text, nullable | |
| timestamps | | |

> **No lleva `sala_id`.** La sala la asigna el administrativo durante la preparación, después de la aprobación del coordinador.

**`solicitud_item`** — `solicitud_id`, `item_inventario_id`, `cantidad` (RF28, RF29)

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

### 4.7 Consentimiento informado

**`consentimientos_plantilla`** — `id`, `nombre`, `archivo_path`, `version`, `activo`, `subido_por` (RF51)

**`consentimientos_estudiante`** (RF52–RF53)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| estudiante_id | FK → users | |
| plantilla_id | FK → consentimientos_plantilla | |
| periodo_academico | string | ej. `2026-2` |
| archivo_firmado_path | string, nullable | |
| estado | enum(`pendiente`,`cargado`,`verificado`) | |
| verificado_por | FK → users, nullable | solo coordinador o ADMIN |
| verificado_at | timestamp, nullable | |

Índice único sobre (`estudiante_id`, `periodo_academico`): el consentimiento se entrega una sola vez por semestre y se renueva al iniciar el siguiente (RF52).

### 4.8 Contenido público (CMS)

| Tabla | Campos principales | RF |
|---|---|---|
| `configuracion_landing` | `clave`, `valor` (pares clave-valor: video hero, textos, contacto) | RF02, RF11 |
| `estadisticas_landing` | `etiqueta`, `valor`, `orden` | RF01 |
| `galeria_fotos` | `titulo`, `imagen_path`, `orden`, `activo` | RF01, RF10 |
| `videos_institucionales` | `titulo`, `url`, `orden`, `activo` | RF08, RF17 |
| `talleres` | `titulo`, `descripcion`, `imagen`, `tema`, `fecha`, `modalidad` (`virtual` \| `presencial`), `muestra_formulario`, `orden`, `activo` | RF04, RF13 |
| `eventos` | `titulo`, `descripcion`, `imagen`, `fecha`, `tipo` (valores pendientes de confirmar), `abierto_publico`, `orden`, `activo` | RF05, RF14 |
| `certificaciones` | `nombre`, `entidad`, `imagen_insignia`, `descripcion`, `orden`, `activo` | RF06, RF15 |
| `perfiles_docentes` | `user_id` (nullable), `nombre`, `cargo`, `foto`, `orden`, `activo` | RF07, RF16 |
| `titulos_docente` | `perfil_docente_id`, `titulo`, `institucion`, `orden` | RF07 |
| `solicitudes_informacion` | `taller_id`, `nombre`, `email`, `telefono`, `mensaje`, `enviado_at` | RF09 |

Todas las tablas de contenido llevan `activo` y `orden`: publicar, despublicar y reordenar se hace desde el panel, sin tocar código.

---

## 5. Relaciones principales

```
users ──< role_user >── roles

materias ──< caso_clinico_materia >── casos_clinicos
materias ──< materia_tipo_evaluacion >── tipos_evaluacion

casos_clinicos ──< caso_clinico_capacidad >── capacidades
casos_clinicos ──< caso_clinico_item >── items_inventario

solicitudes ──> users (docente)
solicitudes ──> materias
solicitudes ──> casos_clinicos
solicitudes ──< solicitud_item >── items_inventario
solicitudes ──1:1── preparaciones ──> salas
preparaciones ──< preparacion_item >── items_inventario

solicitudes ──1:1── evaluaciones ──> tipos_evaluacion
tipos_evaluacion ──< items_checklist
evaluaciones ──< evaluacion_items                (copia congelada)
evaluaciones ──< evaluacion_estudiantes ──> users (estudiante)
evaluacion_estudiantes ──< evaluacion_estudiante_item >── evaluacion_items

users ──< consentimientos_estudiante >── consentimientos_plantilla
```

---

## 6. Reglas de negocio que el modelo garantiza

1. **Ninguna evaluación existe sin escenario apartado.** `evaluaciones.solicitud_id` es obligatorio y único, y la solicitud debe ser de tipo evaluación y estar aprobada (RF41, RF42).
2. **El histórico de evaluaciones es inmutable.** Los ítems se copian a `evaluacion_items` al momento de crear la evaluación (RF44).
3. **El resultado lo decide el docente.** `resultado` no se deriva de `evaluacion_estudiante_item.cumplido` (RF46).
4. **La sala no se elige al solicitar.** Solo aparece en `preparaciones`, completada por el administrativo (RF28, RF36).
5. **La disponibilidad de inventario no la ve el docente.** El acceso a `items_inventario` se restringe por Policy a administrativo y coordinador (RF40).
6. **El acceso depende de la vigencia institucional.** `users.estado` se actualiza por sincronización programada, no manualmente (RF19, RF20).
7. **El consentimiento se renueva cada semestre.** El índice único por estudiante y periodo impide duplicados dentro del mismo semestre y obliga a un registro nuevo al cambiar de periodo (RF52).

---

## 6.1 Matriz de permisos

Resume qué rol ejecuta cada acción sensible. El coordinador hereda todo lo del administrativo, por lo que las filas marcadas para administrativo también aplican a coordinación.

| Acción | ADMIN | Coordinador | Administrativo | Docente | Estudiante |
|---|:--:|:--:|:--:|:--:|:--:|
| Gestionar contenido de la landing | ✓ | | | | |
| Gestionar usuarios y roles | ✓ | | | | |
| Gestionar materias, casos clínicos y tipos de evaluación | ✓ | | | | |
| Registrar nivel de fidelidad de simuladores | ✓ | | | | |
| Registrar y actualizar inventario | ✓ | ✓ | ✓ | | |
| Consultar disponibilidad de inventario | ✓ | ✓ | ✓ | | |
| Solicitar escenario | | | | ✓ | |
| Revisar solicitudes | | ✓ | ✓ | | |
| Aprobar o rechazar solicitudes | | ✓ | | | |
| Asignar sala y preparar escenario | | ✓ | ✓ | | |
| Ver calendario de reservas aprobadas | ✓ | ✓ | ✓ | ✓ | ✓ |
| Crear y registrar evaluaciones | | | | ✓ | |
| Consultar resultados propios | | | | | ✓ |
| Cargar plantilla de consentimiento | ✓ | | | | |
| Verificar consentimiento de estudiantes | ✓ | ✓ | | | |
| Generar reportes | ✓ | ✓ | | | |

Notas de implementación:

- El **nivel de fidelidad** (RF39) es el único atributo del inventario reservado al ADMIN. Los administrativos y coordinadores editan el resto de campos, por lo que la restricción se aplica a nivel de campo dentro de la Policy de `ItemInventario`, no al recurso completo.
- La **verificación del consentimiento** (RF53) queda fuera del alcance del administrativo, a diferencia del resto de sus funciones operativas.
- El **calendario** (RF34) es la única vista compartida por los cinco roles.

---

## 7. Consultas de reportes

**RF54 — uso de escenarios:** agregación sobre `solicitudes` aprobadas unida a `preparaciones`, `materias`, `casos_clinicos` y `salas`, agrupando por docente, materia, semestre, caso clínico, sala y tipo de sesión. Las horas se calculan desde `hora_inicio` y `hora_fin`.

**RF55 — resultados de evaluación:** agregación sobre `evaluacion_estudiantes` unida a `evaluaciones`, `solicitudes` y `materias`. Exportable a PDF y Excel.

**RF56 — evaluaciones no registradas:** `solicitudes` con `tipo = evaluacion`, `estado = aprobada`, `fecha` anterior a hoy y sin registro asociado en `evaluaciones`.

### Exportación

Los reportes se consultan en pantalla y se descargan en dos formatos: PDF mediante plantillas Blade renderizadas con dompdf, y Excel mediante clases `Export` que reutilizan la misma consulta del `ReporteService`. La consulta se escribe una sola vez y alimenta las tres salidas (pantalla, PDF y Excel) para evitar divergencias entre lo que se ve y lo que se descarga.

Los reportes con muchos registros se exportan mediante consultas por lotes (`chunk`), no cargando todo en memoria, para no comprometer el rendimiento del contenedor.

### Índices recomendados

- `solicitudes`: (`fecha`, `estado`), (`docente_id`), (`materia_id`)
- `evaluacion_estudiantes`: (`estudiante_id`), (`evaluacion_id`)
- `preparaciones`: (`sala_id`, `estado`)
- `users`: (`email`), (`estado`)

---

## 8. Despliegue

`docker-compose.yml` con cuatro servicios:

| Servicio | Imagen base | Función |
|---|---|---|
| `app` | php:8.3-fpm | aplicación Laravel |
| `nginx` | nginx:alpine | servidor web y archivos estáticos |
| `db` | postgres:16 | base de datos, con volumen persistente |
| `metrics` | plausible o matomo | métricas del sitio público |

Volúmenes persistentes para la base de datos y para `storage/app`. Variables sensibles (credenciales de Google OAuth, base de datos, SMTP) en `.env`, fuera del control de versiones.

La sincronización de usuarios contra la vista institucional corre como tarea programada de Laravel (`schedule:run` vía cron dentro del contenedor `app`).

---

## 9. Pendientes que pueden afectar el modelo

1. **Autoaprobación:** si se decide impedir que un usuario con rol docente y coordinador apruebe su propia solicitud, se agrega la validación en `SolicitudService`. No requiere cambios de esquema.
2. **Estructura de la vista institucional:** los campos exactos que entregue la universidad pueden obligar a ajustar `users.documento`, `codigo_institucional` y `origen`.
3. **Aviso de sala al docente:** si se decide notificar la asignación de sala, se agrega un evento sobre `preparaciones`. No requiere cambios de esquema.
4. **Volumen real de usuarios:** la cifra de 300 concurrentes (RNF01) es una estimación por confirmar con la institución. Si el número real resulta considerablemente mayor, la vía de ajuste es Laravel Octane, que es un cambio de configuración del contenedor y no de arquitectura ni de esquema.
5. **Valores de `eventos.tipo`:** el RF05 exige registrar el tipo de evento pero no enumera los valores posibles. Pendiente de definir con la coordinación.
