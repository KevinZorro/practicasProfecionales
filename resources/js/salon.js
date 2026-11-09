/*
 * El salón de una sesión mientras se eligen sus salas (P16): de una a tres,
 * unidas abriendo la cortina, con la capacidad sumada frente al grupo.
 *
 * Solo enseña. Si el grupo cabe y si las salas siguen libres lo decide
 * PreparacionService::asignarSalas en el servidor, que avisa igual si algo
 * no cuadra. Esto le ahorra al administrativo pulsar «Asignar» para
 * enterarse, que con mala señal tarda.
 *
 * La selección se lee de $wire.salasElegidas: wire:model la actualiza en el
 * navegador al marcar la casilla, sin ir al servidor. El movimiento es CSS
 * (.salon en app.css); aquí solo se decide qué se ve.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('salonDeLaSesion', ({ salas, grupo, maximo, asignadas }) => ({
        salas,
        grupo,
        maximo,
        // Las que tiene la sesión ahora. Cambian sin recargar la pantalla
        // cuando el servidor confirma una asignación (recordarAsignadas).
        asignadas,

        get elegidas() {
            return (this.$wire.salasElegidas ?? []).map(Number);
        },

        get total() {
            return this.elegidas.reduce((suma, id) => suma + (this.sala(id)?.capacidad ?? 0), 0);
        },

        get completo() {
            return this.elegidas.length >= this.maximo;
        },

        get sinCambios() {
            return this.asignadas.length > 0
                && this.asignadas.length === this.elegidas.length
                && this.asignadas.every((id) => this.elegidas.includes(id));
        },

        sala(id) {
            return this.salas.find((sala) => sala.id === id) ?? null;
        },

        enLugar(posicion) {
            return this.sala(this.elegidas[posicion]);
        },

        elegida(id) {
            return this.elegidas.includes(id);
        },

        /** La sala en esa posición del salón: llena, y unida a las de al lado. */
        ranura(posicion) {
            const cuantas = this.elegidas.length;

            return {
                llena: posicion < cuantas,
                'unida-izquierda': posicion > 0 && posicion < cuantas,
                'unida-derecha': posicion < cuantas - 1,
            };
        },

        /** La cortina que sigue a esa posición: recogida entre dos salas, cerrada en el borde del salón. */
        cortina(posicion) {
            const cuantas = this.elegidas.length;

            return {
                recogida: posicion < cuantas - 1,
                cerrada: posicion === cuantas - 1 && cuantas < this.maximo,
            };
        },

        // El medidor tiene la escala del mayor de los dos: lo que cabe o el grupo.
        anchoOcupado() {
            if (this.total === 0) {
                return '0%';
            }

            return `${(Math.min(this.grupo, this.total) / Math.max(this.grupo, this.total)) * 100}%`;
        },

        anchoQueFalta() {
            if (this.total === 0 || this.total >= this.grupo) {
                return '0%';
            }

            return `${((this.grupo - this.total) / this.grupo) * 100}%`;
        },

        cabe() {
            return this.total > 0 && this.total >= this.grupo;
        },

        estado() {
            if (this.elegidas.length === 0) {
                return 'Elige la sala o las salas donde se monta.';
            }

            const faltan = this.grupo - this.total;

            if (faltan > 0) {
                const lugares = faltan === 1 ? 'Falta 1 lugar' : `Faltan ${faltan} lugares`;

                return this.completo
                    ? `${lugares} y ya son ${this.maximo} salas: cambia alguna por una más grande.`
                    : `${lugares}: une otra sala.`;
            }

            const sobran = -faltan;
            let texto = sobran === 0
                ? 'Cabe el grupo, justo.'
                : `Cabe el grupo y ${sobran === 1 ? 'sobra 1 lugar' : `sobran ${sobran} lugares`}.`;

            // Unir de más no está prohibido, pero conviene saberlo.
            const bastaba = this.elegidas.length > 1
                ? this.elegidas.map((id) => this.sala(id)).find((sala) => sala && sala.capacidad >= this.grupo)
                : null;

            if (bastaba) {
                texto += ` Con ${bastaba.nombre} ya bastaba.`;
            }

            return texto;
        },

        etiqueta() {
            const cuantas = this.elegidas.length;
            const verbo = this.asignadas.length > 0 ? 'Cambiar a' : 'Asignar';

            if (cuantas === 0) {
                return this.asignadas.length > 0 ? 'Cambiar' : 'Asignar';
            }

            if (cuantas === 1) {
                return this.asignadas.length > 0 ? 'Cambiar a esta sala' : 'Asignar la sala';
            }

            return `${verbo} ${cuantas} salas unidas`;
        },

        yaAsignadas() {
            return this.asignadas.length > 1
                ? 'Ya están asignadas y unidas. Para cambiarlas, marca otras.'
                : 'Ya está asignada. Para cambiarla, marca otra.';
        },

        /** El servidor confirmó una asignación (TableroDiario::asignarSalas). */
        recordarAsignadas(detalle, preparacion) {
            if (detalle.preparacion === preparacion) {
                this.asignadas = detalle.salas.map(Number);
            }
        },
    }));
});
