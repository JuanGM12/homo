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

$formatCreatedAt = static function (?string $value): array {
    if ($value === null || trim($value) === '') {
        return ['date' => 'Sin fecha', 'time' => ''];
    }

    try {
        $dt = new DateTimeImmutable($value);
        return [
            'date' => $dt->format('d/m/Y'),
            'time' => $dt->format('H:i'),
        ];
    } catch (Exception) {
        return ['date' => $value, 'time' => ''];
    }
};
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
    $canExportSingle = ($isAdmin || $isCoordinator || $isSpecialist) || ($isOwner && $state === 'Aprobada');

    $payloadArray = [];
    if (!empty($record['payload'])) {
        $decoded = json_decode((string) $record['payload'], true);
        if (is_array($decoded)) {
            $payloadArray = $decoded;
        }
    }

    $auditMotive = trim((string) ($record['audit_motive'] ?? ''));
    $auditObservation = trim((string) ($record['audit_observation'] ?? ''));
    if ($state === 'Devuelta' && ($auditMotive !== '' || $auditObservation !== '')) {
        $returnInfo = [];
        if ($auditMotive !== '') {
            $returnInfo['Motivo de devolución'] = $auditMotive;
        }
        if ($auditObservation !== '') {
            $returnInfo['Comentarios de devolución'] = $auditObservation;
        }

        $payloadArray = array_merge($returnInfo, $payloadArray);
    }

    $details = [
        'id' => (int) $record['id'],
        'created_at' => (string) ($record['created_at'] ?? ''),
        'professional' => trim((string) (($record['professional_name'] ?? '') . ' ' . ($record['professional_last_name'] ?? ''))),
        'professional_role' => (string) ($record['professional_role'] ?? ''),
        'subregion' => (string) ($record['subregion'] ?? ''),
        'municipality' => (string) ($record['municipality'] ?? ''),
        'state' => $state,
        'audit_motive' => $auditMotive,
        'audit_observation' => $auditObservation,
        'can_export_single' => $canExportSingle,
        'export_pdf_url' => $canExportSingle ? ('/aoat/exportar?id=' . (int) $record['id'] . '&format=pdf') : '',
        'export_excel_url' => $canExportSingle ? ('/aoat/exportar?id=' . (int) $record['id'] . '&format=xls') : '',
        'payload' => $payloadArray,
    ];
    $detailsJson = htmlspecialchars(json_encode($details, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

    $badgeClass = 'aoat-status-pill is-neutral';
    if ($state === 'Aprobada') {
        $badgeClass = 'aoat-status-pill is-success';
    } elseif ($state === 'Devuelta') {
        $badgeClass = 'aoat-status-pill is-warning';
    } elseif ($state === 'Realizado') {
        $badgeClass = 'aoat-status-pill is-info';
    } elseif ($state === 'Asignada') {
        $badgeClass = 'aoat-status-pill is-pending';
    }

    $dateParts = $formatCreatedAt(isset($record['created_at']) ? (string) $record['created_at'] : null);
    $professionalName = trim((string) (($record['professional_name'] ?? '') . ' ' . ($record['professional_last_name'] ?? '')));
    ?>
    <tr class="aoat-row">
        <td class="aoat-cell-date">
            <div class="aoat-date-stack">
                <span class="aoat-date-main"><?= htmlspecialchars($dateParts['date'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ($dateParts['time'] !== ''): ?>
                    <span class="aoat-date-sub"><?= htmlspecialchars($dateParts['time'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>
        </td>
        <td>
            <div class="aoat-professional">
                <strong class="aoat-professional-name"><?= htmlspecialchars($professionalName, ENT_QUOTES, 'UTF-8') ?></strong>
                <?php if (!empty($record['professional_role'])): ?>
                    <span class="aoat-professional-role"><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) $record['professional_role'])), ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>
        </td>
        <td class="aoat-cell-territory"><?= htmlspecialchars((string) ($record['subregion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td class="aoat-cell-territory"><?= htmlspecialchars((string) ($record['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td>
            <span class="<?= $badgeClass ?>">
                <?= htmlspecialchars($state, ENT_QUOTES, 'UTF-8') ?>
            </span>
        </td>
        <td class="text-end">
            <div class="aoat-actions">
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
                <?php if (!$canExportSingle && !$canEditForm && !$canAuditState && !$canApproveFromRealizado): ?>
                    <span class="aoat-no-actions">Sin acciones adicionales</span>
                <?php endif; ?>
            </div>
        </td>
    </tr>
<?php endforeach; ?>
