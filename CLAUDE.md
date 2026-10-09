# CLAUDE.md

Instrucciones permanentes para trabajar en este repositorio. Léelas antes de escribir código.

---

## 1. Contexto del proyecto

Plataforma web de gestión del Laboratorio de Simulación Clínica de la Facultad de Ciencias de la Salud. Reemplaza procesos manuales de reserva de escenarios, evaluación de habilidades e inventario de simuladores.

**Los enunciados de los requerimientos están en `docs/requerimientos.md`**, y el estado de cada uno contra el código, con las preguntas abiertas, en `docs/trazabilidad.md`. Si este archivo y el enunciado no coinciden, manda el enunciado: dilo y anótalo en la matriz.

**Usuarios:** ~700 estudiantes y ~150 docentes, más personal administrativo, coordinación y un administrador de la plataforma (RNF01, cifra confirmada con el cliente).

**Entorno de producción:** servidor institucional propio con Debian 13 Trixie, desplegado en contenedores Docker. Sin servicios en la nube de pago.

**Contexto del desarrollo:** proyecto de práctica empresarial, un solo desarrollador, plazo cerrado. El sistema lo mantendrá después el administrador del servidor, que conoce PHP pero no participó del desarrollo. **Prioriza claridad sobre ingenio.** Código que un tercero pueda leer y modificar sin explicación previa.

### Los cinco roles

| Rol | Qué hace |
|---|---|
| `admin` | Administra la plataforma: contenido público, usuarios, materias, casos clínicos, tipos de evaluación, nivel de fidelidad del inventario |
| `coordinador` | Aprueba o rechaza solicitudes de escenario, verifica formatos de confidencialidad, genera reportes. **Hereda todos los permisos del administrativo** |
| `administrativo` | Revisa solicitudes, asigna sala, prepara escenarios, gestiona inventario |
| `docente` | Solicita escenarios, registra evaluaciones de habilidades y entrega su formato de confidencialidad |
| `estudiante` | Consulta sus resultados y entrega el formato de confidencialidad |

Un usuario puede tener varios roles a la vez (una coordinadora puede además ser docente). El rol activo se elige con un selector y vive en sesión.

**Un rol puede tener fecha de fin (RF63, RF64):** el pasante hace el trabajo del administrativo pero se va antes de que terminen las clases, y la coordinadora delega su facultad de aprobar mientras está en consejo. Ver la regla 13.

### Vocabulario del dominio

Usa estos términos exactos en código, base de datos e interfaz. No los traduzcas ni los inventes.

- **Escenario clínico / caso clínico** — la situación que se practica (atención de parto, herida por arma de fuego, incubadora). No es la sala.
- **Sala** — el espacio físico donde se monta el escenario.
- **Simulador** — maniquí de baja, media o alta fidelidad.
- **Equipo clínico / equipo básico** — insumos y equipos que acompañan la práctica.
- **Solicitud** — pedido de un docente para usar un escenario. Es de tipo `practica` o `evaluacion`.
- **Grupo** — la parte de la clase que pasa a los simuladores en una sesión (A, B, C…). No hay grupos fijos del semestre: el docente dice al solicitar qué estudiantes van (`estudiante_solicitud`), y `cantidad_estudiantes` sale de esa lista.
- **Preparación** — el montaje físico del escenario, previo a la clase.
- **Checklist** — lista de ítems que el docente marca al evaluar.
- **Intento** — número de vez que un estudiante presenta la misma evaluación.
- **Formato de confidencialidad** — el documento que se firma para entrar a las prácticas; incluye la autorización de captación de imágenes. Es como lo llama el laboratorio, y así está rotulada su carpeta en el Drive. **No lo llames "consentimiento informado"**: fue nuestro nombre, no el suyo, y se retiró del código. Lo firma todo el que entra a la práctica —estudiantes y docentes—, por eso la columna es `firmante_id` y no `estudiante_id`.

**Cuidado con "capacidad": en este dominio significa tres cosas distintas.** `capacidades` son las capacidades clínicas del simulador (sangrado, llanto, signos vitales); `salas.capacidad` es cuánta gente cabe en el espacio físico; `casos_clinicos.capacidad_maxima_estudiantes` es cuántos estudiantes admite el escenario (RF74). Escribe siempre el nombre largo del tercero: "capacidad" a secas ya está ocupado.

---

## 2. Stack

### Instalado

Lo que está en `composer.json` y `package.json` y se usa hoy.

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
| Exportación | barryvdh/laravel-dompdf, maatwebsite/excel |
| Tests | Pest |
| Análisis estático | Larastan, nivel 6, con línea base (ver §7) |
| Pantallas del ADMIN | Filament 3.3, en `/admin` |
| Autenticación | Laravel Socialite 5 (Google OAuth, RF18) |
| Contenedores | Docker + Docker Compose |

**Dos paneles, con una frontera fija.** Los flujos operativos —solicitudes, preparación, inventario, formato de confidencialidad, evaluaciones— son Livewire y Blade, en `/panel`. Las pantallas de alta, baja y edición del ADMIN sin reglas de negocio —estructura académica (RF22–RF26) y contenido público (RF10–RF17)— son recursos de Filament, en `/admin`. Nada operativo va a Filament.

**Filament, cinco cosas que no son las de su plantilla:**

- **Todo recurso hereda de `App\Filament\RecursoDelAdmin`, y su modelo tiene Policy antes de tener pantalla.** Por defecto Filament *permite* lo que la Policy no define, o todo si no hay Policy; la clase base lo invierte para que el Gate deniegue. `RecursosDelAdminTest` falla si un recurso no hereda de ella o si su modelo no tiene Policy descubierta.
- **Solo entra el ADMIN, con el rol activo aplicado.** Lo decide el Gate `accederAlPanelDelAdmin` desde `User::canAccessPanel()`. `VerificarUsuarioActivo` y `EstablecerRolActivo` van en la pila de Filament, y `bootstrap/app.php` los pone en la lista de prioridad delante de la autenticación: si no, Laravel reordena el `Authenticate` de Filament delante de ellos y `canAccessPanel()` decidiría con todos los roles.
- **Sin `->login()` ni ninguna otra entrada propia** (RF18). La salida del menú apunta a nuestra ruta `salir`. Filament registra de todas formas `admin/logout`, y es la única excepción de `AutenticacionTest`, por nombre exacto de ruta.
- **Sus assets no se versionan.** Los publica el script `filament:upgrade` de `composer.json` en cada `composer install`; el job de Docker de la CI pide una hoja de estilos de Filament y exige 200. No quites ese script.
- **Tailwind no lee las vistas compiladas.** Filament trae su propio CSS; si `content` incluyera `storage/framework/views`, cada build después de `view:cache` arrastraría sus clases al `app.css` del panel y lo duplicaría. Las vistas de paquete que usamos —la paginación de Livewire y la de Laravel— van en `content` por su ruta en `vendor/`.

**Imágenes del contenido público (RNF10).** Toda imagen que sube el ADMIN entra por `App\Filament\Formularios\CampoDeImagen` y se guarda con `ImagenPublicaService`: valida tipo (JPEG, PNG, WebP) y tamaño (5 MB), endereza la foto según su EXIF, la reduce a 1600 px de lado mayor y la guarda en WebP en el disco `public`. **No uses un `FileUpload` suelto para imágenes públicas:** se saltaría la reducción y una foto de celular de 4 MB llegaría tal cual a quien entra desde una conexión lenta. Las páginas de edición con imágenes usan el trait `BorraLasImagenesReemplazadas`, y el recurso declara sus columnas en `camposDeImagen()`: así el archivo viejo se borra al reemplazarlo, después de confirmar la transacción.

**El video del hero es la excepción:** no se recodifica (haría falta ffmpeg). Se sube ya comprimido, MP4 o WebM, con un tope de 15 MB (`ConfiguracionLandingService::TAMANO_MAXIMO_VIDEO_KB`). Ese tope tiene que caber en `php.ini`, en `client_max_body_size` de nginx y en `config/livewire.php`, que queda 1 MB por encima para que el error lo dé el formulario; `ConfiguracionLandingTest` lo comprueba.

Los archivos los sirve **nginx** por `/storage` (enlace de `php artisan storage:link`), con caché de un mes y `nosniff`: sus nombres se generan al subirlos y nunca se reescriben. `storage/app/public` vive en el volumen `archivos_publicos` del compose, montado en `app`, `queue` y `nginx`; el Dockerfile crea esa carpeta con dueño `www-data` porque Docker inicializa un volumen vacío con el dueño de la imagen, y sin eso la aplicación no podría escribir. El job de Docker de la CI comprueba que GD guarda WebP y que nginx sirve un archivo escrito por la aplicación.

**Entrada con Google (RF18).** `AccesoConGoogleController` habla con Google por Socialite; quién entra lo decide `AccesoService::cuentaDeGoogle()`, que no conoce Socialite y recibe una `IdentidadDeGoogle`. Cinco decisiones que no son las del ejemplo de Socialite:

- **No crea cuentas.** Solo entra quien ya tiene cuenta en `users` (la crea la sincronización institucional o el laboratorio): un correo institucional no autoriza el ingreso (regla 8).
- **Vincula `google_id` la primera vez**, encontrando la cuenta por el correo sin distinguir mayúsculas; desde entonces la encuentra por `google_id`, que no cambia aunque cambie el correo. Una cuenta ya vinculada a otro `google_id` no se entrega a quien coincida en el correo.
- **Exige `email_verified`**, y el dominio institucional (`GOOGLE_DOMINIO_INSTITUCIONAL`) se **comprueba al volver** con el campo `hd`. El `hd` que se manda a Google es una sugerencia que cualquiera quita de la URL.
- **Sin `GOOGLE_CLIENT_ID`, la entrada no existe:** las tres rutas dan 404 y el invitado va a la portada, o en `local` al acceso de desarrollo, que sigue ahí hasta probar la entrada con las credenciales reales.
- **Sin límite de peticiones por IP:** en la universidad cientos de personas salen por la misma dirección y entran a la vez.

Los tests simulan a Google con un doble del proveedor de Socialite; la ida a Google y la comprobación del `state` usan el proveedor real.

**Producción (`docker-compose.produccion.yml`).** El código va dentro de las imágenes (etapas `produccion` y `web` de `docker/php/Dockerfile`); la sección "Producción" del README tiene la instalación, el despliegue y la restauración. Cuatro cosas que no se pueden perder:

- **Los documentos privados son un volumen**, `archivos_privados` (`storage/app/private`), montado en `app`, `queue`, `copias` y `restauracion`. Sin él, el formato de confidencialidad firmado se perdería en cada despliegue.
- **Nada de lo que suben los usuarios entra en una imagen:** `.dockerignore` excluye `storage/app/public` y `storage/app/private`. Una imagen construida en un equipo de desarrollo se llevaría dentro los documentos de prueba.
- **Las cabeceras de seguridad las pone `CabecerasDeSeguridad`**, en Laravel y no en nginx, para que se prueben. La Content-Security-Policy lleva `'unsafe-inline'` y `'unsafe-eval'` porque Alpine y Filament los necesitan; lo que cierra son los orígenes externos. Cualquier recurso de otro sitio que se añada (una fuente, un script, una imagen) hay que declararlo en ella, o el navegador lo bloquea. Por eso el avatar de Filament se dibuja en local (`App\Filament\AvatarConIniciales`) y no se pide a ui-avatars.com.
- **Una copia que no se restaura no es una copia.** La CI hace una en cada PR, daña los datos y la restaura. Si cambia dónde guarda algo la aplicación, cambia también `docker/produccion/copias/`.

**Correo: la cuenta institucional del laboratorio, por SMTP de Google, sin paquete.** Los correos salen de la cuenta de Google Workspace que el laboratorio ya tiene, por `smtp.gmail.com` con una contraseña de aplicación y el transporte SMTP que ya trae Laravel; todo se configura con las variables `MAIL_*`. No instales paquetes de proveedores de correo: cambiar de proveedor tiene que ser cambiar el `.env`. La cuenta tiene un tope diario (unos 2.000), así que un aviso que pueda repetirse por sesión se agrupa en un correo por persona. Si alguien cambia la contraseña de esa cuenta, la de aplicación se anula y los correos dejan de salir hasta generar otra.

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

**Form Request** — validación de formato y obligatoriedad. Nada más. Casi todas las pantallas que reciben datos son componentes Livewire, y ese papel lo cumple su validación (`#[Validate]` y `validate()`). Hay un solo Form Request, `FiltroDeReporteRequest`, porque las descargas de reportes son enlaces normales con los filtros en la URL; la pantalla de reportes valida con sus mismas reglas. Las reglas son las mismas: formato y obligatoriedad, nunca reglas de negocio.

**Controller / componente Livewire** — recibe, delega a un Service, responde. Un método de controlador que pasa de ~15 líneas casi siempre tiene lógica que pertenece a un Service.

**Policy** — autorización. Aquí vive la herencia coordinador → administrativo. Nunca compruebes roles con `if ($user->rol === 'coordinador')` disperso por el código.

**Service** — toda la lógica de negocio. Es el único lugar donde se decide algo.

**Model** — relaciones, casts, scopes, accessors. Sin reglas de negocio, sin envío de correos, sin llamadas a otros servicios.

**Event / Listener** — efectos secundarios que no deben bloquear la respuesta (correos, notificaciones).

### Services del proyecto

| Service | Responsabilidad |
|---|---|
| `SolicitudService` | Crear solicitud, precargar inventario del caso clínico, transiciones de estado, disparar notificaciones |
| `PreparacionService` | Crear preparación al aprobar, asignar sala entre las libres (las vinculadas al escenario primero) y avisar al docente por correo, marcar ítems alistados |
| `SalaService` | Rastro de la ubicación de las salas (bloque, piso y número, RF65): una fila en `ubicaciones_sala` cada vez que cambia. Lo llaman las páginas de alta y edición de Filament |
| `EvaluacionService` | Validar solicitud aprobada de tipo evaluación, copiar checklist, calcular número de intento |
| `InventarioService` | Altas, bajas, disponibilidad por fecha y franja horaria |
| `PeriodoAcademicoService` | Abrir, cerrar y reabrir el periodo académico (RF75). Es la única fuente del periodo vigente: el abierto, o entre semestres el último cerrado. Nunca se deriva del calendario |
| `ConfidencialidadService` | Estado del formato de confidencialidad en el periodo vigente; solo recibe entregas con un periodo abierto |
| `AccesoService` | Quién puede entrar según la vigencia institucional (regla 8) y a qué cuenta corresponde quien vuelve de Google (RF18). Lo consultan la entrada y el middleware `VerificarUsuarioActivo` |
| `AsignacionDeRolService` | Asignar y revocar roles, con o sin vigencia, y dejar el rastro. **Única puerta de escritura de roles:** nunca llames a `assignRole()` |
| `ReporteService` | Agregaciones y generación de PDF y Excel |
| `ConfiguracionLandingService` | Textos del hero, video y contacto de la landing (RF11): claves fijas en `ClaveConfiguracionLanding`, guardadas todas o ninguna; borra el video reemplazado al confirmar |
| `ImagenPublicaService` | Imágenes del contenido público: validar, enderezar, reducir, guardar en WebP y borrar la reemplazada al confirmar la transacción |
| `ReposicionService` | Lista de insumos por pedir, necesidades anotadas a mano, cierre del documento |
| `UsuarioSyncService` | Sincronización contra la vista institucional. **Todavía no existe:** depende del pendiente 2, la estructura de la vista institucional. No lo invoques ni supongas que hay sincronización corriendo |

**Cuando se construya `UsuarioSyncService`:** los roles **permanentes** se derivarán del tipo de vinculación institucional, pero la sincronización **no debe tocar las asignaciones temporales ni revocarlas**. Un pasante no figura como administrativo en la vista institucional —por eso se le da el rol a mano y con fecha—, así que una sincronización que reponga roles "según la vinculación" le borraría el suyo en la primera pasada del semestre. Solo son suyas las filas del pivote con `hasta` nulo; las que tienen fecha las reparte el ADMIN y solo él las quita.

---

## 4. Reglas de negocio que no se pueden romper

Estas salieron de reuniones con el cliente. Si el código las contradice, el código está mal.

1. **Ninguna evaluación existe sin escenario apartado.** `evaluaciones.solicitud_id` es obligatorio, único, y la solicitud debe ser de tipo `evaluacion` y estar `aprobada`. Valídalo en `EvaluacionService`, no solo con la restricción de base de datos.

2. **El resultado de una evaluación lo decide el docente.** `resultado` (aprobado / no aprobado) es independiente de los ítems marcados del checklist. **Nunca lo calcules automáticamente** a partir de los ítems cumplidos, por más lógico que parezca.

3. **Los ítems del checklist se copian, no se referencian.** Al crear una evaluación, los ítems de la plantilla se duplican en `evaluacion_items`. Si el ADMIN edita la plantilla después, las evaluaciones históricas conservan los ítems originales. Esta duplicación es intencional.

4. **La sala no se elige al solicitar.** El docente no la elige y el coordinador tampoco. La asigna el administrativo durante la preparación, minutos antes de la clase. `sala_id` vive en `preparaciones`, no en `solicitudes`.

5. **Los docentes no ven disponibilidad de inventario.** Restringido a administrativo, coordinador y admin.

6. **El nivel de fidelidad del simulador solo lo edita el ADMIN.** Es el único campo del inventario con esa restricción; el resto lo editan administrativos y coordinadores. Restricción a nivel de campo, no de recurso.

7. **El formato de confidencialidad se renueva cada periodo académico**, el que abre el laboratorio (RF75). Índice único sobre (`firmante_id`, `periodo_academico`). Lo verifica el **administrativo**, que es quien recibe las entregas a diario; coordinación y ADMIN conservan el permiso por herencia y supervisan. Quien verifica también descarga el documento firmado: no se aprueba lo que no se lee.

   **Lo firma todo el que entra a la práctica, docente incluido (RF51-RF52).** El docente dirige la sesión pero está dentro de ella, y la autorización de captación de imágenes lo cubre igual. Quiénes son esos roles lo dice `Rol::queFirmanElFormato()`, y de ahí leen la Policy y el Service: no repitas la lista. Quien verifica —administrativo, coordinación, ADMIN— **no** firma, así que no puede entregarlo ni por sí mismo ni por otro.

   **La entrega en físico no es un estado del documento, es un hecho que convive con él (RF53).** El estudiante que no puede subir el escaneo entrega el formato firmado en la puerta; `recibido_fisico_at` y `recibido_fisico_por` registran cuándo y quién lo recibió, y esas columnas **no se borran nunca**: ni al subir el escaneo, ni al verificarlo, ni al devolvérselo. `estado` sigue describiendo solo el documento escaneado (`pendiente` → `cargado` → `verificado`). No metas la entrega física en ese enum: se pierde al avanzar de estado y no podrías responder quién entregó papel y todavía no escanea.

   Dos preguntas parecidas que **no** son la misma: `puedeParticiparEnPracticas()` (verificado **o** entrega física) decide si entra a la práctica; `tieneFormatoVigente()` (solo verificado) dice si el trámite está cerrado.

   **Quien no tiene el formato o está bloqueado no entra ni puede ser evaluado (RF45, RF70).** Para el estudiante eso es quedar fuera de la práctica. Para el docente, la decisión por defecto (D2 de `docs/trazabilidad.md`, pendiente de confirmar) es avisar sin cancelar la sesión: bloquear al docente cancela la clase. Hoy nadie llama a `puedeParticiparEnPracticas()`.

8. **El acceso depende de la vigencia institucional.** `users.estado` lo actualiza la sincronización programada, nunca a mano. Los egresados conservan el correo institucional, así que el correo por sí solo no autoriza el ingreso.

   **Se decide en un solo sitio, `AccesoService::puedeEntrar()`, y se comprueba en dos puertas.** La entrada —el controlador de Google, y en `local` el acceso de desarrollo— no deja pasar a un inactivo. Y el middleware `VerificarUsuarioActivo` corta en cada petición a quien se desactiva con la sesión abierta: cierra la sesión y después responde 403.

   **Ese middleware también es persistente en Livewire** (`AppServiceProvider`). Las acciones de un componente ya abierto van a `/livewire/update`, que no pasa por las rutas del panel, y Livewire solo vuelve a aplicar ahí los middleware de su lista. Sin eso, quien se desactivara con una pantalla abierta seguiría pulsando botones. Hay un test que lo comprueba con una petición HTTP de verdad: `Livewire::test()` se salta los middleware y no lo vería. **Cualquier middleware nuevo que decida quién puede actuar tiene que ir también en esa lista.**

9. **El flujo de una solicitud es:** docente solicita → administrativo acepta (revisa) o rechaza (RF30) → coordinador (o el ADMIN, si coordinación no está) aprueba o rechaza (RF31) → administrativo elige una sala libre, se avisa al docente por correo (RF36) y prepara. Son dos fases y en las dos se puede rechazar, cada una lo suyo (`SolicitudPolicy::rechazar()`): la pendiente la rechaza quien revisa; la revisada, coordinación o el ADMIN. El administrativo no rechaza lo que ya aceptó. Una solicitud rechazada en la primera fase queda con `revisada_por` nulo.

   **La única excepción son las sesiones apartadas antes del semestre (RF57):** las registra un administrativo desde el formato que le entrega coordinación, así que nacen aprobadas, sin pasar por las dos fases. Queda registrado quién las cargó. Fuera de ese registro no hay otro camino a `aprobada`. **Sin revisión previa no aprueba nadie:** aprobar exige estado `revisada`. No inventes atajos entre estados.

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

    **El historial es un flujo del periodo; las necesidades son un saldo pendiente.** Por eso no se filtran igual: una gasa gastada en julio no se vuelve a pedir en diciembre, así que los movimientos van por rango de fechas; pero si la pila no llegó, en el semestre siguiente sigue haciendo falta, así que las necesidades entran en todos los borradores hasta que alguien las marque como atendidas. Atenderlas **no repone unidades**: que entren unidades al inventario es otro acto, con su propia cantidad, motivo y responsable (regla 11).

    **Las listas no se pisan ni dejan huecos.** Cada una arranca el día siguiente al cierre de la anterior, y solo la primera elige su origen. `hasta` incluye el día completo y no puede ser futuro. El corte es por día sobre timestamps, y se calcula en la zona de la aplicación —`APP_TIMEZONE=America/Bogota`—: los `timestamp` se guardan en hora de pared, así que cortar en UTC movería la frontera cinco horas y un movimiento de las ocho de la noche caería en la lista equivocada. Hay un test que falla si esa variable se pierde.

13. **Un rol puede tener fecha de fin, y vence solo (RF63, RF64).** El pasante hace el trabajo del administrativo y se retira dos semanas antes de que terminen las clases; la coordinadora delega su facultad de aprobar mientras está en consejo. Son la misma pieza: un rol con `hasta`.

    **El vencimiento se hace cumplir en SQL, dentro de `User::roles()`, y esa decisión no es negociable.** La relación filtra por `desde`/`hasta` del pivote `model_has_roles`, así que el corte lo heredan todos los caminos de lectura —`hasRole()`, `can()`, las Policies, el scope `role()` de spatie, `whereHas('roles')` y el `loadMissing('roles')` que el paquete hace por dentro—, en peticiones HTTP, comandos, colas y tinker por igual. **No escribas un job ni un comando que caduque roles:** no hace falta, y si aparece, el vencimiento pasaría a depender de que corra. Hay un test que adelanta el reloj un día, no corre nada, y exige que la Policy deniegue.

    `hasta` nulo es permanente; `desde` nulo es "siempre ha valido" (los roles que reparten los seeders con `assignRole()`). `hasta` **incluye el día completo**, y el corte se calcula por fecha en la zona de la aplicación, igual que la frontera de las listas de reposición de la regla 12.

    **Revocar borra la fila del pivote; no acorta `hasta`.** Con vigencia por día, poner `hasta` = hoy dejaría el rol vivo hasta medianoche, que es justo lo que la revocación quiere evitar. El rastro no se pierde: vive en `asignaciones_de_rol`, que es de solo añadir y guarda quién asignó, a quién, qué rol, desde cuándo, hasta cuándo, con qué motivo y quién revocó. Hace falta aparte porque la llave primaria del pivote es (`role_id`, `model_id`, `model_type`) y solo cabe una fila por usuario y rol: si un pasante viene, se va y vuelve, sin la tabla se perdería la primera asignación.

    **Toda escritura de roles pasa por `AsignacionDeRolService`. Nunca llames a `assignRole()`**, ni en código nuevo ni en un comando: calcula lo que el usuario ya tiene leyendo la relación **filtrada**, así que con una fila vencida en el pivote intenta insertar una que ya existe y revienta contra la llave primaria. Los `assignRole()` que quedan en seeders y factories son seguros porque solo reparten roles que el usuario no tiene.

    Elevar a **coordinador** exige motivo escrito; los demás roles, no. Asignar y revocar es **solo del ADMIN**, sin herencia: si el coordinador pudiera, se ampliaría a sí mismo el rol que el RF63 reserva al administrador de la plataforma.

    **Al entrar se ve el rol permanente, no el temporal**, aunque el enum lo ponga después: la elevación es excepcional y la persona sigue haciendo su trabajo de siempre. Quien está usando un rol prestado lo ve dicho en la cabecera, con la fecha.

    **El rol activo rige también en los botones, y un rol retirado corta la pantalla que abrió (RF21).** `EstablecerRolActivo` es middleware persistente de Livewire, así que las acciones de un componente se evalúan con el rol activo y no con todos los del usuario. La sesión guarda siempre el rol con el que se abrió la pantalla, aunque no se haya elegido en el selector. Si ese rol se revoca o vence con la pantalla abierta, la siguiente acción responde 403 en vez de caer a otro rol: el botón se pulsó con un rol que ya no se tiene. Al navegar o recargar, en cambio, sí se cae al rol legítimo, sin sacar a nadie del panel.

    **Lo que quedó a medias no se toca.** Una preparación de escenario sin terminar sigue donde estaba y la continúa otro administrativo: las Policies operativas son por rol, nunca por quién empezó la tarea (`preparado_por` registra quién la terminó, no quién la reclamó). Lo mismo con lo ya hecho: una solicitud aprobada por un coordinador temporal sigue aprobada cuando su delegación vence.

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
- **En los Services se permiten las facades `DB` y `Storage`. Se prohíbe todo lo que lea la petición:** `Auth::user()`, `auth()`, `request()`, `session()` y el propio `Request`. Quien actúa llega siempre como parámetro (`User $actor`), igual que los datos.

  El porqué: un Service tiene que funcionar igual desde un controlador, un componente Livewire, un comando, un job en cola o un test. Una llamada a `Auth::user()` dentro del Service devuelve `null` en la cola y en los comandos, y además esconde quién actúa, que es justo lo que las Policies y el historial necesitan saber. `DB::transaction` y `Storage::disk` no tienen ese problema —no dependen de quién ni desde dónde se llama— y se leen mejor que una conexión inyectada, que es lo que importa para el mantenedor.
- Para el resto de dependencias, inyección por constructor.
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
- **La aplicación está en español** (`APP_LOCALE=es`, que también es el valor por defecto de `config/app.php`). Las traducciones del framework viven en `lang/es/validation.php`, `lang/es/pagination.php` y `lang/es.json`, escritas a mano y sin paquetes. Un campo nuevo que se valida lleva su nombre legible en `attributes` de `validation.php`; si no, el mensaje diría "el campo caso clinico id". No escribas mensajes propios en una pantalla para decir lo que el mensaje general ya dice: los propios son para lo que solo esa pantalla sabe explicar. `IdiomaDeLaAplicacionTest` falla si Laravel trae una regla sin traducir.

---

## 6. Antipatrones prohibidos

- **Lógica de negocio en controladores, modelos o vistas.** Va en Services.
- **Consultas N+1.** Usa `with()` siempre que recorras una relación.
- **Comprobar roles con condicionales sueltos.** Usa Policies y Gates.
- **Nombrar una Policy sin que coincida con su modelo.** Laravel las resuelve por convención: el modelo `ItemInventario` exige `ItemInventarioPolicy`, no `InventarioPolicy`. Un nombre que no coincide **no da error**: `Gate::getPolicyFor()` devuelve `null` y el Gate deniega en silencio, así que parece un problema de permisos. Ya ha pasado dos veces en este proyecto. Hay un test que recorre `app/Policies` y falla si alguna no se descubre.
- **Crear un listener sin registrarlo.** El descubrimiento automático de listeners está apagado (`bootstrap/app.php`) porque, sumado al registro a mano, duplicaba los correos. El precio es que un listener que nadie registra en `AppServiceProvider` no se ejecuta nunca y no da error. `ListenersRegistradosTest` recorre `app/Listeners` y falla si alguno no está registrado, o si lo está más de una vez, para cada evento que declara en su `handle()`.
- **Un middleware de autorización que solo protege la página.** Las acciones de un componente ya abierto van a `/livewire/update`, no pasan por las rutas del panel, y Livewire solo vuelve a aplicar ahí los middleware de su lista de persistentes. Todo middleware que decida quién entra o qué puede hacer se registra **también** con `Livewire::addPersistentMiddleware()` en `AppServiceProvider`. Y se prueba con **una petición HTTP real a `/livewire/update`**: `Livewire::test()` se salta los middleware y daría el test por bueno aunque la acción quedara abierta. `instantaneaDeLaPantalla()` y `accionDeLivewire()`, en `tests/Pest.php`, hacen lo que hace el navegador; `VigenciaInstitucionalTest` y `RolActivoEnLivewireTest` tienen el patrón, con su test de control. `MiddlewarePersistenteLivewireTest` recorre las rutas del panel y falla si falta alguno, sin excepciones.
- **Números y textos mágicos.** Enums o constantes con nombre.
- **`Model::all()`** sobre tablas que crecen. Pagina o filtra.
- **Lógica duplicada entre pantalla, PDF y Excel.** Una sola consulta en el Service alimenta las tres salidas.
- **Comentarios que repiten el código.** Comenta solo el porqué de una decisión no obvia.
- **Código comentado o muerto.** Bórralo, para eso está Git.
- **Migraciones editadas después de aplicadas.**
- **`env()` fuera de los archivos de configuración.** Usa `config()`.
- **Archivos del formato de confidencialidad en almacenamiento público.** Contienen datos personales: se sirven por ruta protegida con Policy.
- **Comprobar un efecto secundario con "al menos uno".** Los tests de correos, eventos, jobs y notificaciones exigen la cantidad exacta: `Mail::assertSent(X::class, 1)`, `Event::assertDispatched(X::class, 1)`, `Queue::assertPushed(X::class, 1)`. `assertSent` con una función y sin número pasa con uno o con cinco, y así se nos escapó que cada aprobación mandaba dos correos al docente: el listener estaba registrado dos veces.
- **Correr dos suites de tests a la vez contra la misma base.** PostgreSQL detecta el interbloqueo entre las dos y aborta transacciones, así que saltan `QueryException` en tests que no tienen nada roto. Parece un fallo del código y no lo es. Ya ha pasado dos veces en este proyecto: una suite cada vez, y espera a que termine antes de lanzar la siguiente.

---

## 7. Tests

Cada tarea termina con tests que la prueben. **Una funcionalidad sin test no está terminada.**

- **Feature tests** para los flujos completos: solicitar escenario, aprobar, preparar, evaluar.
- **Unit tests** para la lógica de los Services: cálculo de intentos, copia del checklist, disponibilidad de inventario.
- Prueba también los caminos negativos: crear una evaluación sin solicitud aprobada debe fallar; un docente no debe poder aprobar su propia solicitud si esa regla se activa; quien verifica no debe poder entregar un formato de confidencialidad por otro.
- Usa factories, nunca datos escritos a mano dentro del test.
- Los tests describen comportamiento del dominio, no implementación: `un docente no puede evaluar sin escenario aprobado`.

Antes de dar una tarea por terminada: `php artisan test` y `vendor/bin/phpstan analyse` en verde.

### Análisis estático

Larastan corre en la CI a nivel 6. `phpstan-baseline.neon` congela los errores que ya existían al instalarlo, así que la CI cae solo con los nuevos.

- **La línea base es deuda y solo puede encoger.** Al tocar un archivo, arregla sus errores de la línea base y regenérala con `vendor/bin/phpstan analyse --generate-baseline=phpstan-baseline.neon`. Si arreglas uno sin regenerarla, el análisis falla a propósito.
- **Nunca metas un error nuevo en la línea base** para que pase la CI. Se arregla.
- **Los falsos positivos no son deuda.** Van en `ignoreErrors` de `phpstan.neon`, cada uno con un comentario que explique por qué el código está bien. Ya hay tres: `?->` sobre relaciones y columnas nulables que Larastan cree que nunca son nulas. **Quitar esos `?->` rompe el código.**

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
docker compose exec app php artisan storage:link         # una vez: nginx sirve /storage
docker compose exec app php artisan test
docker compose exec app vendor/bin/phpstan analyse       # análisis estático
docker compose logs -f queue                           # trabajador de la cola (correos)
docker compose exec app php artisan make:model Nombre -mf
docker compose exec node npm run dev
docker compose logs -f app

# Producción (en el servidor; ver la sección "Producción" del README)
docker compose -f docker-compose.produccion.yml up -d --build
docker compose -f docker-compose.produccion.yml exec app php artisan migrate --force
docker compose -f docker-compose.produccion.yml exec copias /scripts/hacer-copia.sh
```

---

## 10. Cómo trabajar

- **Una tarea a la vez.** No adelantes módulos que no se pidieron.
- **Si un requerimiento es ambiguo, pregunta antes de programar.** Adivinar aquí cuesta reprocesos con el cliente.
- **Si algo contradice este archivo o el documento de arquitectura, dilo** en vez de resolverlo por tu cuenta.
- Al terminar una tarea: resume qué se hizo, qué archivos se tocaron y cómo verificarlo.
- No refactorices código ajeno a la tarea actual sin avisar.
- **Todo mensaje al usuario va en español**: respuestas, preguntas, resúmenes al terminar y también las notas de avance mientras se trabaja.

---

## 11. Pendientes abiertos con el cliente

No los resuelvas por tu cuenta; si el código los toca, déjalo señalado:

1. ~~Si un usuario con rol docente y coordinador debe poder aprobar su propia solicitud.~~ Resuelto (RF31): sí, porque siempre media la revisión administrativa.
2. Motor, acceso y estructura de la base de datos institucional para la sincronización de usuarios (RF19, RF20). Se desarrolla con datos simulados.
3. ~~Cómo se entera hoy el docente de la sala asignada al llegar a clase.~~ Resuelto (RF36): por correo, al asignar o cambiar la sala.
4. ~~Volumen real de usuarios.~~ Resuelto: el cliente confirmó ~700 estudiantes y ~150 docentes, y el RNF01 quedó actualizado.
5. Las decisiones por defecto D2–D17 de `docs/trazabilidad.md`. Se aplican si nadie las corrige. Las preguntas P1–P5 ya están respondidas y sus respuestas, en `docs/requerimientos.md`.
6. La dirección del correo del laboratorio y su contraseña de aplicación (verificación en dos pasos activada), o, si la universidad no permite contraseñas de aplicación, el relay de Google Workspace autorizado por sistemas (ver `docs/trazabilidad.md`, Bloqueos externos).
