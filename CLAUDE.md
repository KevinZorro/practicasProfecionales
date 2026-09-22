# CLAUDE.md

Instrucciones permanentes para trabajar en este repositorio. Léelas antes de escribir código.

---

## 1. Contexto del proyecto

Plataforma web de gestión del Laboratorio de Simulación Clínica de la Facultad de Ciencias de la Salud. Reemplaza procesos manuales de reserva de escenarios, evaluación de habilidades e inventario de simuladores.

**Usuarios:** ~700 estudiantes y ~150 docentes, más personal administrativo, coordinación y un administrador de la plataforma (RNF01, cifra confirmada con el cliente).

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

**Cuidado con "capacidad": en este dominio significa tres cosas distintas.** `capacidades` son las capacidades clínicas del simulador (sangrado, llanto, signos vitales); `salas.capacidad` es cuánta gente cabe en el espacio físico; `casos_clinicos.capacidad_maxima_estudiantes` es cuántos estudiantes admite el escenario (RF74). Escribe siempre el nombre largo del tercero: "capacidad" a secas ya está ocupado.

---

## 2. Stack

| Capa | Tecnología |
|---|---|
| Lenguaje | PHP 8.3 |
| Framework | Laravel 12 LTS (12.60 o superior) |
| Vistas | Blade |
| Interactividad | Livewire 3 + Alpine.js |
| Estilos | Tailwind CSS |
| Panel de administración | Filament 3 |
| Base de datos | PostgreSQL 16 |
| Autenticación | Laravel Socialite (Google OAuth) |
| Permisos | spatie/laravel-permission ^7.1 |
| Calendario | FullCalendar.js |
| Exportación | barryvdh/laravel-dompdf, maatwebsite/excel |
| Tests | Pest |
| Contenedores | Docker + Docker Compose |

**No agregues dependencias sin justificarlo primero.** Cada paquete nuevo es algo que el mantenedor futuro tendrá que aprender. Si algo se resuelve con Laravel puro, hazlo con Laravel puro.

**Restricciones de plataforma:**

- `config.platform.php` está fijado en `8.3.0`. Producción corre PHP 8.3, así que ninguna dependencia puede exigir 8.4. Después de cualquier cambio en `composer.json`, verifica que el lock siga siendo instalable en 8.3.
- Los roles y permisos los gestiona `spatie/laravel-permission` con sus propias tablas. **No crees tablas de roles propias** ni compruebes roles con condicionales sueltos.
- La autenticación es únicamente por Google (RF18). No existe `users.password` ni el paquete Breeze: se retiraron por innecesarios. No añadas rutas de login, registro ni recuperación de contraseña; hay tests que fallan si reaparecen.

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
| `ReposicionService` | Lista de insumos por pedir, necesidades anotadas a mano, cierre del documento |
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

7. **El consentimiento se renueva cada semestre.** Índice único sobre (`estudiante_id`, `periodo_academico`). Lo verifica el **administrativo**, que es quien recibe las entregas a diario; coordinación y ADMIN conservan el permiso por herencia y supervisan. Quien verifica también descarga el documento firmado: no se aprueba lo que no se lee.

   **La entrega en físico no es un estado del documento, es un hecho que convive con él (RF53).** El estudiante que no puede subir el escaneo entrega el formato firmado en la puerta; `recibido_fisico_at` y `recibido_fisico_por` registran cuándo y quién lo recibió, y esas columnas **no se borran nunca**: ni al subir el escaneo, ni al verificarlo, ni al devolvérselo. `estado` sigue describiendo solo el documento escaneado (`pendiente` → `cargado` → `verificado`). No metas la entrega física en ese enum: se pierde al avanzar de estado y no podrías responder quién entregó papel y todavía no escanea.

   Dos preguntas parecidas que **no** son la misma: `puedeParticiparEnPracticas()` (verificado **o** entrega física) decide si entra a la práctica; `tieneConsentimientoVigente()` (solo verificado) dice si el trámite está cerrado.

8. **El acceso depende de la vigencia institucional.** `users.estado` lo actualiza la sincronización programada, nunca a mano. Los egresados conservan el correo institucional, así que el correo por sí solo no autoriza el ingreso.

9. **El flujo de una solicitud es:** docente solicita → administrativo revisa → coordinador (o el ADMIN, si coordinación no está) aprueba, o coordinador rechaza → administrativo asigna sala y prepara. **Sin revisión previa no aprueba nadie:** aprobar exige estado `revisada`. No inventes atajos entre estados.

10. **Ningún escenario admite más estudiantes de los que el ADMIN le registró.** `casos_clinicos.capacidad_maxima_estudiantes` (RF74). Es un dato, no una constante: el ADMIN lo edita, y la comprobación vive en `SolicitudService`. Un escenario con la capacidad en `null` está **sin definir** y no limita: bloquear una clase real por un campo que nadie llenó es peor que no tener tope.

11. **El estado funcional es de las unidades, no del ítem (RF66).** De ocho sondas, dos pueden estar en revisión y seis seguir disponibles. Lo llevan tres contadores en `items_inventario` —`cantidad_operativa`, `cantidad_en_revision`, `cantidad_defectuosa`—, y la invariante es:

    **`cantidad_total = cantidad_operativa + cantidad_en_revision + cantidad_defectuosa`**

    La garantiza un `CHECK` de PostgreSQL, que no se puede saltar ni desde tinker ni desde un seeder. No hay columna `estado`: un ítem con seis operativas y dos defectuosas no tiene "un estado".

    **Estado funcional y disponibilidad siguen siendo ejes distintos.** La disponibilidad responde "¿cuántas quedan libres para esta sesión?" y **no se almacena**: `InventarioService` la calcula por franja como `cantidad_operativa` menos lo comprometido en solicitudes aprobadas.

    Toda unidad que se mueve, entra o sale pasa por `InventarioService` (`cambiarEstado`, `retirarUnidades`, `reponerUnidades`) y **exige cantidad, motivo y responsable**, que quedan en `cambios_estado_item`. El historial es de solo añadir y reconstruye los contadores por sí solo; hay un test que lo comprueba. Las cantidades están fuera de `$fillable`, igual que `nivel_fidelidad`.

    Dar de baja unidades defectuosas es definitivo, las descuenta del total y lo reserva la Policy a coordinación y ADMIN. Retirar unidades operativas —gasto, pérdida, corrección de conteo— lo hace quien gestiona el inventario; el historial las distingue por el estado de origen.

12. **La lista de reposición es un documento que se cierra, no una vista (RF67).** Es el soporte de la carta de solicitud de compra que el laboratorio presenta una vez al semestre: en cuanto se entrega, sus cifras son las que se entregaron. En borrador no guarda nada y se calcula al vuelo; al cerrarla, las líneas —**incluida la descripción del ítem**— se congelan en `lineas_reposicion` y de ahí leen la pantalla y las exportaciones. Corregir un movimiento del historial después **no** puede cambiar una lista cerrada. Mismo criterio que los ítems del checklist de la regla 3.

    Solo se descargan listas cerradas. Cerrar es irreversible y lo reserva la Policy a coordinación y ADMIN.

    Lo que hizo falta y el historial no puede saber —lo que se pidió y no había— va en `necesidades_reposicion`, con `item_inventario_id` nulo cuando todavía no está en el catálogo. **No se crea un ítem con cero unidades para representarlo:** aparecería en la disponibilidad y en el formulario del docente como si el laboratorio lo tuviera.

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
- En Tailwind, `border-gray-300` fija el **color** del borde, no su grosor. Sin `border` al lado, el borde no se ve. Es un fallo que se lee perfectamente en el código y solo aparece al abrir el navegador: lo correcto es `border border-gray-300`.
- Móvil primero: los administrativos usan el sistema desde el celular mientras preparan escenarios.
- La interfaz debe funcionar en conexiones lentas y equipos de gama baja: sin dependencias pesadas de JavaScript.

---

## 6. Antipatrones prohibidos

- **Lógica de negocio en controladores, modelos o vistas.** Va en Services.
- **Consultas N+1.** Usa `with()` siempre que recorras una relación.
- **Comprobar roles con condicionales sueltos.** Usa Policies y Gates.
- **Nombrar una Policy sin que coincida con su modelo.** Laravel las resuelve por convención: el modelo `ItemInventario` exige `ItemInventarioPolicy`, no `InventarioPolicy`. Un nombre que no coincide **no da error**: `Gate::getPolicyFor()` devuelve `null` y el Gate deniega en silencio, así que parece un problema de permisos. Ya ha pasado dos veces en este proyecto. Hay un test que recorre `app/Policies` y falla si alguna no se descubre.
- **Números y textos mágicos.** Enums o constantes con nombre.
- **`Model::all()`** sobre tablas que crecen. Pagina o filtra.
- **Lógica duplicada entre pantalla, PDF y Excel.** Una sola consulta en el Service alimenta las tres salidas.
- **Comentarios que repiten el código.** Comenta solo el porqué de una decisión no obvia.
- **Código comentado o muerto.** Bórralo, para eso está Git.
- **Migraciones editadas después de aplicadas.**
- **`env()` fuera de los archivos de configuración.** Usa `config()`.
- **Archivos de consentimiento en almacenamiento público.** Contienen datos personales: se sirven por ruta protegida con Policy.
- **Correr dos suites de tests a la vez contra la misma base.** PostgreSQL detecta el interbloqueo entre las dos y aborta transacciones, así que saltan `QueryException` en tests que no tienen nada roto. Parece un fallo del código y no lo es. Ya ha pasado dos veces en este proyecto: una suite cada vez, y espera a que termine antes de lanzar la siguiente.

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
4. ~~Volumen real de usuarios.~~ Resuelto: el cliente confirmó ~700 estudiantes y ~150 docentes, y el RNF01 quedó actualizado.
5. Valores posibles de `eventos.tipo`: el RF05 pide registrar el tipo pero no los enumera.
