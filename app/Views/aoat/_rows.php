<?php
/** @var array<int, array<string, mixed>> $records */
/** @var bool $isAudit */
/** @var array|null $currentUserLocal */

use App\Services\Auth;

$user = $currentUserLocal ?? Auth::user();

$userId = $user['id'] ?? null;
$userRoles = $user['roles'] ?? [];
$isSpecialist = in_array('especialista', $userRoles ?? [], true);
$isCoordinator = in_array('coordinadora', $userRoles ?? [], true) || in_array('coordinador', $userRoles ?? [], true);
$isAdmin = in_array('admin', $userRoles ?? [], true);
$canAuditRole = $isSpecialist || $isCoordinator || $isAdmin;
?>

<?php foreach ($records as $record): ?>
    <?php
    $ownerId = $record['user_id'] ?? null;
    $isOwner = $userId !== null && (int) $ownerId === (int) $userId;
    $state = (string) ($record['state'] ?? '');
    $canEditForm = !$isAudit && $isOwner && !in_array($state, ['Aprobada', 'Realizado'], true);
    $canAuditState = $isAudit && !$isOwner && $state === 'Asignada' && $canAuditRole;
    $canApproveFromRealizado = $isAudit && !$isOwner && $state === 'Realizado' && $canAuditRole;
    $canMarkRealizado = !$isAudit && $isOwner && $state === 'Devuelta';

    $payloadArray = [];
    if (!empty($record['payload'])) {
        $decoded = json_decode((string) $record['payload'], true);
        if (is_array($decoded)) {
            $payloadArray = $decoded;
        }
    }

    $details = [
        'id' => (int) $record['id'],
        'created_at' => (string) ($record['created_at'] ?? ''),
        'professional' => trim((string) (($record['professional_name'] ?? '') . ' ' . ($record['professional_last_name'] ?? ''))),
        'professional_role' => (string) ($record['professional_role'] ?? ''),
        'subregion' => (string) ($record['subregion'] ?? ''),
        'municipality' => (string) ($record['municipality'] ?? ''),
        'state' => $state,
        'payload' => $payloadArray,
    ];
    $detailsJson = htmlspecialchars(json_encode($details, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

    $badgeClass = 'text-bg-light';
    if ($state === 'Aprobada') {
        $badgeClass = 'text-bg-success';
    } elseif ($state === 'Devuelta') {
        $badgeClass = 'text-bg-warning';
    } elseif ($state === 'Realizado') {
        $badgeClass = 'text-bg-info';
    }
    ?>
    <tr>
        <td><?= (int) $record['id'] ?></td>
        <td><?= htmlspecialchars((string) $record['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
        <td>
            <?= htmlspecialchars($record['professional_name'] . ' ' . $record['professional_last_name'], ENT_QUOTES, 'UTF-8') ?>
        </td>
        <td><?= htmlspecialchars((string) $record['subregion'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $record['municipality'], ENT_QUOTES, 'UTF-8') ?></td>
        <td>
            <span class="badge rounded-pill <?= $badgeClass ?>">
                <?= htmlspecialchars($state, ENT_QUOTES, 'UTF-8') ?>
            </span>
        </td>
        <td class="d-flex flex-wrap gap-2">
            <button
                type="button"
                class="btn btn-sm btn-outline-secondary"
                data-aoat-details
                data-aoat="<?= $detailsJson ?>"
            >
                <i class="bi bi-eye me-1"></i>
                Ver detalles
            </button>
            <?php if ($canEditForm): ?>
                <a href="/aoat/editar?id=<?= (int) $record['id'] ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil me-1"></i>
                    Editar
                </a>
            <?php endif; ?>
            <?php if ($canMarkRealizado): ?>
                <form method="post" action="/aoat/marcar-realizado" class="d-inline" onsubmit="return confirm('¿Confirmas que ya realizaste los ajustes solicitados? El estado pasará a «Realizado» y el especialista podrá aprobarlo.');">
                    <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-check2-circle me-1"></i>
                        Marcar como Realizado
                    </button>
                </form>
            <?php endif; ?>
            <?php if ($canAuditState): ?>
                <button
                    type="button"
                    class="btn btn-sm btn-outline-primary"
                    data-aoat-audit
                    data-aoat="<?= $detailsJson ?>"
                >
                    <i class="bi bi-clipboard-check me-1"></i>
                    Auditar
                </button>
            <?php endif; ?>
            <?php if ($canApproveFromRealizado): ?>
                <button
                    type="button"
                    class="btn btn-sm btn-primary"
                    data-aoat-approve-realizado
                    data-aoat="<?= $detailsJson ?>"
                >
                    <i class="bi bi-patch-check me-1"></i>
                    Aprobar revisión
                </button>
            <?php endif; ?>
            <?php if (!$canEditForm && !$canMarkRealizado && !$canAuditState && !$canApproveFromRealizado): ?>
                <span class="text-muted small">Sin acciones</span>
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; ?>
