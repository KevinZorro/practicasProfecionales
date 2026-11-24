# Despliegue nativo (sin Docker)

Para servidores donde no se permite Docker (ADR 0010). La instalación con Docker sigue en `docs/instalacion-en-el-servidor.md`. Aquí PHP-FPM y nginx son los del sistema, y PostgreSQL está en otra máquina.

## Lo que tiene que dejar el administrador del servidor

Una sola vez, con `sudo`. Todo lo demás lo hace `./nativo.sh` sin privilegios.

| Qué | Detalle |
|---|---|
| PHP 8.3 (FPM y CLI) | Mínimo 8.3; 8.3.36 o superior trae el parche de PHP2Shell. No hace falta PHP 8.4. |
| Extensiones | `pdo_pgsql`, `gd` (con WebP), `intl`, `zip`, `mbstring`, `xml`, `curl`, `exif`, `pcntl`, `opcache` (paquetes `php8.3-pgsql`, `-gd`, `-intl`, `-zip`, `-mbstring`, `-xml`, `-curl`, `-opcache`; `exif` y `pcntl` vienen con el paquete base de Debian) |
| Composer 2, Node 22 y npm | Los usa el usuario que despliega. |
| `postgresql-client` | `pg_dump` para las copias; `psql` para probar. |
| nginx | Con la configuración de abajo. |
| `cron` | El usuario que despliega tiene que poder usar `crontab`. |
| La base | Alcanzable desde este servidor por el puerto 5432: `pg_hba.conf` debe admitir su IP. |

### nginx

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/laboratorio/public;
    index index.php;
    charset utf-8;
    server_tokens off;
    client_max_body_size 36M;      # el tope de los videos (ConfiguracionLandingTest)

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ^~ /build/ { expires 1y; add_header Cache-Control "public, immutable"; }
    location ^~ /storage/ { try_files $uri =404; expires 30d; add_header X-Content-Type-Options nosniff; }
    location ~ \.php$ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_read_timeout 120;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
```

El de Docker (`docker/produccion/nginx.conf`) trae además topes por IP y compresión, que aquí son opcionales.

### Permisos

Lo mejor es que el usuario que despliega esté en el grupo `www-data` (`usermod -aG www-data deploy_dev`): `nativo.sh` comparte `storage` y `bootstrap/cache` por grupo y deja el `.env` en `640`. Sin eso, los deja escribibles por todos (`o+rwX`) y la configuración en caché —con las contraseñas— queda legible por cualquier usuario del servidor. Sirve para un sandbox, no para producción.

## Instalar

```bash
cd /var/www/laboratorio            # el código ya clonado
./nativo.sh configurar             # crea .env con APP_KEY y dice qué falta
nano .env                          # APP_URL y DB_PASSWORD (ver abajo)
./nativo.sh instalar               # dependencias, assets, migraciones, caché, permisos y cron
./nativo.sh administrador correo@ufps.edu.co "Nombre Apellido"
./nativo.sh verificar
```

En `.env`, `DB_HOST=172.18.222.232`, `DB_DATABASE=laboratorio` y `DB_USERNAME=testlab` ya vienen del ejemplo; falta `DB_PASSWORD` y `APP_URL`. **Las contraseñas solo van en el `.env` del servidor**, nunca en el repositorio. Para comprobar la base antes de instalar: `psql -h 172.18.222.232 -U testlab -d laboratorio`.

### Usuario de la base

- **Un solo usuario (sandbox).** Deja `DB_DUENO_USUARIO` vacío. `testlab` migra y la aplicación entra con él, así que tiene que ser dueño de la base y poder crear tablas en `public`.
- **Dueño y usuario de la aplicación (recomendado, S.1).** `DB_DUENO_USUARIO`/`DB_DUENO_CONTRASENA` con un rol que tenga `CREATEROLE`; la aplicación entra con `DB_USERNAME`, que `base:usuario-de-la-aplicacion` crea sin superusuario y solo con permisos sobre filas.

### Correo, Google y HTTPS

- `MAIL_MAILER=log` deja los correos en el registro mientras no haya cuenta SMTP.
- La entrada con Google exige `https` y un dominio (no acepta una IP). Mientras se entre por `http://IP`, la cookie no puede ser `secure` (`SESSION_SECURE_COOKIE=false`), y nadie podrá iniciar sesión con Google. Con HTTPS en el proxy de la universidad (ADR 0006): `APP_URL=https://…`, `SESSION_SECURE_COOKIE=true` y `PROXIES_DE_CONFIANZA`.

## Actualizar

```bash
./nativo.sh actualizar             # git pull, copia, composer, npm, migraciones, caché
./nativo.sh actualizar --sin-git   # con el código tal como está
```

Pone la aplicación en mantenimiento mientras cambia `vendor/` y la base; si algo falla, se queda así a propósito (`php artisan up` para abrirla). `queue:restart` hace que los trabajadores tomen el código nuevo. Después de cambiar el `.env` hay que correr `actualizar --sin-git` (o `php artisan optimize`): la configuración está en caché.

## Cola, programador y copias

`./nativo.sh cron` escribe un bloque marcado en el `crontab` del usuario, sin tocar el resto:

- cada minuto, `queue:work` bajo `flock` (uno solo a la vez; `--max-time=3600` lo reinicia cada hora, y el cron lo levanta si se cae);
- cada minuto, `schedule:run` (el aviso del RF60);
- a las 02:30, `nativo.sh copia`, si `DIRECTORIO_COPIAS` está definido.

Los correos se envían por la cola: sin el cron no salen. Con systemd (necesita root) serían dos unidades `Type=simple` con `User=` el que despliega, `ExecStart=/usr/bin/php8.3 artisan queue:work --tries=3 --timeout=90 --max-time=3600` y `schedule:work`, `Restart=always`.

**Copia.** `./nativo.sh copia` guarda en `DIRECTORIO_COPIAS/AAAA-MM-DD_HHMM/` el volcado de la base (`pg_dump -Fc`), los archivos públicos y los privados (el formato de confidencialidad firmado), con `SHA256SUMS`, y borra las de más de `COPIAS_RETENCION_DIAS`. Las copias tienen datos personales (Ley 1581): carpeta de uso exclusivo del usuario, y conviene copiarla fuera del servidor. **Guarda el `.env` aparte:** sin `APP_KEY` se pierden los documentos cifrados.

**Restaurar.** En una base vacía: `pg_restore --no-owner --clean --if-exists -h 172.18.222.232 -U <dueño> -d laboratorio base-de-datos.dump`, y `tar -xzf archivos-publicos.tar.gz -C storage/app/public` (y lo mismo con los privados).

## Registros y problemas comunes

- Registros: `storage/logs/laravel-AAAA-MM-DD.log` (14 días); los de nginx y PHP-FPM, donde los deje el sistema.
- *No conecta con la base:* `psql` a mano; si falla, `pg_hba.conf`, el firewall o la contraseña. Con `DB_HOST=db` no resuelve: ese nombre solo existe dentro del compose.
- *403/500 al subir una imagen:* revisa que `storage/app/public` sea escribible por `www-data` y que `public/storage` exista (`php artisan storage:link`).
- *Se subió un video y falla:* `public/.user.ini` (lo escribe `nativo.sh`) tarda hasta 5 minutos en leerse (`user_ini.cache_ttl`), y `client_max_body_size` de nginx debe ser 36M.
- *Cambié el `.env` y no pasa nada:* `php artisan optimize` (la configuración está en caché).
