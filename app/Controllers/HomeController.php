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
    /** Tipos de AoAT que cuentan para la meta (asistencia técnica + asesorías) */
    private const AOAT_META_ACTIVITY_TYPES = ['Asistencia técnica', 'Asesoría'];

    private const AOAT_ACTIVIDAD_TIPO = 'Actividad';

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $userId = (int) ($user['id'] ?? 0);
        $canSeeGlobal = Auth::canViewAllModuleRecords($user);

        $pdo = Connection::getPdo();
        $professionalRoleScope = Auth::dashboardProfessionalRoleScope($user);
        $unrestrictedDashboard = $professionalRoleScope === null;

        $professionalOptions = [];
        if ($canSeeGlobal) {
            $professionalOptions = $this->loadDashboardProfessionals($pdo, $professionalRoleScope);
        }
        $allowedFilterIds = array_values(array_filter(
            array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $professionalOptions),
            static fn (int $id): bool => $id > 0
        ));

        $filterUserId = null;
        if ($canSeeGlobal) {
            $rawProf = trim((string) $request->input('profesional', ''));
            if ($rawProf !== '' && ctype_digit($rawProf)) {
                $candidate = (int) $rawProf;
                if ($this->userExistsActive($pdo, $candidate) && in_array($candidate, $allowedFilterIds, true)) {
                    $filterUserId = $candidate;
                }
            }
        }

        if (!$canSeeGlobal) {
            $scopeKind = 'personal';
            $scopeUserIds = [$userId];
        } elseif ($filterUserId !== null) {
            $scopeKind = 'single';
            $scopeUserIds = [$filterUserId];
        } elseif ($unrestrictedDashboard) {
            $scopeKind = 'platform';
            $scopeUserIds = null;
        } else {
            $scopeKind = 'team';
            $scopeUserIds = $allowedFilterIds;
        }

        $scopeDocuments = null;
        if ($scopeKind !== 'platform') {
            $scopeDocuments = $this->fetchNonEmptyDocumentsForUserIds($pdo, $scopeUserIds ?? []);
        }

        $consolidatedNoFilter = $canSeeGlobal && $filterUserId === null;
        $scopeIsFullPlatform = $consolidatedNoFilter && $unrestrictedDashboard;
        $scopeTeamConsolidated = $consolidatedNoFilter && !$unrestrictedDashboard;

        $aoatStates = $this->countAoatStates($pdo, $scopeKind, $scopeUserIds);

        $aoatMetaSuma = $this->countAoatByPayloadActivityTypes($pdo, $scopeKind, $scopeUserIds, self::AOAT_META_ACTIVITY_TYPES);
        $aoatTipoActividad = $this->countAoatByPayloadActivityTypes($pdo, $scopeKind, $scopeUserIds, [self::AOAT_ACTIVIDAD_TIPO]);

        $kpis = [
            'aoat_total' => $this->countScoped(
                $pdo,
                'aoat_records',
                'user_id',
                $scopeKind,
                $scopeUserIds
            ),
            'aoat_meta_suma' => $aoatMetaSuma,
            'aoat_tipo_actividad' => $aoatTipoActividad,
            'aoat_aprobadas' => $aoatStates['Aprobada'],
            'aoat_asignadas' => $aoatStates['Asignada'],
            'aoat_devueltas' => $aoatStates['Devuelta'],
            'aoat_revisadas' => $aoatStates['Realizado'],
            'evaluaciones_total' => $this->countTestResponses($pdo, $scopeKind, $scopeDocuments),
            'asistencias_actividades' => $this->countScoped(
                $pdo,
                'asistencia_actividades',
                'advisor_user_id',
                $scopeKind,
                $scopeUserIds
            ),
            'asistentes_registrados' => $this->countAsistentesRegistrados($pdo, $scopeKind, $scopeUserIds),
            'planes_total' => $this->countScoped(
                $pdo,
                'training_plans',
                'user_id',
                $scopeKind,
                $scopeUserIds
            ),
            'entrenamientos_total' => $this->countScoped(
                $pdo,
                'entrenamiento_plans',
                'user_id',
                $scopeKind,
                $scopeUserIds
            ),
            'pic_total' => $this->countScoped(
                $pdo,
                'pic_records',
                'user_id',
                $scopeKind,
                $scopeUserIds
            ),
        ];

        $aoatCompletionPct = $kpis['aoat_total'] > 0
            ? (int) round(($kpis['aoat_aprobadas'] / $kpis['aoat_total']) * 100)
            : 0;

        $evaluacionesPre = $this->countTestResponsesByPhase($pdo, $scopeKind, $scopeDocuments, 'pre');
        $evaluacionesPost = $this->countTestResponsesByPhase($pdo, $scopeKind, $scopeDocuments, 'post');

        $moduleMix = [
            ['label' => 'AoAT', 'value' => $kpis['aoat_total']],
            ['label' => 'Evaluaciones', 'value' => $kpis['evaluaciones_total']],
            ['label' => 'Asistencia', 'value' => $kpis['asistencias_actividades']],
            ['label' => 'PIC', 'value' => $kpis['pic_total']],
        ];

        $recentActivities = $this->recentActivities($pdo, $scopeKind, $scopeUserIds);

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
                'scope_is_full_platform' => $scopeIsFullPlatform,
                'scope_team_consolidated' => $scopeTeamConsolidated,
                'can_filter_professional' => $canSeeGlobal,
                'filter_professional_id' => $filterUserId,
                'filter_professional_name' => $filterProfessionalName,
                'professional_options' => $professionalOptions,
                'consolidated_filter_label' => $unrestrictedDashboard ? 'Todos (consolidado)' : 'Todos (mi equipo)',
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
     * @param list<string>|null $roleScope null = todos los usuarios activos; [] = ninguno
     * @return array<int, array<string, mixed>>
     */
    private function loadDashboardProfessionals(PDO $pdo, ?array $roleScope): array
    {
        if ($roleScope === null) {
            $stmt = $pdo->query('SELECT id, name FROM users WHERE active = 1 ORDER BY name ASC');

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if ($roleScope === []) {
            return [];
        }

        $roles = array_values(array_unique($roleScope));
        $placeholders = implode(', ', array_fill(0, count($roles), '?'));
        $sql = "SELECT DISTINCT u.id, u.name FROM users u
                INNER JOIN user_roles ur ON ur.user_id = u.id
                INNER JOIN roles r ON r.id = ur.role_id
                WHERE u.active = 1 AND r.name IN ($placeholders)
                ORDER BY u.name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($roles);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param list<int> $userIds
     * @return list<string>
     */
    private function fetchNonEmptyDocumentsForUserIds(PDO $pdo, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($userIds), '?'));
        $sql = "SELECT DISTINCT document_number FROM users WHERE id IN ($placeholders)
                AND document_number IS NOT NULL AND TRIM(document_number) <> ''";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($userIds);
        $docs = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_filter(
            array_map(static fn ($d): string => trim((string) $d), $docs),
            static fn (string $d): bool => $d !== ''
        ));
    }

    /**
     * Cuenta registros AoAT cuyo payload.activity_type está en la lista (p. ej. meta o tipo «Actividad»).
     *
     * @param list<string> $types
     */
    private function countAoatByPayloadActivityTypes(
        PDO $pdo,
        string $scopeKind,
        ?array $scopeUserIds,
        array $types
    ): int {
        if ($types === []) {
            return 0;
        }

        if ($scopeKind !== 'platform' && ($scopeUserIds === null || $scopeUserIds === [])) {
            return 0;
        }

        $typePlaceholders = implode(', ', array_fill(0, count($types), '?'));
        $typeExpr = "JSON_UNQUOTE(JSON_EXTRACT(payload, '$.activity_type'))";
        $sql = "SELECT COUNT(*) FROM aoat_records WHERE payload IS NOT NULL AND {$typeExpr} IN ({$typePlaceholders})";
        $params = array_values($types);

        if ($scopeKind !== 'platform') {
            $uPh = implode(', ', array_fill(0, count($scopeUserIds ?? []), '?'));
            $sql .= " AND user_id IN ({$uPh})";
            $params = array_merge($params, $scopeUserIds);
        }

        return $this->scalar($pdo, $sql, $params);
    }

    /**
     * @return array{Asignada: int, Devuelta: int, Realizado: int, Aprobada: int}
     */
    private function countAoatStates(PDO $pdo, string $scopeKind, ?array $scopeUserIds): array
    {
        $out = [
            'Asignada' => 0,
            'Devuelta' => 0,
            'Realizado' => 0,
            'Aprobada' => 0,
        ];

        $sql = 'SELECT state, COUNT(*) AS c FROM aoat_records';
        $params = [];
        if ($scopeKind === 'platform') {
            // sin filtro
        } elseif ($scopeUserIds === null || $scopeUserIds === []) {
            return $out;
        } else {
            $placeholders = implode(', ', array_fill(0, count($scopeUserIds), '?'));
            $sql .= ' WHERE user_id IN (' . $placeholders . ')';
            $params = $scopeUserIds;
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

    private function countScoped(
        PDO $pdo,
        string $table,
        string $userColumn,
        string $scopeKind,
        ?array $scopeUserIds
    ): int {
        if ($scopeKind === 'platform') {
            return $this->scalar($pdo, "SELECT COUNT(*) FROM {$table}", []);
        }

        if ($scopeUserIds === null || $scopeUserIds === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($scopeUserIds), '?'));

        return $this->scalar(
            $pdo,
            "SELECT COUNT(*) FROM {$table} WHERE {$userColumn} IN ({$placeholders})",
            $scopeUserIds
        );
    }

    private function countAsistentesRegistrados(PDO $pdo, string $scopeKind, ?array $scopeUserIds): int
    {
        if ($scopeKind === 'platform') {
            return $this->scalar(
                $pdo,
                'SELECT COUNT(*) FROM asistencia_asistentes aa
                 INNER JOIN asistencia_actividades ac ON ac.id = aa.actividad_id',
                []
            );
        }

        if ($scopeUserIds === null || $scopeUserIds === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($scopeUserIds), '?'));

        return $this->scalar(
            $pdo,
            "SELECT COUNT(*) FROM asistencia_asistentes aa
             INNER JOIN asistencia_actividades ac ON ac.id = aa.actividad_id
             WHERE ac.advisor_user_id IN ({$placeholders})",
            $scopeUserIds
        );
    }

    /**
     * @param list<string>|null $scopeDocuments
     */
    private function countTestResponses(PDO $pdo, string $scopeKind, ?array $scopeDocuments): int
    {
        if ($scopeKind === 'platform') {
            return $this->scalar($pdo, 'SELECT COUNT(*) FROM test_responses', []);
        }

        if ($scopeDocuments === null || $scopeDocuments === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($scopeDocuments), '?'));

        return $this->scalar(
            $pdo,
            "SELECT COUNT(*) FROM test_responses WHERE document_number IN ({$placeholders})",
            $scopeDocuments
        );
    }

    /**
     * @param list<string>|null $scopeDocuments
     */
    private function countTestResponsesByPhase(
        PDO $pdo,
        string $scopeKind,
        ?array $scopeDocuments,
        string $phase
    ): int {
        if ($scopeKind === 'platform') {
            return $this->scalar(
                $pdo,
                'SELECT COUNT(*) FROM test_responses WHERE phase = :phase',
                [':phase' => $phase]
            );
        }

        if ($scopeDocuments === null || $scopeDocuments === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($scopeDocuments), '?'));
        $sql = "SELECT COUNT(*) FROM test_responses WHERE document_number IN ({$placeholders}) AND phase = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([...$scopeDocuments, $phase]);

        return (int) $stmt->fetchColumn();
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
     * @param list<int>|null $scopeUserIds
     * @return array<int, array<string, string>>
     */
    private function recentActivities(PDO $pdo, string $scopeKind, ?array $scopeUserIds): array
    {
        $items = [];

        if ($scopeKind === 'platform') {
            $sqlAoat = 'SELECT created_at, "AoAT registrada" AS event, municipality, subregion
                        FROM aoat_records
                        ORDER BY created_at DESC LIMIT 4';
            $stmtAoat = $pdo->prepare($sqlAoat);
            $stmtAoat->execute();

            $sqlTest = 'SELECT created_at, CONCAT("Test ", UPPER(phase), " · ", test_key) AS event, municipality, subregion
                        FROM test_responses
                        ORDER BY created_at DESC LIMIT 4';
            $stmtTest = $pdo->prepare($sqlTest);
            $stmtTest->execute();
        } elseif ($scopeUserIds === null || $scopeUserIds === []) {
            return [];
        } else {
            $placeholders = implode(', ', array_fill(0, count($scopeUserIds), '?'));
            $sqlAoat = "SELECT created_at, \"AoAT registrada\" AS event, municipality, subregion
                        FROM aoat_records
                        WHERE user_id IN ({$placeholders})
                        ORDER BY created_at DESC LIMIT 4";
            $stmtAoat = $pdo->prepare($sqlAoat);
            $stmtAoat->execute($scopeUserIds);

            $docs = $this->fetchNonEmptyDocumentsForUserIds($pdo, $scopeUserIds);
            if ($docs === []) {
                $stmtTest = null;
            } else {
                $phDoc = implode(', ', array_fill(0, count($docs), '?'));
                $sqlTest = "SELECT created_at, CONCAT(\"Test \", UPPER(phase), \" · \", test_key) AS event, municipality, subregion
                            FROM test_responses
                            WHERE document_number IN ({$phDoc})
                            ORDER BY created_at DESC LIMIT 4";
                $stmtTest = $pdo->prepare($sqlTest);
                $stmtTest->execute($docs);
            }
        }

        foreach ($stmtAoat->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $items[] = [
                'date' => (string) ($row['created_at'] ?? ''),
                'event' => (string) ($row['event'] ?? ''),
                'place' => trim((string) (($row['municipality'] ?? '') . ' · ' . ($row['subregion'] ?? ''))),
            ];
        }

        if (isset($stmtTest)) {
            foreach ($stmtTest->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $items[] = [
                    'date' => (string) ($row['created_at'] ?? ''),
                    'event' => (string) ($row['event'] ?? ''),
                    'place' => trim((string) (($row['municipality'] ?? '') . ' · ' . ($row['subregion'] ?? ''))),
                ];
            }
        }

        usort($items, static function (array $a, array $b): int {
            return strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''));
        });

        return array_slice($items, 0, 6);
    }
}
