# 0009. La instalación es un comando, y el primer ADMIN se nombra desde la consola

- Fecha: 2026-11-22
- Estado: Aceptada

## Contexto

La primera instalación eran siete órdenes a mano: copiar el ejemplo, inventar dos contraseñas, construir, generar la `APP_KEY` y pegarla en el `.env`, crear la carpeta de las copias, migrar y levantar. Las iba a correr el desarrollador una vez, en el sandbox que preparó el ingeniero de la universidad, con el tiempo contado; después las repetirá el administrador del servidor, que conoce PHP pero no Docker.

Al revisarlas aparecieron dos huecos que dejaban la instalación inservible aunque cada orden saliera bien:

1. **No había roles.** Solo los creaba `RolSeeder`, y a ese solo lo llamaba `db:seed` junto con los datos de prueba, que en producción no se corren (y no podrían: Faker es de desarrollo).
2. **No había forma de crear el primer ADMIN.** La entrada con Google no crea cuentas (RF18, regla 8), y crear cuentas y repartir roles exige un ADMIN con sesión que pase la Policy.

## Decisión

1. **`produccion.sh`, en la raíz, hace la instalación con una orden por tarea:** `instalar`, `actualizar`, `verificar`, `administrador`, `configurar` y `empaquetar`. Es bash sobre `docker compose`, sin nada más que instalar; imprime cada orden de Docker antes de correrla, y repetirlo no cambia lo que ya está. La guía es `docs/instalacion-en-el-servidor.md`.
2. **El `.env` lo arma el script.** Genera la `APP_KEY` y las contraseñas, pregunta lo que no puede adivinar y deriva la cookie segura de si `APP_URL` es `https://`. Nunca cambia los secretos de un `.env` que ya existe, y no inventa unos nuevos si encuentra el volumen de una base anterior sin su `.env`: con otra contraseña la base no abre, y con otra `APP_KEY` se pierden los documentos cifrados.
3. **Los roles los crea el servicio `migraciones` en cada despliegue**, con `RolSeeder`, después de migrar.
4. **El primer ADMIN lo nombra quien tiene la consola del servidor**, con `usuarios:crear-administrador`. Crea la cuenta con origen `manual`, o usa la que ya existe, y le da el rol permanente por `AsignacionDeRolService`. Sin Policy: no hay nadie con sesión con quién comprobarla, y quien llega a esa consola ya puede todo (lee el `.env`, entra a la base con su dueño). Sin entrada en la bitácora, que exige quién actuó: el rastro queda en `asignaciones_de_rol`, con `asignado_por` nulo y el motivo de la consola.
5. **Un plan B sin internet:** `empaquetar`, en un equipo que sí lo tenga, guarda las imágenes en un `.tar.gz` y el código commiteado en un `git bundle`; `instalar --imagenes` los carga.

## Alternativas consideradas

- **Ansible o un Makefile.** Ansible es otra herramienta que aprender y mantener para un solo servidor; `make` no viene en un Debian mínimo y un Makefile con esta lógica se lee peor que un script.
- **Los roles en una migración de datos.** Habría que escribir otra cada vez que el enum `Rol` ganara un caso; el seeder lo recorre solo y no duplica.
- **El primer ADMIN desde el `.env`** (`ADMIN_EMAIL`, aplicado en cada despliegue). Volvería a nombrar ADMIN a quien el laboratorio le quitó el rol, y un cambio en un archivo de configuración repartiría permisos sin que nadie lo vea.
- **Abrir el acceso de desarrollo en el sandbox.** Es una puerta sin Google en una red compartida; la entrada sigue siendo solo con Google (ADR 0001) y la del sandbox se prueba con un túnel SSH a `localhost`, que Google acepta sin HTTPS.
- **Registrar el nombramiento en la bitácora con el nuevo ADMIN como autor.** Diría que actuó alguien que no actuó.

## Consecuencias

- Gana: instalar y actualizar son una orden, con una copia de seguridad antes de cada migración. El job de producción de la CI instala con el mismo script, así que se prueba en cada PR, y `InstaladorDeProduccionTest` prueba el `.env` y que el script comparta nombres de proyecto e imágenes con el compose.
- Sacrifica: un script de bash de varios cientos de líneas que mantener junto al compose.
- `asignado_por` nulo en `asignaciones_de_rol` ya no significa solo «lo puso la sincronización»: significa «no lo puso una persona desde el panel», y el motivo dice si fue la sincronización o la consola.
- Nombrar un ADMIN desde la consola no aparece en la bitácora de la plataforma. Quien tiene esa consola ya podía hacer cualquier cosa sin dejar rastro en ella; el control de ese acceso es del servidor, no de la aplicación.

**Señal de revisión:** si la universidad adopta una herramienta propia de despliegue (Ansible, un pipeline de sistemas), los pasos de `instalar` están en la guía y se pueden trasladar tal cual.
