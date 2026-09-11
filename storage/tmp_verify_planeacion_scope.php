<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Repositories\TrainingPlanRepository;
use App\Repositories\UserRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

$dotenvPath = dirname(__DIR__);
if (file_exists($dotenvPath . '/.env')) {
    Dotenv\Dotenv::createImmutable($dotenvPath)->safeLoad();
}

$pdo = Connection::getPdo();
$repo = new TrainingPlanRepository();
$userRepo = new UserRepository();

$inheritedStmt = $pdo->query(
    'SELECT um.user_id, um.subregion, um.municipality, tp.id AS plan_id, tp.user_id AS plan_user_id
     FROM user_municipalities um
     INNER JOIN training_plans tp
        ON tp.subregion = um.subregion AND tp.municipality = um.municipality
     WHERE tp.user_id <> um.user_id
     LIMIT 10'
);
$inheritedCases = $inheritedStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$inheritedVisible = 0;
foreach ($inheritedCases as $case) {
    $userId = (int) $case['user_id'];
    $assigned = $userRepo->findMunicipalitiesForUser($userId);
    if ($assigned === []) {
        continue;
    }
    $visible = $repo->findForUserAndAssignedMunicipalities($userId, $assigned);
    $own = $repo->findForUser($userId);
    $visibleIds = array_map(static fn (array $row): int => (int) $row['id'], $visible);
    $ownIds = array_map(static fn (array $row): int => (int) $row['id'], $own);
    $planId = (int) $case['plan_id'];
    if (in_array($planId, $visibleIds, true) && !in_array($planId, $ownIds, true)) {
        $inheritedVisible++;
    }
}

$usersWithoutAssigned = (int) $pdo->query(
    'SELECT COUNT(*) FROM users u
     WHERE NOT EXISTS (SELECT 1 FROM user_municipalities um WHERE um.user_id = u.id)'
)->fetchColumn();

$usersWithAssigned = (int) $pdo->query(
    'SELECT COUNT(DISTINCT user_id) FROM user_municipalities'
)->fetchColumn();

echo "inherited_cases=" . count($inheritedCases) . PHP_EOL;
echo "inherited_now_visible=" . $inheritedVisible . PHP_EOL;
echo "users_without_assigned=" . $usersWithoutAssigned . PHP_EOL;
echo "users_with_assigned=" . $usersWithAssigned . PHP_EOL;

$sampleOwn = $pdo->query('SELECT user_id FROM training_plans ORDER BY id DESC LIMIT 1')->fetchColumn();
if ($sampleOwn) {
    $sampleUserId = (int) $sampleOwn;
    $assigned = $userRepo->findMunicipalitiesForUser($sampleUserId);
    $own = $repo->findForUser($sampleUserId);
    $visible = $assigned === []
        ? $repo->findForUser($sampleUserId)
        : $repo->findForUserAndAssignedMunicipalities($sampleUserId, $assigned);
    echo "sample_assigned_count=" . count($assigned) . PHP_EOL;
    echo "sample_own=" . count($own) . PHP_EOL;
    echo "sample_visible=" . count($visible) . PHP_EOL;
    echo "sample_visible_gte_own=" . (count($visible) >= count($own) ? 'yes' : 'no') . PHP_EOL;
    if ($assigned === []) {
        echo "sample_empty_assignment_equals_own=" . (count($visible) === count($own) ? 'yes' : 'no') . PHP_EOL;
    }
}
