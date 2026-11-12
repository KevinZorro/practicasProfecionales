# Decisiones de arquitectura

Las decisiones de arquitectura de la plataforma, con su contexto, las alternativas que se descartaron y lo que cuestan. Sirven para que quien mantenga la plataforma sepa por qué está hecha así y cuándo conviene revisarlas.

Las decisiones pequeñas, como las reglas por defecto D1–D25 o las preguntas respondidas P1–P16, siguen en `docs/trazabilidad.md`.

Una decisión no se edita: si cambia, se escribe una nueva que la reemplaza, y la vieja queda marcada como reemplazada.

| # | Decisión | Estado |
|---|---|---|
| [0001](0001-entrada-solo-con-google.md) | Entrar solo con la cuenta institucional de Google | Aceptada |
| [0002](0002-usuarios-desde-la-vista-institucional.md) | Copiar los usuarios de una vista de la base institucional | Aceptada; falta la vista |
| [0003](0003-postgresql-para-todo-en-un-solo-servidor.md) | PostgreSQL para todo, en un solo servidor | Aceptada |
| [0004](0004-dos-paneles-livewire-y-filament.md) | Dos paneles: Livewire para la operación, Filament para el ADMIN | Aceptada |
| [0005](0005-tamano-maximo-de-los-videos.md) | Tamaño máximo de los videos (reemplaza D7) | Aceptada |
| [0006](0006-https-en-el-proxy-de-la-universidad.md) | El HTTPS lo termina el proxy de la universidad | Aceptada; falta la IP del proxy |
| [0007](0007-las-copias-se-quedan-en-el-servidor.md) | Las copias de seguridad se quedan en el servidor | Aceptada |
| [0008](0008-pruebas-de-rendimiento-fuera-de-la-suite.md) | Las pruebas de rendimiento corren fuera de la suite, con k6 y pgbench en contenedores | Aceptada |
