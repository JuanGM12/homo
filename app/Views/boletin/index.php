<?php
/** @var array<string, mixed> $filters */
/** @var array<string, mixed> $dashboard */
/** @var array<string, string> $roleOptions */
/** @var array<string, string> $activityTypeOptions */
/** @var array<string, string> $topicOptions */

$filters = is_array($filters ?? null) ? $filters : [];
$dashboard = is_array($dashboard ?? null) ? $dashboard : [];
$roleOptions = is_array($roleOptions ?? null) ? $roleOptions : [];
$activityTypeOptions = is_array($activityTypeOptions ?? null) ? $activityTypeOptions : [];
$topicOptions = is_array($topicOptions ?? null) ? $topicOptions : [];
$professionals = is_array($dashboard['professionals'] ?? null) ? $dashboard['professionals'] : [];
$filterSubregion = (string) ($filters['subregion'] ?? '');
$filterMunicipalities = is_array($filters['municipalities'] ?? null) ? $filters['municipalities'] : [];
$municipalitiesJson = htmlspecialchars(json_encode($filterMunicipalities, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
?>

<section class="boletin-page">
    <header class="boletin-hero">
        <div class="boletin-hero-logos">
            <img src="<?= asset_url('/assets/img/logoAntioquia.png') ?>" alt="Gobernación de Antioquia">
            <img src="<?= asset_url('/assets/img/logoHomo.png') ?>" alt="HOMO">
        </div>
        <div class="boletin-hero-copy">
            <p class="boletin-kicker">Boletín automático de resultados</p>
            <h1><?= htmlspecialchars((string) ($dashboard['program_short'] ?? 'Acción en Territorio'), ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="boletin-program"><?= htmlspecialchars((string) ($dashboard['program_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="boletin-meta">
                Contrato No. <?= htmlspecialchars((string) ($dashboard['contrato'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                · Corte <?= htmlspecialchars((string) ($dashboard['cutoff_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                · Fuente: Registro AoAT y Listado de asistencia
            </p>
        </div>
        <button type="button" class="btn btn-light btn-sm boletin-pdf" data-boletin-export>
            <i class="bi bi-file-earmark-pdf me-1"></i> Descargar PDF
        </button>
    </header>

    <form class="boletin-filters" method="get" action="/boletin" data-boletin-filters data-territory-filter>
        <div class="boletin-filter-grid">
            <div>
                <label>Periodo</label>
                <select name="period_id" class="form-select form-select-sm">
                    <option value="0">Todos</option>
                    <?php foreach (($dashboard['period_options'] ?? []) as $period): ?>
                        <?php $periodId = (int) ($period['id'] ?? 0); ?>
                        <?php if ($periodId <= 0) { continue; } ?>
                        <option value="<?= $periodId ?>" <?= (int) ($filters['period_id'] ?? 0) === $periodId ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($period['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?><?= !empty($period['active']) ? ' (activo)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Subregión</label>
                <select name="subregion" class="form-select form-select-sm" data-subregion-select data-current-value="<?= htmlspecialchars($filterSubregion, ENT_QUOTES, 'UTF-8') ?>">
                    <option value="">Todas</option>
                </select>
            </div>
            <div>
                <label>Municipio(s)</label>
                <select
                    name="municipality[]"
                    class="form-select form-select-sm"
                    multiple
                    data-municipality-select
                    data-municipality-multi="1"
                    data-current-values="<?= $municipalitiesJson ?>"
                    disabled
                ></select>
            </div>
            <div>
                <label>Desde</label>
                <input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars((string) ($filters['from_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div>
                <label>Hasta</label>
                <input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars((string) ($filters['to_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div>
                <label>Tema</label>
                <select name="tema" class="form-select form-select-sm">
                    <option value="">AoAT · todos los temas</option>
                    <?php foreach ($topicOptions as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($filters['tema'] ?? '') === $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Rol</label>
                <select name="role" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($roleOptions as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($filters['role'] ?? '') === $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Profesional</label>
                <select name="professional_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($professionals as $pro): ?>
                        <?php $pid = (int) ($pro['id'] ?? 0); ?>
                        <option value="<?= $pid ?>" <?= (int) ($filters['professional_id'] ?? 0) === $pid ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($pro['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Actividad que realizó</label>
                <select name="activity_type" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <?php foreach ($activityTypeOptions as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($filters['activity_type'] ?? '') === $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="boletin-filter-actions">
            <a href="/boletin" class="btn btn-outline-secondary btn-sm" data-homo-filter-clear="/boletin">Limpiar</a>
        </div>
    </form>

    <div data-boletin-results>
        <?php require __DIR__ . '/_dashboard.php'; ?>
    </div>
</section>
