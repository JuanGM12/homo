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
        $roles = $user['roles'] ?? [];
        $canSeeGlobal = in_array('admin', $roles, true) || in_array('coordinador', $roles, true) || in_array('coordinadora', $roles, true);
        $scopeIsGlobal = $canSeeGlobal;

        $pdo = Connection::getPdo();

        $whereAoat = $scopeIsGlobal ? '' : ' WHERE user_id = :user_id';
        $whereEvaluaciones = $scopeIsGlobal ? '' : ' WHERE document_number = :document_number';
        $whereAsistencia = $scopeIsGlobal ? '' : ' WHERE advisor_user_id = :user_id';
        $wherePlan = $scopeIsGlobal ? '' : ' WHERE user_id = :user_id';
        $wherePic = $scopeIsGlobal ? '' : ' WHERE user_id = :user_id';

        $paramsUser = [':user_id' => $userId];
        $paramsDoc = [':document_number' => (string) ($user['document_number'] ?? '')];

        $kpis = [
            'aoat_total' => $this->scalar(
                $pdo,
                'SELECT COUNT(*) FROM aoat_records' . $whereAoat,
                $whereAoat !== '' ? $paramsUser : []
            ),
            'aoat_aprobadas' => $this->scalar(
                $pdo,
                "SELECT COUNT(*) FROM aoat_records" . ($whereAoat === '' ? ' WHERE state = :state' : $whereAoat . ' AND state = :state'),
                ($whereAoat !== '' ? $paramsUser : []) + [':state' => 'Aprobada']
            ),
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

        $recentActivities = $this->recentActivities($pdo, $scopeIsGlobal, $userId, (string) ($user['document_number'] ?? ''));

        return Response::view('home/index', [
            'pageTitle' => 'Equipo de Promoción y Prevención',
            'tests' => EvaluacionesController::getTestsList(),
            'dashboard' => [
                'scope_is_global' => $scopeIsGlobal,
                'kpis' => $kpis,
                'aoat_completion_pct' => $aoatCompletionPct,
                'evaluaciones_pre' => $evaluacionesPre,
                'evaluaciones_post' => $evaluacionesPost,
                'module_mix' => $moduleMix,
                'recent_activities' => $recentActivities,
            ],
        ]);
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

