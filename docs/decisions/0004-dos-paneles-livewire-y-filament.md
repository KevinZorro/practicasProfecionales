# 0004. Dos paneles: Livewire para la operación, Filament para el ADMIN

- Fecha: 2026-11-07 (registrada después de implementarse)
- Estado: Aceptada

## Contexto

La plataforma tiene dos tipos de pantallas:

- **Flujos con reglas de negocio:** solicitudes, preparación, inventario, formato de confidencialidad y evaluaciones. Los administrativos los usan desde el celular mientras montan.
- **Catálogos del ADMIN sin reglas:** la estructura académica (RF23–RF26) y el contenido público (RF10–RF17).

## Decisión

- **`/panel`, con Livewire 3 y Blade, pensado primero para el móvil:** todo lo operativo.
- **`/admin`, con Filament 3.3:** los catálogos del ADMIN.
- **Las cuentas de usuario (RF22) van en `/panel`,** porque tienen reglas: motivo, bitácora y vigencia.
- **Filament se cierra por defecto.** Todo recurso hereda de `RecursoDelAdmin`, que niega lo que su Policy no define, y su modelo tiene Policy antes de tener pantalla.
- **En `/admin` solo entra el ADMIN,** con el rol activo aplicado.

## Alternativas consideradas

- **Todo en Livewire.** Habría que escribir más pantallas para catálogos que no tienen reglas.
- **Todo en Filament.** Sus pantallas por defecto no están pensadas para el celular, y sin Policy deja hacer todo.

## Consecuencias

- Gana: un catálogo se resuelve en pocas líneas.
- Sacrifica: el mantenedor tiene que conocer dos tecnologías de interfaz.
- Sacrifica: los assets de Filament se publican en cada `composer install`, con el script `filament:upgrade`.
- Sacrifica: hay una regla que vigilar, que nada operativo vaya a Filament. `RecursosDelAdminTest` comprueba la clase base y la Policy de cada recurso.

**Señal de revisión:** si una pantalla del ADMIN empieza a necesitar reglas de negocio, se pasa al panel.
