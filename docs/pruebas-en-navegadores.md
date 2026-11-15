# Pruebas en Firefox y Edge (RNF08)

El RNF08 pide que la plataforma funcione en Chrome, Firefox y Edge. Todas las pruebas automáticas —la suite, la auditoría de QA y la campaña de rendimiento— usaron Chromium, que es el motor de Chrome y de Edge, así que **Edge comparte motor con lo probado**, pero Firefox no. Esta guía es la prueba manual que falta: una persona, unos 40 minutos por navegador, con los datos de prueba del seeder (`php artisan migrate:fresh --seed`) y el acceso de desarrollo (`/desarrollo/acceso`), que existe solo con `APP_ENV=local`.

Mientras no esté llena la tabla del final, RNF08 sigue **Parcial** en `docs/trazabilidad.md`.

## Qué tener a mano

- Firefox y Edge en su última versión, en un computador.
- Firefox en un celular Android, o el modo de diseño adaptable de Firefox (Ctrl+Shift+M) a 390 px de ancho: los administrativos usan la plataforma desde el celular.
- Un PDF cualquiera para subir como formato de confidencialidad.

En cada paso, además de lo que dice la columna «Debe pasar», mirar la consola del navegador (F12): un error en rojo es un fallo aunque la pantalla se vea bien.

## Recorridos

| # | Rol | Recorrido | Debe pasar |
|---|---|---|---|
| 1 | Sin sesión | Abrir `/`, bajar hasta el final, abrir un escenario y volver | Las secciones aparecen al bajar (o se ven completas de una vez si el sistema pide reducir el movimiento); ningún video se reproduce solo |
| 2 | Sin sesión | Si el formulario de información está habilitado, «Pedir información» en un taller, enviar con el correo mal escrito y luego bien | El error sale bajo el campo; al corregir, «Solicitud enviada.» |
| 3 | Docente | Solicitar un escenario: elegir caso clínico, fecha, hora y estudiantes, y enviar | La solicitud aparece en «Mis solicitudes» como pendiente |
| 4 | Administrativo | En «Solicitudes», entrar a esa solicitud y marcarla revisada | Los avisos de la revisión (cruces, salas) se leen; vuelve con «Solicitud marcada como revisada» |
| 5 | Coordinador | Aprobarla | El estado cambia a aprobada sin recargar a mano |
| 6 | Administrativo | En «Preparaciones», asignarle una o dos salas y marcar los ítems alistados | Las casillas responden al primer toque; el progreso avanza |
| 7 | Administrativo | «Estado de las salas»: poner una sala con novedad, con nota, y volverla a operativa | La nota aparece y desaparece |
| 8 | Cualquiera con calendario | Calendario: mes, semana y día; «Siguiente» y «Anterior»; tocar un evento | La página no salta al cargar los eventos; el detalle aparece debajo |
| 9 | Estudiante | «Mi formato»: subir el PDF | Queda como cargado; el archivo se descarga al pulsarlo |
| 10 | Administrativo | «Formatos de confidencialidad»: descargar ese PDF y verificarlo | El PDF se abre en el navegador; el estado cambia a verificado |
| 11 | Docente | Registrar una evaluación de una sesión de evaluación aprobada: marcar ítems, poner resultado, finalizar | Finaliza sin errores; el intento sale numerado |
| 12 | Coordinador | Reportes: filtrar por fechas, descargar el PDF y el Excel | Los dos archivos se descargan y se abren |
| 13 | Coordinador | Cambiar de rol con el selector de la cabecera (si tiene dos) | El menú cambia al rol elegido |
| 14 | Admin | Entrar a `/admin`, editar un taller y subir una imagen | La imagen se ve en la portada |
| 15 | Cualquiera | Salir | Vuelve a la portada |

## Resultados

Anotar la versión del navegador, la fecha y, si algo falla, el número del recorrido y qué se vio (mejor con una captura).

| Navegador | Versión | Fecha | Quién | Recorridos que fallaron | Notas |
|---|---|---|---|---|---|
| Firefox (computador) | | | | | |
| Firefox (celular, 390 px) | | | | | |
| Edge (computador) | | | | | |

Con las tres filas llenas y sin fallos abiertos, RNF08 pasa a **Completo** en la matriz. Un fallo se arregla como cualquier otro defecto: con su test, si se puede escribir uno.
