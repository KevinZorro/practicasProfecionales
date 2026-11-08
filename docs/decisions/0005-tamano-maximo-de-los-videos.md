# 0005. Tamaño máximo de los videos

- Fecha: 2026-11-07
- Estado: Aceptada. Reemplaza la decisión por defecto D7 de `docs/trazabilidad.md`

## Contexto

D7 fijaba un máximo de 100 MB por video, configurable con una variable de entorno. El código terminó con otros topes, y el cambio no quedó registrado.

## Decisión

- **Videos del contenido público (RF76):** hasta 30 MB, unos 90 segundos a 720p (`VideoPublicoService::TAMANO_MAXIMO_KB`).
- **Video de la portada:** hasta 15 MB (`ConfiguracionLandingService::TAMANO_MAXIMO_VIDEO_KB`).
- **Formato:** MP4 o WebM ya comprimidos. La plataforma no los recodifica.

Son constantes y no variables de entorno, porque el tope tiene que caber a la vez en cuatro sitios:

- `upload_max_filesize` de `php.ini`: 32 MB.
- `post_max_size` de `php.ini`: 36 MB.
- `client_max_body_size` de nginx: 36 MB.
- `config/livewire.php`: 1 MB por encima del video más grande.

`ConfiguracionLandingTest` falla si alguno se queda corto.

## Alternativas consideradas

- **100 MB (D7).** La portada se ve desde conexiones lentas (RNF10), una subida así pasaría del tiempo máximo de PHP-FPM y las copias crecerían.
- **Recodificar con ffmpeg.** Sería una dependencia pesada en la imagen.
- **Enlaces a YouTube.** Abrirían la CSP a otro sitio (D20).

## Consecuencias

- Gana: la portada carga bien en conexiones lentas.
- Sacrifica: el ADMIN tiene que comprimir el video antes de subirlo.
- Sacrifica: para cambiar el tope hay que tocar los cuatro sitios a la vez.
