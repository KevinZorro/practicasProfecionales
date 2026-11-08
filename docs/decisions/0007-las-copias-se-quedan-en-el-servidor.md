# 0007. Las copias de seguridad se quedan en el servidor

- Fecha: 2026-11-08
- Estado: Aceptada

## Contexto

El servicio `copias` hace cada día una copia de la base, las imágenes públicas y los documentos privados, y la guarda en `DIRECTORIO_COPIAS`, en el mismo servidor. La auditoría de arquitectura (S.7) propuso dos cosas: cifrarlas con una llave pública (`age`) y llevar una por semana al Drive del laboratorio. El laboratorio respondió que no le sirven: no quiere custodiar una llave aparte ni depender de que alguien suba la copia a mano.

## Decisión

Las copias se quedan en el disco del servidor, como hasta ahora: diarias, con sus sumas SHA-256, catorce días de retención (`COPIAS_RETENCION_DIAS`) y la carpeta solo para root.

## Alternativas consideradas

- **Cifrarlas con `age` y subir una por semana al Drive a mano.** Protege de perder el disco, pero exige una llave privada fuera del servidor y una tarea manual que se olvida. Descartada por el laboratorio.
- **Subirlas solas al Drive (rclone con la cuenta del laboratorio).** Quita la tarea manual, pero añade una dependencia y una credencial de Google en el servidor, y lleva datos personales a un tercero sin cifrar.

## Consecuencias

- Gana: nada nuevo que operar ni que custodiar.
- Sacrifica: **las copias protegen de un error, no de perder el disco.** Si el disco del servidor se daña, se pierden la base y sus copias a la vez. El riesgo queda aceptado por el laboratorio.
- `APP_KEY` sigue siendo tan importante como las copias: el documento de identidad de cada cuenta está cifrado con ella, y el `.env` se guarda aparte.

**Señal de revisión:** si sistemas de la UFPS ofrece un respaldo del servidor o un almacenamiento institucional, llevar ahí `DIRECTORIO_COPIAS` es configuración, no código.
