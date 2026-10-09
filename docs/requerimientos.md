# REQUERIMIENTOS FUNCIONALES

**Versión:** 9 de octubre de 2026, con las aclaraciones del mismo día. Reemplaza las versiones anteriores e incorpora las decisiones tomadas con el laboratorio hasta esta fecha. Los cambios están resumidos al final del documento.

**Nota de roles:** el sistema maneja cinco roles (ADMIN, coordinador, administrativo, docente y estudiante). El coordinador hereda todos los permisos del administrativo. Un usuario puede tener varios roles asignados simultáneamente.

## Landing pública

- **RF01** — El sistema debe mostrar información institucional del laboratorio, incluyendo cifras destacadas (número de salas, estudiantes por semestre, escenarios disponibles) y galería de fotografías.
- **RF02** — El sistema debe reproducir un video institucional de fondo en la sección principal de la landing.
- **RF03** — El sistema debe mostrar los escenarios clínicos disponibles con sus capacidades (sangrado, respuestas fisiológicas, sonido, signos vitales), como resumen con opción de ver detalle ampliado.
- **RF04** — El sistema debe mostrar los talleres y cursos ofertados con imagen, tema, fecha y modalidad (virtual o presencial).
- **RF05** *(modificado)* — El sistema debe mostrar los eventos del laboratorio con fecha, tipo e indicador de si están abiertos al público. Los tipos de evento los define el ADMIN.
- **RF06** — El sistema debe mostrar las certificaciones y acreditaciones del laboratorio en formato de insignias.
- **RF07** — El sistema debe mostrar el listado de docentes con foto, nombre, cargo y títulos académicos.
- **RF08** — El sistema debe mostrar una galería de videos institucionales.
- **RF09** — El sistema debe ofrecer un formulario de solicitud de información por taller, cuyo envío se dirige a un correo institucional no-reply. La sección debe poder ocultarse mientras no esté habilitada.

## Gestión de contenido público (ADMIN)

- **RF10** — El ADMIN debe poder gestionar la información institucional, las cifras destacadas y la galería de fotografías de la landing.
- **RF11** — El ADMIN debe poder actualizar el video institucional de fondo.
- **RF12** — El ADMIN debe poder gestionar los escenarios clínicos publicados con su imagen, descripción, ítems de capacidades y equipo asociado.
- **RF13** — El ADMIN debe poder crear, editar y eliminar talleres y cursos.
- **RF14** *(modificado)* — El ADMIN debe poder crear, editar y eliminar eventos, y gestionar el catálogo de tipos de evento.
- **RF15** — El ADMIN debe poder gestionar las certificaciones mostradas como insignias.
- **RF16** — El ADMIN debe poder gestionar el listado de docentes y sus títulos académicos.
- **RF17** *(modificado)* — El ADMIN debe poder gestionar la galería de videos institucionales, subiendo cada video al servidor de la plataforma con su título, imagen de portada y orden. Se admiten videos en MP4 con un tamaño máximo configurable, y la landing no descarga ningún video hasta que el visitante lo reproduce. La galería está pensada para pocas decenas de videos y de fotografías.

## Autenticación y acceso

- **RF18** — El sistema debe permitir el inicio de sesión únicamente mediante cuenta de Google institucional, restringido al dominio de la universidad.
- **RF19** *(modificado)* — El sistema debe validar el acceso cruzando el correo institucional contra la información de matrícula y contratación de la universidad, permitiendo el ingreso únicamente a personas vigentes de los cuatro programas que usan el laboratorio (enfermería, licenciatura en ciencias naturales, seguridad y salud en el trabajo y regencia en farmacia). El acceso no se restringe por facultad.
- **RF20** *(modificado)* — El sistema debe conectarse a la base de datos institucional con un usuario de solo lectura proporcionado por la universidad, copiar los datos necesarios a una base propia y actualizar esa copia de forma periódica, con una frecuencia configurable. Los usuarios que dejan de estar vigentes se desactivan y nunca se borran, y el sistema no modifica la información de origen. Si una actualización fuera a desactivar más usuarios de un umbral configurable, se detiene sin aplicar cambios y avisa al ADMIN.
- **RF21** — El sistema debe ofrecer un selector de vista en la parte superior derecha que permita a los usuarios con varios roles alternar entre ellos sin cerrar sesión.

## Gestión de usuarios y estructura académica (ADMIN)

- **RF22** — El ADMIN debe poder crear, actualizar y deshabilitar usuarios, y asignar uno o más roles a cada uno.
- **RF23** — El ADMIN debe poder crear y gestionar materias, asociando cada una a un semestre.
- **RF24** — El ADMIN debe poder crear y gestionar los tipos de caso clínico y asociarlos a una o varias materias.
- **RF25** — El ADMIN debe poder asociar a cada tipo de caso clínico los simuladores, equipo clínico y equipo básico requeridos, con sus cantidades.
- **RF26** *(modificado)* — El ADMIN debe poder crear tipos de evaluación, asociarlos a una o varias materias y definir su lista de ítems de checklist, con al menos un ítem y sin ponderación entre ellos. Un tipo de evaluación desactivado no puede usarse en evaluaciones nuevas.

## Solicitud de escenarios

- **RF27** — El sistema debe permitir al docente solicitar un escenario, indicando el tipo de sesión (práctica o evaluación).
- **RF28** *(modificado)* — El sistema debe permitir al docente seleccionar el caso clínico, la fecha y hora, la materia, el grupo, la cantidad de estudiantes y los estudiantes que asistirán a esa sesión. El grupo es la subdivisión de la clase que pasa a los simuladores, identificada con una letra (A, B, C…), porque cada simulador admite menos estudiantes que una clase completa. La sala no es seleccionada por el docente. En las sesiones apartadas antes del semestre, la fecha proviene del registro previo (RF57).
- **RF29** — Al seleccionar el caso clínico, el sistema debe precargar los simuladores y equipos asociados, permitiendo ajustar cantidades o agregar elementos adicionales.
- **RF30** *(modificado)* — El sistema debe permitir a los administrativos revisar las solicitudes de escenario y rechazarlas cuando corresponda. La revisión es obligatoria antes de la aprobación e incluye verificar cruces de horario, disponibilidad de insumos y simuladores, y estado del espacio físico (aire acondicionado, filtraciones, escenario deshabilitado).
- **RF31** *(modificado)* — El sistema debe permitir a coordinación aprobar o rechazar las solicitudes previamente revisadas. La solicitud pasa así por dos fases: el administrativo la acepta o la rechaza, y coordinación aprueba o rechaza la que el administrativo aceptó. El ADMIN puede aprobar en ausencia de la coordinadora, siempre que exista revisión administrativa previa registrada. Como toda solicitud pasa antes por la revisión administrativa, no se requiere un bloqueo adicional cuando la coordinadora aprueba una solicitud hecha por ella misma como docente.
- **RF32** — En caso de rechazo, el sistema debe permitir registrar un comentario opcional con el motivo.
- **RF33** — El sistema debe notificar por correo electrónico al docente el resultado de su solicitud.
- **RF34** — El sistema debe mostrar las reservas aprobadas en un calendario visible para estudiantes, docentes, administrativos y coordinación, diferenciando visualmente las sesiones de práctica de las de evaluación. La sala se muestra una vez asignada.
- **RF35** — El sistema debe permitir al docente consultar el historial de sus solicitudes con su estado, la sala asignada cuando ya esté definida y, en caso de rechazo, el motivo.

## Preparación de escenarios

- **RF36** *(modificado)* — El sistema debe mostrar a los administrativos la vista diaria de escenarios aprobados por preparar, permitiendo asignar la sala en la que se montará cada caso clínico, con el detalle de simuladores y equipos requeridos. El administrativo elige la sala, y el sistema solo le ofrece las que están libres en esa franja. Al asignar o cambiar la sala, el sistema debe notificar por correo al docente cuál es.
- **RF37** — El sistema debe permitir marcar individualmente los elementos alistados y registrar el estado general del escenario (pendiente por preparar, en preparación o preparado), con campo de observaciones. La preparación admite avance parcial, continuado por otra persona y en día distinto al de la sesión.

## Inventario

- **RF38** — El sistema debe permitir a los administrativos y coordinadores registrar y actualizar el inventario, incluido el retiro de unidades, distinguiendo insumos consumibles (gestionados por el personal administrativo) de accesorios y repuestos asociados a un simulador específico.
- **RF39** — El sistema debe registrar el nivel de fidelidad de cada simulador (baja, media o alta) como atributo interno del inventario. Lo gestiona el ADMIN.
- **RF40** — El sistema debe permitir a los administrativos y coordinadores consultar la disponibilidad de equipos y simuladores.

## Evaluación de habilidades

- **RF41** — El sistema debe permitir al docente crear una evaluación únicamente a partir de una solicitud de tipo evaluación previamente aprobada.
- **RF42** — El sistema debe impedir la creación de evaluaciones que no estén vinculadas a una solicitud de escenario aprobada.
- **RF43** — El docente debe seleccionar el tipo de evaluación entre los disponibles para la materia correspondiente, sin poder modificar los ítems del checklist.
- **RF44** — Al crear una evaluación, el sistema debe copiar los ítems del checklist vigentes en ese momento, preservando el histórico ante cambios posteriores de la plantilla.
- **RF45** *(modificado)* — El sistema debe permitir agregar múltiples estudiantes a una misma evaluación. Solo pueden agregarse estudiantes habilitados para ingresar al laboratorio, de modo que el sistema impide incluir a quien no tenga el formato de confidencialidad en estado verificado o entregado en físico, o esté bloqueado (RF68).
- **RF46** — El sistema debe permitir al docente marcar los ítems del checklist y registrar de forma independiente el resultado de cada estudiante (aprobado o no aprobado). El resultado lo determina el docente; el sistema no lo calcula automáticamente.
- **RF47** — El sistema debe permitir registrar observaciones opcionales por estudiante.
- **RF48** — El sistema debe registrar un intento por cada evaluación realizada a un estudiante, con su resultado. Todo procedimiento evaluado cuenta como intento, independientemente de si aprobó o no. El sistema no limita ni programa la repetición, y si el estudiante repite, se registra como un nuevo intento.
- **RF49** — El sistema debe permitir al estudiante consultar sus evaluaciones, resultados, intentos y el detalle del checklist evaluado.
- **RF50** — El sistema debe permitir al docente consultar el historial de evaluaciones realizadas.

## Formato de confidencialidad

- **RF51** — El ADMIN debe poder cargar el formato de confidencialidad vigente en formato PDF por periodo académico, aplicable tanto a estudiantes como a docentes.
- **RF52** — El sistema debe permitir a estudiantes y docentes descargar, firmar de forma manuscrita y cargar el formato de confidencialidad una vez por periodo académico, obligatoriamente desde la cuenta institucional. No se admite firma digital.
- **RF53** — El sistema debe registrar el estado del formato por persona (pendiente, entregado en físico, cargado o verificado), organizado por programa, materia y grupo, y condicionar el ingreso al laboratorio a su cumplimiento. El estado «entregado en físico» habilita el ingreso y deja la carga pendiente. La verificación y aprobación la realizan los administrativos y pasantes; coordinación y ADMIN conservan el permiso y supervisan que la verificación se haya hecho.

## Reportes

- **RF54** — El sistema debe generar reportes de uso de escenarios que incluyan docente, materia, semestre, caso clínico, sala, horas utilizadas, número de estudiantes y tipo de sesión, con filtros por rango de fechas. Las horas se atribuyen al docente que efectivamente dictó la sesión, indicando cuando hubo sustitución.
- **RF55** — El sistema debe generar reportes de evaluaciones para coordinación y jefaturas, incluyendo resultados por materia, docente y estudiante, exportables en PDF y Excel.
- **RF56** — El sistema debe identificar en los reportes las sesiones de evaluación aprobadas cuya evaluación no fue registrada.

## Programación de sesiones

- **RF57** *(modificado)* — El sistema debe permitir a los administrativos registrar las sesiones apartadas con anterioridad al semestre, que se repiten cada periodo, a partir del formato físico que maneja el laboratorio y que siempre llega antes de iniciar el semestre. El registro es manual y parcial, de modo que se cargan las sesiones apartadas y no el sílabo completo. Estas sesiones llegan ya aprobadas, porque es coordinación quien entrega el formato a los administrativos, y no pasan por la revisión ni la aprobación de RF30 y RF31. El sistema admite tanto estas sesiones como las solicitudes que surgen durante el semestre (RF27).
- **RF58** *(modificado)* — Al registrar una sesión, el sistema debe validar que la sala y los simuladores requeridos estén disponibles en esa fecha y hora, advirtiendo de cruces con sesiones ya registradas. El tiempo entre sesiones para desmontar y montar lo decide el personal del laboratorio, y el sistema no lo impone.
- **RF59** — El sistema debe permitir registrar, por cada sesión, el formato intramural con los insumos, equipo clínico y simuladores que el docente requiere, como paso separado y posterior a la programación de la fecha. Lo registran los administrativos a partir del formato impreso que el laboratorio recibe antes del semestre.
- **RF60** — El sistema debe alertar a administrativos y al docente cuando una sesión próxima no tenga formato intramural registrado, con la antelación que configure el laboratorio.
- **RF61** — Los administrativos deben poder reprogramar una sesión aprobada (fecha, hora o escenario), exigiendo motivo, constancia de comunicación previa con el docente y notificación automática al docente.
- **RF73** — El sistema debe permitir sustituir al docente responsable de una sesión cuando no pueda asistir, registrando la novedad, el docente original y el que lo reemplaza.

## Periodo académico *(nuevo)*

- **RF75** *(nuevo)* — Los administrativos, la coordinación y el ADMIN deben poder abrir y cerrar el periodo académico vigente desde la plataforma, sin que el sistema imponga fechas. El formato de confidencialidad y los demás procesos semestrales se rigen por el periodo que ellos definan.

## Auditoría y roles

- **RF62** — El sistema debe registrar en bitácora de auditoría toda aprobación, rechazo, reprogramación, retiro y cambio de rol, indicando usuario, fecha y motivo.
- **RF63** — El ADMIN debe poder elevar temporalmente el rol de un usuario a coordinador, con vigencia definida y registro en bitácora.
- **RF64** — El sistema debe soportar roles administrativos temporales para pasantes, con fecha de finalización.

## Espacios, inventario y preparación

- **RF65** — El ADMIN debe poder crear, editar y reubicar espacios físicos y vincularlos con los escenarios clínicos que se montan en ellos, conservando el histórico de la reubicación. Cada sala tiene un nombre editable y una ubicación formada por bloque, piso y número de sala dentro de ese bloque y piso, para dar cabida a edificios nuevos.
- **RF66** — El sistema debe permitir registrar el estado funcional de cada ítem de inventario (operativo, en revisión, defectuoso o dado de baja), con motivo y responsable del reporte.
- **RF67** *(modificado)* — El sistema debe mantener una lista de insumos pendientes por pedir o reponer, que los administrativos alimentan y la coordinadora confirma. Una vez confirmada, la lista es exportable como soporte de la solicitud de compra de fin de semestre.
- **RF72** — El sistema debe distinguir, en la preparación del escenario, los elementos que monta el personal administrativo de los que requieren intervención del ingeniero (simuladores de alta fidelidad).
- **RF74** *(modificado)* — El ADMIN debe poder definir y editar la capacidad máxima de estudiantes de cada caso clínico, y el sistema debe validarla al registrar la sesión. Esa es la capacidad que limita la cantidad de estudiantes; la capacidad de la sala es informativa y no bloquea el registro.

## Control de acceso de participantes

- **RF68** — La coordinación debe poder bloquear a un estudiante o a un docente para el uso del laboratorio por incumplimiento del reglamento u otras causales, registrando el motivo.
- **RF69** — El sistema debe permitir retirar a un estudiante o docente de una sesión en curso o programada, dejando registro del motivo y del responsable de la decisión.
- **RF70** *(modificado)* — El sistema debe mostrar al docente, antes de cada sesión, qué participantes no pueden asistir y por qué motivo. Quien no tenga el formato de confidencialidad vigente o esté bloqueado no puede ingresar al laboratorio y, por lo tanto, tampoco ser evaluado.
- **RF71** — Los administrativos deben poder verificar el cumplimiento del formato de confidencialidad de un grupo completo en una sola pantalla, con búsqueda por persona.

# REQUERIMIENTOS NO FUNCIONALES

- **RNF01** — **Rendimiento:** el sistema debe soportar la población real del laboratorio, aproximadamente 700 estudiantes y 150 docentes.
- **RNF02** — **Disponibilidad:** el sistema debe estar disponible durante el horario académico institucional.
- **RNF03** — **Portabilidad:** el sistema debe poder desplegarse mediante contenedores Docker sobre Debian 13 Trixie.
- **RNF04** — **Seguridad:** el acceso a los módulos debe restringirse según el rol; el coordinador hereda los permisos del administrativo y el ADMIN mantiene control total de la plataforma.
- **RNF05** — **Usabilidad:** la interfaz debe ser responsiva y utilizable en computadores y dispositivos móviles.
- **RNF06** — **Mantenibilidad:** el código debe seguir una arquitectura modular por capas, documentada para su mantenimiento por terceros.
- **RNF07** — **Confidencialidad:** la información de estudiantes y evaluaciones debe ser accesible solo por roles autorizados.
- **RNF08** — **Compatibilidad:** el sistema debe funcionar en los navegadores web más utilizados (Chrome, Firefox, Edge).
- **RNF09** — **Escalabilidad:** la arquitectura debe permitir agregar nuevos módulos sin afectar los existentes.
- **RNF10** — **Rendimiento en condiciones limitadas:** el sistema debe funcionar de manera aceptable en conexiones de baja velocidad y en computadores de gama baja.

# FUERA DE ALCANCE

- Firma digital del formato de confidencialidad, descartada por exigencia legal de la coordinadora, porque la firma debe ser manuscrita.
- Control y asignación de llaves de muebles, vitrinas, lockers y escenarios, que el cliente no solicitó.
- Carpeta de almacenamiento personal de documentos, porque el laboratorio ya cuenta con Google Drive.
- Hoja de alistamiento impresa, porque la preparación se registra en la plataforma.
- Control automático del tiempo entre sesiones en una misma sala, que decide el personal del laboratorio.
- Conversión o recompresión automática de los videos subidos, porque se suben ya comprimidos en MP4.

# MEJORAS POSTERGADAS

- Duplicar las sesiones del semestre anterior como punto de partida del registro previo (RF57). No fue solicitada y se evaluará después de la entrega.

# PENDIENTES POR CONFIRMAR

- Credenciales del usuario de solo lectura a la base de datos institucional y estructura de las tablas que se van a copiar (RF20). Sin ellas, la sincronización se desarrolla y se prueba con datos simulados.

# DATOS DE DESPLIEGUE POR OBTENER

No son requerimientos, pero condicionan la puesta en producción y dependen de la universidad.

- Dominio o subdominio definitivo de la plataforma, necesario para registrar la dirección de retorno en Google.
- Responsable de crear las credenciales de Google para el inicio de sesión.
- Si el certificado HTTPS lo pone un proxy de la universidad o lo requiere el propio servidor.
- Destino externo para las copias de seguridad.

# CAMBIOS DE ESTA VERSIÓN

- **RF05 y RF14.** Los tipos de evento pasan a ser un catálogo que gestiona el ADMIN.
- **RF17.** Los videos de la galería se suben al servidor de la plataforma en MP4, con portada y tamaño máximo, y no se descargan hasta que el visitante los reproduce.
- **RF19 y RF20.** La información institucional se obtiene con una conexión de solo lectura a la base de datos de la universidad, copiada a una base propia y actualizada periódicamente, con una protección ante desactivaciones masivas.
- **RF26.** El checklist exige al menos un ítem, y un tipo de evaluación desactivado no se usa en evaluaciones nuevas.
- **RF28.** Se define el grupo como la subdivisión de la clase que pasa a los simuladores.
- **RF30 y RF31.** El rechazo lo realizan los administrativos durante la revisión, la aprobación sigue siendo de coordinación o del ADMIN en su ausencia, y no se bloquea la autoaprobación porque siempre media la revisión administrativa.
- **RF36.** El docente recibe por correo la sala asignada.
- **RF38.** Los administrativos pueden retirar unidades del inventario.
- **RF45 y RF70.** No se puede agregar a una evaluación a quien no tenga el formato vigente o esté bloqueado.
- **RF52.** La renovación del formato se rige por el periodo académico y no por el semestre calendario.
- **RF57 y RF58.** Conviven las sesiones registradas antes del semestre y las solicitudes del semestre, y el tiempo entre sesiones lo decide el personal.
- **RF62.** La bitácora registra también los rechazos.
- **RF67.** La coordinadora confirma la lista de reposición antes de exportarla.
- **RF74.** La capacidad que limita la cantidad de estudiantes es la del caso clínico, no la de la sala.
- **RF75.** Nuevo requerimiento para que el laboratorio abra y cierre el periodo académico sin fechas impuestas por el sistema.
- **Aclaraciones del 9 de octubre.** RF28: el docente indica los estudiantes de cada sesión. RF31: coordinación también puede rechazar, en una segunda fase. RF36: solo se ofrecen salas libres. RF57: las sesiones apartadas llegan aprobadas. RF59: el formato intramural lo digitan los administrativos. RF65: las salas se ubican por bloque, piso y número.
- **Pendientes cerrados.** Autoaprobación, antelación del formato intramural, aviso de sala al docente, solicitudes fuera de la programación, tipos de evento, quién rechaza, retiro de inventario, cierre de la lista de reposición, grupos, tiempo entre sesiones, canal de videos, participantes sin formato o bloqueados, conciliación de capacidades, mes de inicio del semestre y hoja de alistamiento.
