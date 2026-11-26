# Manual de usuario del administrador

- `manual-de-usuario-administrador.md`: la fuente. Se edita aquí.
- `manual-de-usuario-administrador.pdf`: la versión para entregar, generada a partir de la fuente.
- `img/`: capturas que usa el manual.

Sigue la estructura de ISO/IEC/IEEE 26514 (información para usuarios). Cuando cambie una pantalla o una regla que el manual explica, actualiza la sección, sube la versión y anota el cambio en «Control de versiones».

Para regenerar un Word sencillo desde la fuente (sin el bloque de metadatos del inicio):

```bash
cd docs/manual-administrador
pandoc manual-de-usuario-administrador.md --toc -o manual.docx
```

Luego se abre en Word o LibreOffice, se actualiza el índice y se exporta a PDF.
