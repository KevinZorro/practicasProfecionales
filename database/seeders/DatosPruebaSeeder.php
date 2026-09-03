<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EstadoConsentimiento;
use App\Enums\EstadoEvaluacion;
use App\Enums\EstadoItemInventario;
use App\Enums\EstadoPreparacion;
use App\Enums\EstadoSolicitud;
use App\Enums\ModalidadTaller;
use App\Enums\NivelFidelidad;
use App\Enums\OrigenUsuario;
use App\Enums\ResultadoEvaluacion;
use App\Enums\Rol;
use App\Enums\TipoEvento;
use App\Enums\TipoItemInventario;
use App\Enums\TipoSesion;
use App\Models\Capacidad;
use App\Models\CasoClinico;
use App\Models\Certificacion;
use App\Models\ConfiguracionLanding;
use App\Models\ConsentimientoEstudiante;
use App\Models\ConsentimientoPlantilla;
use App\Models\EstadisticaLanding;
use App\Models\Evaluacion;
use App\Models\EvaluacionEstudiante;
use App\Models\Evento;
use App\Models\GaleriaFoto;
use App\Models\ItemChecklist;
use App\Models\ItemInventario;
use App\Models\Materia;
use App\Models\PerfilDocente;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\SolicitudInformacion;
use App\Models\Taller;
use App\Models\TipoEvaluacion;
use App\Models\TituloDocente;
use App\Models\User;
use App\Models\VideoInstitucional;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de prueba de un laboratorio de simulación clínica: estructura
 * académica, inventario, usuarios de los cinco roles y un flujo completo de
 * solicitud → preparación → evaluación.
 *
 * Depende de RolSeeder: los roles deben existir antes de asignarlos.
 */
class DatosPruebaSeeder extends Seeder
{
    private const PERIODO_ACADEMICO = '2026-2';

    public function run(): void
    {
        DB::transaction(function (): void {
            $salas = $this->crearSalas();
            $materias = $this->crearMaterias();
            $capacidades = $this->crearCapacidades();
            $inventario = $this->crearInventario();
            $casos = $this->crearCasosClinicos($materias, $capacidades, $inventario);
            $tipos = $this->crearTiposEvaluacion($materias);
            $usuarios = $this->crearUsuarios();

            $this->crearConsentimientos($usuarios['admin'], $usuarios['estudiantes']);
            $this->crearSolicitudes($usuarios, $materias, $casos, $inventario, $salas);
            $this->crearEvaluacion($usuarios, $tipos);
            $this->crearContenidoPublico($usuarios['coordinadora']);
        });
    }

    /** @return Collection<string, Sala> */
    private function crearSalas(): Collection
    {
        $definicion = [
            'SIM-01' => ['Sala de partos', 12],
            'SIM-02' => ['Sala de urgencias', 20],
            'SIM-03' => ['Sala de cuidado neonatal', 10],
            'SIM-04' => ['Sala de hospitalización', 24],
            'SIM-05' => ['Consultorio de semiología', 8],
        ];

        return collect($definicion)->map(fn (array $datos, string $codigo): Sala => Sala::create([
            'nombre' => $datos[0],
            'codigo' => $codigo,
            'capacidad' => $datos[1],
            'activo' => true,
        ]));
    }

    /** @return Collection<string, Materia> */
    private function crearMaterias(): Collection
    {
        $definicion = [
            'ENF-201' => ['Cuidado Materno Perinatal', 4],
            'ENF-305' => ['Urgencias y Trauma', 6],
            'ENF-210' => ['Enfermería del Adulto', 5],
            'ENF-402' => ['Cuidado Crítico Neonatal', 8],
            'MED-105' => ['Semiología', 2],
            'MED-220' => ['Farmacología Aplicada', 4],
        ];

        return collect($definicion)->map(fn (array $datos, string $codigo): Materia => Materia::create([
            'codigo' => $codigo,
            'nombre' => $datos[0],
            'semestre' => $datos[1],
            'activo' => true,
        ]));
    }

    /** @return Collection<string, Capacidad> */
    private function crearCapacidades(): Collection
    {
        $definicion = [
            'Sangrado' => 'heroicon-o-beaker',
            'Llanto' => 'heroicon-o-speaker-wave',
            'Signos vitales' => 'heroicon-o-heart',
            'Convulsión' => 'heroicon-o-bolt',
            'Vía aérea' => 'heroicon-o-cloud',
            'Pulso palpable' => 'heroicon-o-finger-print',
            'Auscultación' => 'heroicon-o-microphone',
        ];

        return collect($definicion)->map(fn (string $icono, string $nombre): Capacidad => Capacidad::create([
            'nombre' => $nombre,
            'icono' => $icono,
        ]));
    }

    /** @return Collection<string, ItemInventario> */
    private function crearInventario(): Collection
    {
        $definicion = [
            // nombre => [tipo, nivel de fidelidad, cantidad, estado]
            'Maniquí de parto' => [TipoItemInventario::Simulador, NivelFidelidad::Alta, 2, EstadoItemInventario::Disponible],
            'Simulador neonatal' => [TipoItemInventario::Simulador, NivelFidelidad::Alta, 2, EstadoItemInventario::Disponible],
            'Simulador de trauma adulto' => [TipoItemInventario::Simulador, NivelFidelidad::Media, 3, EstadoItemInventario::Disponible],
            'Torso de RCP' => [TipoItemInventario::Simulador, NivelFidelidad::Baja, 6, EstadoItemInventario::Disponible],
            'Brazo de punción venosa' => [TipoItemInventario::Simulador, NivelFidelidad::Baja, 8, EstadoItemInventario::Disponible],
            'Simulador de auscultación' => [TipoItemInventario::Simulador, NivelFidelidad::Media, 1, EstadoItemInventario::Mantenimiento],
            'Incubadora neonatal' => [TipoItemInventario::EquipoClinico, null, 2, EstadoItemInventario::Disponible],
            'Monitor de signos vitales' => [TipoItemInventario::EquipoClinico, null, 6, EstadoItemInventario::Disponible],
            'Bomba de infusión' => [TipoItemInventario::EquipoClinico, null, 8, EstadoItemInventario::Disponible],
            'Desfibrilador de entrenamiento' => [TipoItemInventario::EquipoClinico, null, 3, EstadoItemInventario::Disponible],
            'Tensiómetro' => [TipoItemInventario::EquipoClinico, null, 15, EstadoItemInventario::Disponible],
            'Fonendoscopio' => [TipoItemInventario::EquipoClinico, null, 20, EstadoItemInventario::Disponible],
            'Camilla de traslado' => [TipoItemInventario::EquipoClinico, null, 4, EstadoItemInventario::Disponible],
            'Guantes de nitrilo' => [TipoItemInventario::EquipoBasico, null, 500, EstadoItemInventario::Disponible],
            'Gasas estériles' => [TipoItemInventario::EquipoBasico, null, 400, EstadoItemInventario::Disponible],
            'Jeringas 10 ml' => [TipoItemInventario::EquipoBasico, null, 300, EstadoItemInventario::Disponible],
            'Set de curación' => [TipoItemInventario::EquipoBasico, null, 120, EstadoItemInventario::Disponible],
        ];

        return collect($definicion)->map(fn (array $datos, string $nombre): ItemInventario => ItemInventario::create([
            'nombre' => $nombre,
            'tipo' => $datos[0],
            'nivel_fidelidad' => $datos[1],
            'cantidad_total' => $datos[2],
            'descripcion' => null,
            'estado' => $datos[3],
            'activo' => true,
        ]));
    }

    /**
     * @param  Collection<string, Materia>  $materias
     * @param  Collection<string, Capacidad>  $capacidades
     * @param  Collection<string, ItemInventario>  $inventario
     * @return Collection<string, CasoClinico>
     */
    private function crearCasosClinicos(Collection $materias, Collection $capacidades, Collection $inventario): Collection
    {
        $definicion = [
            'Atención de parto normal' => [
                'descripcion' => 'Atención del trabajo de parto y del recién nacido inmediato, con acompañamiento de la madre.',
                'materias' => ['ENF-201'],
                'capacidades' => ['Sangrado', 'Llanto', 'Signos vitales'],
                'items' => ['Maniquí de parto' => 1, 'Monitor de signos vitales' => 1, 'Guantes de nitrilo' => 20, 'Gasas estériles' => 15],
            ],
            'Herida por arma de fuego' => [
                'descripcion' => 'Atención inicial del trauma penetrante: control de hemorragia, vía aérea y traslado.',
                'materias' => ['ENF-305'],
                'capacidades' => ['Sangrado', 'Vía aérea', 'Pulso palpable'],
                'items' => ['Simulador de trauma adulto' => 1, 'Camilla de traslado' => 1, 'Set de curación' => 4, 'Guantes de nitrilo' => 20],
            ],
            'Reanimación neonatal en incubadora' => [
                'descripcion' => 'Estabilización del recién nacido prematuro dentro de la incubadora.',
                'materias' => ['ENF-402', 'ENF-201'],
                'capacidades' => ['Llanto', 'Vía aérea', 'Signos vitales'],
                'items' => ['Simulador neonatal' => 1, 'Incubadora neonatal' => 1, 'Monitor de signos vitales' => 1],
            ],
            'Paro cardiorrespiratorio en adulto' => [
                'descripcion' => 'Reanimación cardiopulmonar básica y avanzada con desfibrilación temprana.',
                'materias' => ['ENF-305', 'ENF-210'],
                'capacidades' => ['Pulso palpable', 'Vía aérea'],
                'items' => ['Torso de RCP' => 2, 'Desfibrilador de entrenamiento' => 1, 'Guantes de nitrilo' => 10],
            ],
            'Crisis convulsiva' => [
                'descripcion' => 'Manejo de la crisis convulsiva y protección del paciente durante el episodio.',
                'materias' => ['ENF-210'],
                'capacidades' => ['Convulsión', 'Signos vitales'],
                'items' => ['Simulador de trauma adulto' => 1, 'Monitor de signos vitales' => 1, 'Bomba de infusión' => 1],
            ],
            'Valoración cardiopulmonar' => [
                'descripcion' => 'Examen físico completo del tórax con auscultación cardiaca y pulmonar.',
                'materias' => ['MED-105'],
                'capacidades' => ['Auscultación', 'Signos vitales'],
                'items' => ['Fonendoscopio' => 4, 'Tensiómetro' => 4],
            ],
        ];

        $orden = 1;

        return collect($definicion)->map(function (array $datos, string $nombre) use ($materias, $capacidades, $inventario, &$orden): CasoClinico {
            $caso = CasoClinico::create([
                'nombre' => $nombre,
                'descripcion' => $datos['descripcion'],
                'imagen' => null,
                'visible_publico' => true,
                'orden' => $orden++,
                'activo' => true,
            ]);

            $caso->materias()->attach(
                collect($datos['materias'])->map(fn (string $codigo): int => $materias[$codigo]->id)->all()
            );

            $caso->capacidades()->attach(
                collect($datos['capacidades'])->map(fn (string $nombreCapacidad): int => $capacidades[$nombreCapacidad]->id)->all()
            );

            foreach ($datos['items'] as $nombreItem => $cantidad) {
                $caso->items()->attach($inventario[$nombreItem]->id, ['cantidad' => $cantidad]);
            }

            return $caso;
        });
    }

    /**
     * @param  Collection<string, Materia>  $materias
     * @return Collection<string, TipoEvaluacion>
     */
    private function crearTiposEvaluacion(Collection $materias): Collection
    {
        $definicion = [
            'Canalización de vía periférica' => [
                'materias' => ['ENF-210', 'MED-220'],
                'items' => [
                    'Verifica la identidad del paciente y explica el procedimiento',
                    'Realiza higiene de manos antes del procedimiento',
                    'Selecciona el calibre adecuado del catéter',
                    'Aplica torniquete y localiza la vena por palpación',
                    'Realiza asepsia de la zona de punción',
                    'Inserta el catéter con técnica aséptica',
                    'Fija el catéter y rotula con fecha y hora',
                    'Descarta el material cortopunzante en el guardián',
                    'Registra el procedimiento en la historia clínica',
                ],
            ],
            'Reanimación cardiopulmonar básica' => [
                'materias' => ['ENF-305', 'ENF-210'],
                'items' => [
                    'Verifica la seguridad de la escena',
                    'Comprueba respuesta y respiración en menos de 10 segundos',
                    'Activa el sistema de emergencias',
                    'Ubica correctamente las manos en el centro del tórax',
                    'Comprime a una frecuencia de 100 a 120 por minuto',
                    'Permite la reexpansión completa del tórax',
                    'Mantiene una relación de 30 compresiones por 2 ventilaciones',
                    'Minimiza las interrupciones de las compresiones',
                ],
            ],
            'Atención del parto' => [
                'materias' => ['ENF-201'],
                'items' => [
                    'Prepara el equipo estéril antes del nacimiento',
                    'Acompaña y explica a la gestante durante el proceso',
                    'Protege el periné durante la salida de la cabeza',
                    'Realiza pinzamiento oportuno del cordón',
                    'Seca y estimula al recién nacido',
                    'Favorece el contacto piel a piel',
                    'Vigila el sangrado en el posparto inmediato',
                ],
            ],
            'Lavado de manos quirúrgico' => [
                'materias' => ['MED-105'],
                'items' => [
                    'Retira joyas y accesorios de manos y antebrazos',
                    'Regula la temperatura del agua antes de iniciar',
                    'Cubre todas las superficies de manos y antebrazos',
                    'Respeta el tiempo mínimo de fricción',
                    'Mantiene las manos por encima de los codos',
                    'Se seca con compresa estéril sin recontaminar',
                ],
            ],
            'Toma de signos vitales' => [
                'materias' => ['MED-105', 'ENF-210'],
                'items' => [
                    'Realiza higiene de manos',
                    'Selecciona el brazalete del tamaño adecuado',
                    'Toma la presión arterial con la técnica correcta',
                    'Cuenta la frecuencia respiratoria durante un minuto',
                    'Registra los valores obtenidos',
                    'Informa hallazgos anormales al docente',
                ],
            ],
        ];

        return collect($definicion)->map(function (array $datos, string $nombre) use ($materias): TipoEvaluacion {
            $tipo = TipoEvaluacion::create([
                'nombre' => $nombre,
                'descripcion' => 'Checklist institucional para '.mb_strtolower($nombre).'.',
                'activo' => true,
            ]);

            $tipo->materias()->attach(
                collect($datos['materias'])->map(fn (string $codigo): int => $materias[$codigo]->id)->all()
            );

            foreach (array_values($datos['items']) as $indice => $descripcion) {
                ItemChecklist::create([
                    'tipo_evaluacion_id' => $tipo->id,
                    'descripcion' => $descripcion,
                    'orden' => $indice + 1,
                ]);
            }

            return $tipo;
        });
    }

    /**
     * Un usuario puede tener varios roles: la coordinadora es además docente.
     *
     * @return array{admin: User, coordinadora: User, administrativos: Collection<int, User>, docentes: Collection<int, User>, estudiantes: Collection<int, User>}
     */
    private function crearUsuarios(): array
    {
        $admin = $this->crearUsuario('Sandra Milena Ospina', 'admin@ejemplo.edu.co', OrigenUsuario::Contratado, 'ADM-001');
        $admin->assignRole(Rol::Admin->value);

        $coordinadora = $this->crearUsuario('Claudia Patricia Ríos', 'coordinacion@ejemplo.edu.co', OrigenUsuario::Contratado, 'COO-001');
        $coordinadora->assignRole([Rol::Coordinador->value, Rol::Docente->value]);

        $administrativos = collect([
            ['Jorge Andrés Peña', 'jorge.pena@ejemplo.edu.co', 'ADT-001'],
            ['Marcela Lozano', 'marcela.lozano@ejemplo.edu.co', 'ADT-002'],
        ])->map(function (array $datos): User {
            $usuario = $this->crearUsuario($datos[0], $datos[1], OrigenUsuario::Contratado, $datos[2]);
            $usuario->assignRole(Rol::Administrativo->value);

            return $usuario;
        });

        $docentes = collect([
            ['Luis Fernando Guzmán', 'luis.guzman@ejemplo.edu.co', 'DOC-001'],
            ['Ana María Céspedes', 'ana.cespedes@ejemplo.edu.co', 'DOC-002'],
            ['Óscar Iván Tovar', 'oscar.tovar@ejemplo.edu.co', 'DOC-003'],
            ['Diana Carolina Mejía', 'diana.mejia@ejemplo.edu.co', 'DOC-004'],
        ])->map(function (array $datos): User {
            $usuario = $this->crearUsuario($datos[0], $datos[1], OrigenUsuario::Contratado, $datos[2]);
            $usuario->assignRole(Rol::Docente->value);

            return $usuario;
        });

        $estudiantes = User::factory()
            ->count(24)
            ->state(fn (): array => ['origen' => OrigenUsuario::Matriculado])
            ->create()
            ->each(fn (User $usuario) => $usuario->assignRole(Rol::Estudiante->value));

        return [
            'admin' => $admin,
            'coordinadora' => $coordinadora,
            'administrativos' => $administrativos,
            'docentes' => $docentes,
            'estudiantes' => $estudiantes,
        ];
    }

    private function crearUsuario(string $nombre, string $email, OrigenUsuario $origen, string $codigo): User
    {
        return User::create([
            'google_id' => null,
            'nombre' => $nombre,
            'email' => $email,
            'documento' => (string) random_int(1000000000, 1099999999),
            'codigo_institucional' => $codigo,
            'origen' => $origen,
            'ultima_sincronizacion' => now(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
    }

    /** @param Collection<int, User> $estudiantes */
    private function crearConsentimientos(User $admin, Collection $estudiantes): void
    {
        $plantilla = ConsentimientoPlantilla::create([
            'nombre' => 'Consentimiento informado de prácticas de simulación',
            'archivo_path' => 'consentimientos/plantillas/consentimiento-2026-2-v2.pdf',
            'version' => '2.0',
            'activo' => true,
            'subido_por' => $admin->id,
        ]);

        // Un consentimiento por estudiante y periodo: el índice único lo exige.
        foreach ($estudiantes as $indice => $estudiante) {
            $estado = match ($indice % 3) {
                0 => EstadoConsentimiento::Verificado,
                1 => EstadoConsentimiento::Cargado,
                default => EstadoConsentimiento::Pendiente,
            };

            ConsentimientoEstudiante::create([
                'estudiante_id' => $estudiante->id,
                'plantilla_id' => $plantilla->id,
                'periodo_academico' => self::PERIODO_ACADEMICO,
                'archivo_firmado_path' => $estado === EstadoConsentimiento::Pendiente
                    ? null
                    : 'consentimientos/firmados/'.$estudiante->id.'-2026-2.pdf',
                'estado' => $estado,
                'verificado_por' => $estado === EstadoConsentimiento::Verificado ? $admin->id : null,
                'verificado_at' => $estado === EstadoConsentimiento::Verificado ? now() : null,
            ]);
        }
    }

    /**
     * @param  array{admin: User, coordinadora: User, administrativos: Collection<int, User>, docentes: Collection<int, User>, estudiantes: Collection<int, User>}  $usuarios
     * @param  Collection<string, Materia>  $materias
     * @param  Collection<string, CasoClinico>  $casos
     * @param  Collection<string, ItemInventario>  $inventario
     * @param  Collection<string, Sala>  $salas
     */
    private function crearSolicitudes(array $usuarios, Collection $materias, Collection $casos, Collection $inventario, Collection $salas): void
    {
        $definicion = [
            // Pendiente: aún no la revisa el administrativo.
            [
                'docente' => 0,
                'materia' => 'ENF-201',
                'caso' => 'Atención de parto normal',
                'tipo' => TipoSesion::Practica,
                'fecha' => now()->addDays(4),
                'hora' => '07:00',
                'estudiantes' => 12,
                'estado' => EstadoSolicitud::Pendiente,
            ],
            // Revisada: el administrativo ya la pasó a coordinación.
            [
                'docente' => 1,
                'materia' => 'ENF-305',
                'caso' => 'Herida por arma de fuego',
                'tipo' => TipoSesion::Practica,
                'fecha' => now()->addDays(6),
                'hora' => '09:00',
                'estudiantes' => 18,
                'estado' => EstadoSolicitud::Revisada,
            ],
            // Aprobada sin sala: la preparación existe pero falta asignar sala.
            [
                'docente' => 2,
                'materia' => 'ENF-402',
                'caso' => 'Reanimación neonatal en incubadora',
                'tipo' => TipoSesion::Practica,
                'fecha' => now()->addDays(2),
                'hora' => '14:00',
                'estudiantes' => 8,
                'estado' => EstadoSolicitud::Aprobada,
                'preparacion' => 'sin_sala',
            ],
            // Aprobada y ya montada.
            [
                'docente' => 3,
                'materia' => 'ENF-210',
                'caso' => 'Paro cardiorrespiratorio en adulto',
                'tipo' => TipoSesion::Practica,
                'fecha' => now()->subDays(3),
                'hora' => '10:00',
                'estudiantes' => 20,
                'estado' => EstadoSolicitud::Aprobada,
                'preparacion' => 'preparada',
                'sala' => 'SIM-02',
            ],
            // Rechazada por falta de simulador.
            [
                'docente' => 0,
                'materia' => 'ENF-210',
                'caso' => 'Crisis convulsiva',
                'tipo' => TipoSesion::Practica,
                'fecha' => now()->addDays(9),
                'hora' => '16:00',
                'estudiantes' => 15,
                'estado' => EstadoSolicitud::Rechazada,
            ],
            // Evaluación aprobada y montada: es la que soporta la evaluación
            // registrada más abajo.
            [
                'docente' => 1,
                'materia' => 'MED-105',
                'caso' => 'Valoración cardiopulmonar',
                'tipo' => TipoSesion::Evaluacion,
                'fecha' => now()->subDays(7),
                'hora' => '08:00',
                'estudiantes' => 6,
                'estado' => EstadoSolicitud::Aprobada,
                'preparacion' => 'preparada',
                'sala' => 'SIM-05',
            ],
        ];

        $administrativo = $usuarios['administrativos']->first();

        foreach ($definicion as $datos) {
            $docente = $usuarios['docentes'][$datos['docente']];
            $caso = $casos[$datos['caso']];
            $revisada = $datos['estado'] !== EstadoSolicitud::Pendiente;
            $resuelta = in_array($datos['estado'], [EstadoSolicitud::Aprobada, EstadoSolicitud::Rechazada], true);

            $solicitud = Solicitud::create([
                'docente_id' => $docente->id,
                'materia_id' => $materias[$datos['materia']]->id,
                'caso_clinico_id' => $caso->id,
                'tipo' => $datos['tipo'],
                'fecha' => $datos['fecha']->format('Y-m-d'),
                'hora_inicio' => $datos['hora'].':00',
                'hora_fin' => sprintf('%02d:00:00', ((int) substr($datos['hora'], 0, 2)) + 2),
                'cantidad_estudiantes' => $datos['estudiantes'],
                'estado' => $datos['estado'],
                'revisada_por' => $revisada ? $administrativo->id : null,
                'revisada_at' => $revisada ? $datos['fecha']->copy()->subDays(3) : null,
                'resuelta_por' => $resuelta ? $usuarios['coordinadora']->id : null,
                'resuelta_at' => $resuelta ? $datos['fecha']->copy()->subDays(2) : null,
                'motivo_rechazo' => $datos['estado'] === EstadoSolicitud::Rechazada
                    ? 'El simulador de auscultación está en mantenimiento en esa fecha.'
                    : null,
            ]);

            // El inventario del caso clínico se precarga en la solicitud.
            foreach ($caso->items as $item) {
                $solicitud->items()->attach($item->id, ['cantidad' => $item->pivot->cantidad]);
            }

            if (! isset($datos['preparacion'])) {
                continue;
            }

            $preparada = $datos['preparacion'] === 'preparada';

            $preparacion = Preparacion::create([
                'solicitud_id' => $solicitud->id,
                'sala_id' => isset($datos['sala']) ? $salas[$datos['sala']]->id : null,
                'estado' => $preparada ? EstadoPreparacion::Preparado : EstadoPreparacion::Pendiente,
                'preparado_por' => $preparada ? $administrativo->id : null,
                'preparado_at' => $preparada ? $datos['fecha']->copy()->subHours(2) : null,
                'observaciones' => $preparada ? null : 'Pendiente de asignar sala.',
            ]);

            foreach ($solicitud->items as $item) {
                $preparacion->items()->attach($item->id, [
                    'cantidad' => $item->pivot->cantidad,
                    'alistado' => $preparada,
                ]);
            }
        }
    }

    /**
     * Evaluación completa sobre la solicitud de tipo evaluación ya aprobada,
     * con el checklist copiado y seis estudiantes calificados.
     *
     * @param  array{admin: User, coordinadora: User, administrativos: Collection<int, User>, docentes: Collection<int, User>, estudiantes: Collection<int, User>}  $usuarios
     * @param  Collection<string, TipoEvaluacion>  $tipos
     */
    private function crearEvaluacion(array $usuarios, Collection $tipos): void
    {
        $solicitud = Solicitud::query()
            ->deEvaluacion()
            ->aprobadas()
            ->orderBy('id')
            ->firstOrFail();

        $tipo = $tipos['Toma de signos vitales'];

        $evaluacion = Evaluacion::create([
            'solicitud_id' => $solicitud->id,
            'tipo_evaluacion_id' => $tipo->id,
            'docente_id' => $solicitud->docente_id,
            'estado' => EstadoEvaluacion::Finalizada,
        ]);

        // Los ítems se copian, no se referencian: la plantilla puede cambiar
        // después y esta evaluación conserva con qué se evaluó.
        $itemsCopiados = $tipo->itemsChecklist->map(fn (ItemChecklist $plantilla) => $evaluacion->items()->create([
            'descripcion' => $plantilla->descripcion,
            'orden' => $plantilla->orden,
        ]));

        foreach ($usuarios['estudiantes']->take(6) as $indice => $estudiante) {
            // El resultado lo decide el docente: no se deriva de los ítems.
            $aprobado = $indice !== 4;

            $registro = EvaluacionEstudiante::create([
                'evaluacion_id' => $evaluacion->id,
                'estudiante_id' => $estudiante->id,
                'resultado' => $aprobado ? ResultadoEvaluacion::Aprobado : ResultadoEvaluacion::NoAprobado,
                'intento' => $aprobado ? 1 : 2,
                'observaciones' => $aprobado
                    ? null
                    : 'Debe repasar la técnica de medición de la frecuencia respiratoria.',
            ]);

            foreach ($itemsCopiados as $posicion => $item) {
                $registro->items()->attach($item->id, [
                    'cumplido' => $aprobado || $posicion < 3,
                ]);
            }
        }
    }

    private function crearContenidoPublico(User $coordinadora): void
    {
        $configuracion = [
            'hero_video_url' => 'https://www.youtube.com/watch?v=simulacion-lab',
            'hero_titulo' => 'Laboratorio de Simulación Clínica',
            'hero_subtitulo' => 'Formación práctica en entornos clínicos seguros y controlados.',
            'contacto_email' => 'laboratorio@ejemplo.edu.co',
            'contacto_telefono' => '+57 601 000 0000',
            'contacto_direccion' => 'Facultad de Ciencias de la Salud, bloque C, piso 2',
        ];

        foreach ($configuracion as $clave => $valor) {
            ConfiguracionLanding::create(['clave' => $clave, 'valor' => $valor]);
        }

        $estadisticas = [
            ['Salas de simulación', '5'],
            ['Simuladores disponibles', '22'],
            ['Estudiantes por semestre', '1.850'],
            ['Casos clínicos', '6'],
        ];

        foreach ($estadisticas as $orden => $datos) {
            EstadisticaLanding::create([
                'etiqueta' => $datos[0],
                'valor' => $datos[1],
                'orden' => $orden + 1,
                'activo' => true,
            ]);
        }

        foreach (['Sala de partos', 'Práctica de urgencias', 'Cuidado neonatal', 'Taller de RCP'] as $orden => $titulo) {
            GaleriaFoto::create([
                'titulo' => $titulo,
                'imagen_path' => 'landing/galeria/'.($orden + 1).'.jpg',
                'orden' => $orden + 1,
                'activo' => true,
            ]);
        }

        VideoInstitucional::create([
            'titulo' => 'Recorrido por el laboratorio',
            'url' => 'https://www.youtube.com/watch?v=recorrido-lab',
            'orden' => 1,
            'activo' => true,
        ]);

        $talleres = [
            ['Taller de reanimación cardiopulmonar', 'Urgencias', ModalidadTaller::Presencial],
            ['Taller de atención inicial del trauma', 'Urgencias', ModalidadTaller::Mixta],
            ['Taller de lactancia y cuidado neonatal', 'Materno perinatal', ModalidadTaller::Virtual],
        ];

        foreach ($talleres as $orden => $datos) {
            $taller = Taller::create([
                'titulo' => $datos[0],
                'descripcion' => 'Actividad de extensión abierta a estudiantes y profesionales del área de la salud.',
                'imagen' => null,
                'tema' => $datos[1],
                'fecha' => now()->addMonths($orden + 1)->format('Y-m-d'),
                'modalidad' => $datos[2],
                'muestra_formulario' => true,
                'orden' => $orden + 1,
                'activo' => true,
            ]);

            SolicitudInformacion::factory()->count(2)->create(['taller_id' => $taller->id]);
        }

        $eventos = [
            ['Jornada de simulación clínica', TipoEvento::Jornada],
            ['Congreso regional de enfermería', TipoEvento::Congreso],
            ['Seminario de seguridad del paciente', TipoEvento::Seminario],
        ];

        foreach ($eventos as $orden => $datos) {
            Evento::create([
                'titulo' => $datos[0],
                'descripcion' => 'Espacio académico organizado por la Facultad de Ciencias de la Salud.',
                'imagen' => null,
                'fecha' => now()->addMonths($orden + 2)->format('Y-m-d'),
                'tipo' => $datos[1],
                'abierto_publico' => true,
                'orden' => $orden + 1,
                'activo' => true,
            ]);
        }

        $certificaciones = [
            ['Centro de entrenamiento certificado', 'American Heart Association'],
            ['Acreditación en simulación clínica', 'Society for Simulation in Healthcare'],
        ];

        foreach ($certificaciones as $orden => $datos) {
            Certificacion::create([
                'nombre' => $datos[0],
                'entidad' => $datos[1],
                'imagen_insignia' => null,
                'descripcion' => 'Reconocimiento vigente al proceso formativo del laboratorio.',
                'orden' => $orden + 1,
                'activo' => true,
            ]);
        }

        $perfil = PerfilDocente::create([
            'user_id' => $coordinadora->id,
            'nombre' => $coordinadora->nombre,
            'cargo' => 'Coordinadora del laboratorio',
            'foto' => null,
            'orden' => 1,
            'activo' => true,
        ]);

        $titulos = [
            ['Enfermera profesional', 'Universidad Nacional de Colombia'],
            ['Especialista en cuidado crítico', 'Universidad de Antioquia'],
            ['Magíster en educación para la salud', 'Universidad del Valle'],
        ];

        foreach ($titulos as $orden => $datos) {
            TituloDocente::create([
                'perfil_docente_id' => $perfil->id,
                'titulo' => $datos[0],
                'institucion' => $datos[1],
                'orden' => $orden + 1,
            ]);
        }
    }
}
