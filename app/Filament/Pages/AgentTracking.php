<?php

namespace App\Filament\Pages;

use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

/**
 * FLX-0054: vista mobile-first de seguimiento de agentes (Claude/Codex) y REQ. Resume, sin chat ni diffs, en
 * que esta trabajando cada agente y si hay algo esperando revision o permiso. Lee la BD de COORDINACION
 * (conexion 'coordination') en SOLO LECTURA; si no esta configurada, lo informa sin romper el panel.
 */
class AgentTracking extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $title = 'Seguimiento de agentes';

    protected static ?string $navigationLabel = 'Agentes';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.agent-tracking';

    /** Proyectos de este tablero (semaforo). */
    private const PROJECTS = ['FLX', 'VLS'];

    protected function getViewData(): array
    {
        if (! $this->coordinationConfigured()) {
            return ['configured' => false, 'agents' => [], 'permissions' => [], 'counters' => self::emptyCounters()];
        }

        try {
            $rows = DB::connection('coordination')
                ->table('vw_req_estado_operativo')
                ->whereIn('ProyectoCodigo', self::PROJECTS)
                ->whereNotIn('EstadoFuente', ['CERRADO', 'CANCELADO'])
                ->orderByRaw("FIELD(Responsable,'claude','codex')")
                ->orderBy('Numero')
                ->get();
        } catch (\Throwable $e) {
            return ['configured' => false, 'error' => $e->getMessage(), 'agents' => [], 'permissions' => [], 'counters' => self::emptyCounters()];
        }

        $counters = self::emptyCounters();
        $byAgent = [];
        $permissions = [];

        foreach ($rows as $r) {
            $op = $this->operational($r);
            $counters['activos']++;
            if ($r->Responsable === 'claude') {
                $counters['claude']++;
            } elseif ($r->Responsable === 'codex') {
                $counters['codex']++;
            }
            if ($op['key'] === 'bloqueado') {
                $counters['bloqueados']++;
            }
            if ($op['key'] === 'esperando_permiso') {
                $counters['esperando_permiso']++;
                $permissions[] = [
                    'req' => $r->ReqCodigo,
                    'project' => $r->ProyectoCodigo,
                    'title' => $r->Titulo,
                    'reason' => $r->MotivoBloqueo ?: 'Requiere autorizacion del usuario',
                    'updated' => $this->relative($r->ActualizadoEn),
                ];
            }

            $agent = $r->Responsable ?: 'sin asignar';
            $byAgent[$agent][] = [
                'req' => $r->ReqCodigo,
                'project' => $r->ProyectoCodigo,
                'title' => $r->Titulo,
                'state_key' => $op['key'],
                'state_label' => $op['label'],
                'state_color' => $op['color'],
                'next' => $op['next'],
                'blocked_by' => $r->MotivoBloqueo,
                'updated' => $this->relative($r->ActualizadoEn),
            ];
        }

        $agents = [];
        foreach (['claude', 'codex'] as $code) {
            if (! empty($byAgent[$code])) {
                $agents[] = ['code' => $code, 'name' => ucfirst($code), 'reqs' => $byAgent[$code]];
                unset($byAgent[$code]);
            }
        }
        foreach ($byAgent as $code => $reqs) {
            $agents[] = ['code' => $code, 'name' => ucfirst($code), 'reqs' => $reqs];
        }

        return ['configured' => true, 'agents' => $agents, 'permissions' => $permissions, 'counters' => $counters];
    }

    /** Deriva estado operativo + color + proxima accion a partir del estado del REQ. */
    private function operational(object $r): array
    {
        $op = (string) $r->EstadoOperativo;
        $src = (string) $r->EstadoFuente;

        // Codex R1: los estados de USUARIO tienen PRIORIDAD sobre el bloque generico BLOQUEADO_*. La vista deja
        // EstadoOperativo = EstadoFuente salvo bloqueos por prioridad, asi que BLOQUEADO_POR_USUARIO entraria por
        // el BLOQUEADO generico y quedaria como 'bloqueado' en vez de 'esperando_permiso'. Evaluarlos primero.
        if (in_array($src, ['ESPERA_USUARIO', 'BLOQUEADO_POR_USUARIO'], true) || $op === 'BLOQUEADO_POR_USUARIO') {
            return ['key' => 'esperando_permiso', 'label' => 'Esperando permiso', 'color' => 'warning', 'next' => 'Requiere tu OK'];
        }

        // Otros bloqueos (prioridad / por REQ, no de usuario) -> bloqueado.
        if (str_starts_with($op, 'BLOQUEADO')) {
            return ['key' => 'bloqueado', 'label' => 'Bloqueado', 'color' => 'gray', 'next' => $r->MotivoBloqueo ?: 'Espera desbloqueo'];
        }

        return match ($src) {
            'LISTO_PARA_REVISION' => ['key' => 'esperando_revision', 'label' => 'Esperando revisión', 'color' => 'info', 'next' => 'Codex audita'],
            'REQUIERE_CAMBIOS' => ['key' => 'en_proceso', 'label' => 'Requiere cambios', 'color' => 'danger', 'next' => 'Claude corrige'],
            'APROBADO_POR_CODEX' => ['key' => 'cerrado', 'label' => 'Aprobado', 'color' => 'success', 'next' => '—'],
            'NUEVO', 'EN_ANALISIS', 'EN_DESARROLLO', 'PRECHECK_FAIL' => ['key' => 'en_proceso', 'label' => 'En proceso', 'color' => 'primary', 'next' => $r->Responsable === 'codex' ? 'Codex trabaja' : 'Claude desarrolla'],
            default => ['key' => 'otro', 'label' => $src, 'color' => 'gray', 'next' => '—'],
        };
    }

    private function relative($ts): string
    {
        if (! $ts) {
            return '—';
        }

        return Carbon::parse($ts)->diffForHumans();
    }

    private function coordinationConfigured(): bool
    {
        return filled(config('database.connections.coordination.database'))
            && filled(config('database.connections.coordination.username'));
    }

    private static function emptyCounters(): array
    {
        return ['activos' => 0, 'claude' => 0, 'codex' => 0, 'bloqueados' => 0, 'esperando_permiso' => 0];
    }
}
