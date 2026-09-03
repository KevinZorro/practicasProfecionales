# CLAUDE.md

Instrucciones permanentes para trabajar en este repositorio. Léelas antes de escribir código.

---

## 1. Contexto del proyecto

Plataforma web de gestión del Laboratorio de Simulación Clínica de la Facultad de Ciencias de la Salud. Reemplaza procesos manuales de reserva de escenarios, evaluación de habilidades e inventario de simuladores.

**Usuarios:** ~2.000 en total, con picos estimados de 300 concurrentes. Docentes, estudiantes, personal administrativo, coordinación y un administrador de la plataforma.

**Entorno de producción:** servidor institucional propio con Debian 13 Trixie, desplegado en contenedores Docker. Sin servicios en la nube de pago.

**Contexto del desarrollo:** proyecto de práctica empresarial, un solo desarrollador, plazo cerrado. El sistema lo mantendrá después el administrador del servidor, que conoce PHP pero no participó del desarrollo. **Prioriza claridad sobre ingenio.** Código que un tercero pueda leer y modificar sin explicación previa.

### Los cinco roles

| Rol | Qué hace |
|---|---|
| `admin` | Administra la plataforma: contenido público, usuarios, materias, casos clínicos, tipos de evaluación, nivel de fidelidad del inventario |
| `coordinador` | Aprueba o rechaza solicitudes de escenario, verifica consentimientos, genera reportes. **Hereda todos los permisos del administrativo** |
| `administrativo` | Revisa solicitudes, asigna sala, prepara escenarios, gestiona inventario |
| `docente` | Solicita escenarios y registra evaluaciones de habilidades |
| `estudiante` | Consulta sus resultados y entrega el consentimiento informado |

Un usuario puede tener varios roles a la vez (una coordinadora puede además ser docente). El rol activo se elige con un selector y vive en sesión.

### Vocabulario del dominio

Usa estos términos exactos en código, base de datos e interfaz. No los traduzcas ni los inventes.

- **Escenario clínico / caso clínico** — la situación que se practica (atención de parto, herida por arma de fuego, incubadora). No es la sala.
- **Sala** — el espacio físico donde se monta el escenario.
- **Simulador** — maniquí de baja, media o alta fidelidad.
- **Equipo clínico / equipo básico** — insumos y equipos que acompañan la práctica.
- **Solicitud** — pedido de un docente para usar un escenario. Es de tipo `practica` o `evaluacion`.
- **Preparación** — el montaje físico del escenario, previo a la clase.
- **Checklist** — lista de ítems que el docente marca al evaluar.
- **Intento** — número de vez que un estudiante presenta la misma evaluación.

---

## 2. Stack

| Capa | Tecnología |
|---|---|
| Lenguaje | PHP 8.3 |
| Framework | Laravel 11 |
| Vistas | Blade |
| Interactividad | Livewire 3 + Alpine.js |
| Estilos | Tailwind CSS |
| Panel de administración | Filament 3 |
| Base de datos | PostgreSQL 16 |
| Autenticación | Laravel Socialite (Google OAuth) |
| Permisos | spatie/laravel-permission |
| Calendario | FullCalendar.js |
| Exportación | barryvdh/laravel-dompdf, maatwebsite/excel |
| Tests | Pest |
| Contenedores | Docker + Docker Compose |

**No agregues dependencias sin justificarlo primero.** Cada paquete nuevo es algo que el mantenedor futuro tendrá que aprender. Si algo se resuelve con Laravel puro, hazlo con Laravel puro.

---

## 3. Arquitectura

```
Request → Route → Middleware → Form Request → Controller/Livewire
                                                    ↓
                                                 Policy
                                                    ↓
                                                 Service
                                                    ↓
                                              Model/Eloquent
                                                    ↓
                                            Event → Listener → Mail
```

### Qué va en cada capa

**Form Request** — validación de formato y obligatoriedad. Nada más.

**Controller / componente Livewire** — recibe, delega a un Service, responde. Un método de controlador que pasa de ~15 líneas casi siempre tiene lógica que pertenece a un Service.

**Policy** — autorización. Aquí vive la herencia coordinador → administrativo. Nunca compruebes roles con `if ($user->rol === 'coordinador')` disperso por el código.

**Service** — toda la lógica de negocio. Es el único lugar donde se decide algo.

**Model** — relaciones, casts, scopes, accessors. Sin reglas de negocio, sin envío de correos, sin llamadas a otros servicios.

**Event / Listener** — efectos secundarios que no deben bloquear la respuesta (correos, notificaciones).

### Services del proyecto

| Service | Responsabilidad |
|---|---|
| `SolicitudService` | Crear solicitud, precargar inventario del caso clínico, transiciones de estado, disparar notificaciones |
| `PreparacionService` | Crear preparación al aprobar, asignar sala, marcar ítems alistados |
| `EvaluacionService` | Validar solicitud aprobada de tipo evaluación, copiar checklist, calcular número de intento |
| `InventarioService` | Altas, bajas, disponibilidad por fecha y franja horaria |
| `ConsentimientoService` | Periodo académico vigente, estado del consentimiento, bloqueo de prácticas |
| `ReporteService` | Agregaciones y generación de PDF y Excel |
| `UsuarioSyncService` | Sincronización contra la vista institucional |

---

## 4. Reglas de negocio que no se pueden romper

Estas salieron de reuniones con el cliente. Si el código las contradice, el código está mal.

1. **Ninguna evaluación existe sin escenario apartado.** `evaluaciones.solicitud_id` es obligatorio, único, y la solicitud debe ser de tipo `evaluacion` y estar `aprobada`. Valídalo en `EvaluacionService`, no solo con la restricción de base de datos.

2. **El resultado de una evaluación lo decide el docente.** `resultado` (aprobado / no aprobado) es independiente de los ítems marcados del checklist. **Nunca lo calcules automáticamente** a partir de los ítems cumplidos, por más lógico que parezca.

3. **Los ítems del checklist se copian, no se referencian.** Al crear una evaluación, los ítems de la plantilla se duplican en `evaluacion_items`. Si el ADMIN edita la plantilla después, las evaluaciones históricas conservan los ítems originales. Esta duplicación es intencional.

4. **La sala no se elige al solicitar.** El docente no la elige y el coordinador tampoco. La asigna el administrativo durante la preparación, minutos antes de la clase. `sala_id` vive en `preparaciones`, no en `solicitudes`.

5. **Los docentes no ven disponibilidad de inventario.** Restringido a administrativo, coordinador y admin.

6. **El nivel de fidelidad del simulador solo lo edita el ADMIN.** Es el único campo del inventario con esa restricción; el resto lo editan administrativos y coordinadores. Restricción a nivel de campo, no de recurso.

7. **El consentimiento se renueva cada semestre.** Índice único sobre (`estudiante_id`, `periodo_academico`). Lo verifican coordinadores y ADMIN, **no** los administrativos.

8. **El acceso depende de la vigencia institucional.** `users.estado` lo actualiza la sincronización programada, nunca a mano. Los egresados conservan el correo institucional, así que el correo por sí solo no autoriza el ingreso.

9. **El flujo de una solicitud es:** docente solicita → administrativo revisa → coordinador aprueba o rechaza → administrativo asigna sala y prepara. No inventes atajos entre estados.

---

## 5. Convenciones de código

### Nomenclatura

- **Dominio en español:** tablas, modelos, columnas, rutas, variables de negocio (`Solicitud`, `casos_clinicos`, `nivel_fidelidad`, `intento`).
- **Framework en inglés:** métodos de Laravel, hooks de Livewire, nombres de tests (`handle`, `mount`, `render`).
- Tablas en plural y snake_case; modelos en singular y StudlyCase.
- Pivotes en singular ordenado alfabéticamente: `caso_clinico_materia`.
- Sin abreviaturas: `cantidad_estudiantes`, no `cant_est`.

### PHP

- `declare(strict_types=1)` en todos los archivos.
- Tipado explícito en parámetros, retornos y propiedades. Nada de `mixed` por comodidad.
- **Enums de PHP** para todos los estados y tipos (`EstadoSolicitud`, `TipoSesion`, `NivelFidelidad`, `ResultadoEvaluacion`). Nunca strings sueltos ni constantes de clase.
- Inyección de dependencias por constructor, no facades dentro de los Services.
- Retorno temprano en vez de `if` anidados.

### Base de datos

- Toda operación que toque varias tablas va en una transacción.
- Llaves foráneas con restricción explícita: `cascade` donde el hijo no tiene sentido sin el padre, `restrict` donde borrar rompería el histórico.
- Índices en columnas de filtro frecuente: `solicitudes(fecha, estado)`, `evaluacion_estudiantes(estudiante_id)`, `users(email, estado)`.
- **Nunca** modifiques una migración ya aplicada en un commit anterior: crea una nueva.

### Vistas

- Componentes Blade reutilizables para elementos repetidos (tarjetas de solicitud, etiquetas de estado, tablas).
- Solo clases utilitarias de Tailwind; sin CSS suelto salvo que no haya alternativa.
- Móvil primero: los administrativos usan el sistema desde el celular mientras preparan escenarios.
- La interfaz debe funcionar en conexiones lentas y equipos de gama baja: sin dependencias pesadas de JavaScript.

---

## 6. Antipatrones prohibidos

- **Lógica de negocio en controladores, modelos o vistas.** Va en Services.
- **Consultas N+1.** Usa `with()` siempre que recorras una relación.
- **Comprobar roles con condicionales sueltos.** Usa Policies y Gates.
- **Números y textos mágicos.** Enums o constantes con nombre.
- **`Model::all()`** sobre tablas que crecen. Pagina o filtra.
- **Lógica duplicada entre pantalla, PDF y Excel.** Una sola consulta en el Service alimenta las tres salidas.
- **Comentarios que repiten el código.** Comenta solo el porqué de una decisión no obvia.
- **Código comentado o muerto.** Bórralo, para eso está Git.
- **Migraciones editadas después de aplicadas.**
- **`env()` fuera de los archivos de configuración.** Usa `config()`.
- **Archivos de consentimiento en almacenamiento público.** Contienen datos personales: se sirven por ruta protegida con Policy.

---

## 7. Tests

Cada tarea termina con tests que la prueben. **Una funcionalidad sin test no está terminada.**

- **Feature tests** para los flujos completos: solicitar escenario, aprobar, preparar, evaluar.
- **Unit tests** para la lógica de los Services: cálculo de intentos, copia del checklist, disponibilidad de inventario.
- Prueba también los caminos negativos: crear una evaluación sin solicitud aprobada debe fallar; un docente no debe poder aprobar su propia solicitud si esa regla se activa; un administrativo no debe poder verificar consentimientos.
- Usa factories, nunca datos escritos a mano dentro del test.
- Los tests describen comportamiento del dominio, no implementación: `un docente no puede evaluar sin escenario aprobado`.

Antes de dar una tarea por terminada: `php artisan test` en verde.

---

## 8. Git

- Un commit por unidad de trabajo con sentido propio.
- Mensajes en español con prefijo convencional: `feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`.
- Ejemplo: `feat: flujo de aprobación de solicitudes de escenario`.
- Nunca commitear `.env`, `/vendor`, `/node_modules`, ni archivos subidos por usuarios.

---

## 9. Comandos

Todo corre dentro de Docker. No asumas PHP ni Composer instalados en el host.

```bash
docker compose up -d                                    # levantar entorno
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan test
docker compose exec app php artisan make:model Nombre -mf
docker compose exec node npm run dev
docker compose logs -f app
```

---

## 10. Cómo trabajar

- **Una tarea a la vez.** No adelantes módulos que no se pidieron.
- **Si un requerimiento es ambiguo, pregunta antes de programar.** Adivinar aquí cuesta reprocesos con el cliente.
- **Si algo contradice este archivo o el documento de arquitectura, dilo** en vez de resolverlo por tu cuenta.
- Al terminar una tarea: resume qué se hizo, qué archivos se tocaron y cómo verificarlo.
- No refactorices código ajeno a la tarea actual sin avisar.

---

## 11. Pendientes abiertos con el cliente

No los resuelvas por tu cuenta; si el código los toca, déjalo señalado:

1. Si un usuario con rol docente y coordinador debe poder aprobar su propia solicitud.
2. Estructura exacta de la vista de la base de datos institucional para la sincronización de usuarios.
3. Cómo se entera hoy el docente de la sala asignada al llegar a clase.
4. Volumen real de usuarios concurrentes; la cifra actual es una estimación.
