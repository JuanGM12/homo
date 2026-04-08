<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\EvaluacionesController;
use App\Core\Request;
use App\Core\Response;
use App\Database\Connection;
use App\Services\Auth;
use PDO;

final class HomeController
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $userId = (int) ($user['id'] ?? 0);
        $canSeeGlobal = Auth::canViewAllModuleRecords($user);

        $pdo = Connection::getPdo();

        $filterUserId = null;
        if ($canSeeGlobal) {
            $rawProf = trim((string) $request->input('profesional', ''));
            if ($rawProf !== '' && ctype_digit($rawProf)) {
                $candidate = (int) $rawProf;
                if ($this->userExistsActive($pdo, $candidate)) {
                    $filterUserId = $candidate;
                }
            }
        }

        $consolidatedGlobal = $canSeeGlobal && $filterUserId === null;
        $scopeIsGlobal = $consolidatedGlobal;

        $effectiveUserId = $filterUserId ?? $userId;
        $effectiveDocument = $consolidatedGlobal
            ? ''
            : ($filterUserId !== null
                ? $this->fetchUserDocument($pdo, $filterUserId)
                : (string) ($user['document_number'] ?? ''));

        $whereAoat = $consolidatedGlobal ? '' : ' WHERE user_id = :user_id';
        $whereEvaluaciones = $consolidatedGlobal ? '' : ' WHERE document_number = :document_number';
        $whereAsistencia = $consolidatedGlobal ? '' : ' WHERE advisor_user_id = :user_id';
        $wherePlan = $consolidatedGlobal ? '' : ' WHERE user_id = :user_id';
        $wherePic = $consolidatedGlobal ? '' : ' WHERE user_id = :user_id';

        $paramsUser = [':user_id' => $effectiveUserId];
        $paramsDoc = [':document_number' => $effectiveDocument];

        $aoatStates = $this->countAoatStates($pdo, $consolidatedGlobal, $effectiveUserId);

        $kpis = [
            'aoat_total' => $this->scalar(
                $pdo,
                'SELECT COUNT(*) FROM aoat_records' . $whereAoat,
                $whereAoat !== '' ? $paramsUser : []
            ),
            'aoat_aprobadas' => $aoatStates['Aprobada'],
            'aoat_asignadas' => $aoatStates['Asignada'],
            'aoat_devueltas' => $aoatStates['Devuelta'],
            'aoat_revisadas' => $aoatStates['Realizado'],
            'evaluaciones_total' => $this->scalar(
                $pdo,
                'SELECT COUNT(*) FROM test_responses' . $whereEvaluaciones,
                $whereEvaluaciones !== '' ? $paramsDoc : []
            ),
            'asistencias_actividades' => $this->scalar(
                $pdo,
                'SELECT COUNT(*) FROM asistencia_actividades' . $whereAsistencia,
                $whereAsistencia !== '' ? $paramsUser : []
            ),
            'asistentes_registrados' => $this->scalar(
                $pdo,
                'SELECT COUNT(*) FROM asistencia_asistentes aa
                 INNER JOIN asistencia_actividades ac ON ac.id = aa.actividad_id' . ($whereAsistencia === '' ? '' : ' WHERE ac.advisor_user_id = :user_id'),
                $whereAsistencia !== '' ? $paramsUser : []
            ),
            'planes_total' => $this->scalar(
                $pdo,
                'SELECT COUNT(*) FROM training_plans' . $wherePlan,
                $wherePlan !== '' ? $paramsUser : []
            ),
            'entrenamientos_total' => $this->scalar(
                $pdo,
                'SELECT COUNT(*) FROM entrenamiento_plans' . $wherePlan,
                $wherePlan !== '' ? $paramsUser : []
            ),
            'pic_total' => $this->scalar(
                $pdo,
                'SELECT COUNT(*) FROM pic_records' . $wherePic,
                $wherePic !== '' ? $paramsUser : []
            ),
        ];

        $aoatCompletionPct = $kpis['aoat_total'] > 0
            ? (int) round(($kpis['aoat_aprobadas'] / $kpis['aoat_total']) * 100)
            : 0;

        $evaluacionesPre = $this->scalar(
            $pdo,
            'SELECT COUNT(*) FROM test_responses' . ($whereEvaluaciones === '' ? ' WHERE phase = :phase' : $whereEvaluaciones . ' AND phase = :phase'),
            ($whereEvaluaciones !== '' ? $paramsDoc : []) + [':phase' => 'pre']
        );
        $evaluacionesPost = $this->scalar(
            $pdo,
            'SELECT COUNT(*) FROM test_responses' . ($whereEvaluaciones === '' ? ' WHERE phase = :phase' : $whereEvaluaciones . ' AND phase = :phase'),
            ($whereEvaluaciones !== '' ? $paramsDoc : []) + [':phase' => 'post']
        );

        $moduleMix = [
            ['label' => 'AoAT', 'value' => $kpis['aoat_total']],
            ['label' => 'Evaluaciones', 'value' => $kpis['evaluaciones_total']],
            ['label' => 'Asistencia', 'value' => $kpis['asistencias_actividades']],
            ['label' => 'PIC', 'value' => $kpis['pic_total']],
        ];

        $recentActivities = $this->recentActivities(
            $pdo,
            $consolidatedGlobal,
            $effectiveUserId,
            $effectiveDocument
        );

        $professionalOptions = [];
        if ($canSeeGlobal) {
            $stmt = $pdo->query('SELECT id, name FROM users WHERE active = 1 ORDER BY name ASC');
            $professionalOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $filterProfessionalName = '';
        if ($filterUserId !== null) {
            $stmt = $pdo->prepare('SELECT name FROM users WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $filterUserId]);
            $filterProfessionalName = (string) ($stmt->fetchColumn() ?: '');
        }

        return Response::view('home/index', [
            'pageTitle' => 'Equipo de Promoción y Prevención',
            'tests' => EvaluacionesController::getTestsListForUser($user),
            'dashboard' => [
                'scope_is_global' => $scopeIsGlobal,
                'can_filter_professional' => $canSeeGlobal,
                'filter_professional_id' => $filterUserId,
                'filter_professional_name' => $filterProfessionalName,
                'professional_options' => $professionalOptions,
                'kpis' => $kpis,
                'aoat_completion_pct' => $aoatCompletionPct,
                'evaluaciones_pre' => $evaluacionesPre,
                'evaluaciones_post' => $evaluacionesPost,
                'module_mix' => $moduleMix,
                'recent_activities' => $recentActivities,
            ],
        ]);
    }

    /**
     * @return array{Asignada: int, Devuelta: int, Realizado: int, Aprobada: int}
     */
    private function countAoatStates(PDO $pdo, bool $consolidatedGlobal, int $userIdForScope): array
    {
        $out = [
            'Asignada' => 0,
            'Devuelta' => 0,
            'Realizado' => 0,
            'Aprobada' => 0,
        ];
        $sql = 'SELECT state, COUNT(*) AS c FROM aoat_records';
        $params = [];
        if (!$consolidatedGlobal) {
            $sql .= ' WHERE user_id = :user_id';
            $params[':user_id'] = $userIdForScope;
        }
        $sql .= ' GROUP BY state';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $st = (string) ($row['state'] ?? '');
            if (array_key_exists($st, $out)) {
                $out[$st] = (int) ($row['c'] ?? 0);
            }
        }

        return $out;
    }

    private function fetchUserDocument(PDO $pdo, int $userId): string
    {
        $stmt = $pdo->prepare('SELECT document_number FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $col = $stmt->fetchColumn();

        return $col !== false ? trim((string) $col) : '';
    }

    private function userExistsActive(PDO $pdo, int $userId): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE id = :id AND active = 1 LIMIT 1');
        $stmt->execute([':id' => $userId]);

        return (bool) $stmt->fetchColumn();
    }

    private function scalar(PDO $pdo, string $sql, array $params = []): int
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function recentActivities(PDO $pdo, bool $global, int $userId, string $documentNumber): array
    {
        $items = [];

        $sqlAoat = 'SELECT created_at, "AoAT registrada" AS event, municipality, subregion
                    FROM aoat_records' . ($global ? '' : ' WHERE user_id = :user_id') . '
                    ORDER BY created_at DESC LIMIT 4';
        $stmtAoat = $pdo->prepare($sqlAoat);
        $stmtAoat->execute($global ? [] : [':user_id' => $userId]);
        foreach ($stmtAoat->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $items[] = [
                'date' => (string) ($row['created_at'] ?? ''),
                'event' => (string) ($row['event'] ?? ''),
                'place' => trim((string) (($row['municipality'] ?? '') . ' · ' . ($row['subregion'] ?? ''))),
            ];
        }

        $sqlTest = 'SELECT created_at, CONCAT("Test ", UPPER(phase), " · ", test_key) AS event, municipality, subregion
                    FROM test_responses' . ($global ? '' : ' WHERE document_number = :document_number') . '
                    ORDER BY created_at DESC LIMIT 4';
        $stmtTest = $pdo->prepare($sqlTest);
        $stmtTest->execute($global ? [] : [':document_number' => $documentNumber]);
        foreach ($stmtTest->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $items[] = [
                'date' => (string) ($row['created_at'] ?? ''),
                'event' => (string) ($row['event'] ?? ''),
                'place' => trim((string) (($row['municipality'] ?? '') . ' · ' . ($row['subregion'] ?? ''))),
            ];
        }

        usort($items, static function (array $a, array $b): int {
            return strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''));
        });

        return array_slice($items, 0, 6);
    }
}

