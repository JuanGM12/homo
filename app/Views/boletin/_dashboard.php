<?php
/** @var array<string, mixed> $dashboard */

$dashboard = is_array($dashboard ?? null) ? $dashboard : [];
$kpis = is_array($dashboard['kpis'] ?? null) ? $dashboard['kpis'] : [];
$benef = is_array($dashboard['beneficiarios'] ?? null) ? $dashboard['beneficiarios'] : [];
$territory = is_array($dashboard['territory'] ?? null) ? $dashboard['territory'] : [];
$byRole = is_array($dashboard['by_role'] ?? null) ? $dashboard['by_role'] : [];
$byActivity = is_array($dashboard['by_activity'] ?? null) ? $dashboard['by_activity'] : [];
$byMonth = is_array($dashboard['by_month'] ?? null) ? $dashboard['by_month'] : [];

$seriesTotal = static function (array $rows): int {
    $sum = 0;
    foreach ($rows as $row) {
        $sum += (int) ($row['value'] ?? 0);
    }

    return $sum;
};

$barRows = static function (array $rows) use ($seriesTotal): string {
    $max = 0;
    $sum = $seriesTotal($rows);
    foreach ($rows as $row) {
        $max = max($max, (int) ($row['value'] ?? 0));
    }
    $html = '';
    foreach ($rows as $row) {
        $label = htmlspecialchars((string) ($row['label'] ?? ''), ENT_QUOTES, 'UTF-8');
        $value = (int) ($row['value'] ?? 0);
        $pct = $max > 0 ? (int) round(($value / $max) * 100) : 0;
        $html .= '<div class="boletin-bar-row">'
            . '<span class="boletin-bar-label">' . $label . '</span>'
            . '<span class="boletin-bar-track"><span class="boletin-bar-fill" style="width:' . $pct . '%"></span></span>'
            . '<span class="boletin-bar-value">' . $value . '</span>'
            . '</div>';
    }
    if ($html === '') {
        return '<p class="boletin-empty">Sin datos en este recorte.</p>';
    }

    return $html . '<div class="boletin-chart-total">Total: <strong>' . $sum . '</strong></div>';
};

$activityTotal = $seriesTotal($byActivity);
$activityMax = 0;
foreach ($byActivity as $row) {
    $activityMax = max($activityMax, (int) ($row['value'] ?? 0));
}

$monthMax = 0;
$monthGrand = 0;
foreach ($byMonth as $row) {
    $monthMax = max(
        $monthMax,
        (int) ($row['asesoria'] ?? 0),
        (int) ($row['at'] ?? 0),
        (int) ($row['actividad'] ?? 0)
    );
    $monthGrand += (int) ($row['total'] ?? 0);
}
?>

<div class="boletin-dashboard">
    <div class="boletin-kpi-grid">
        <article class="boletin-kpi is-green">
            <p>AoAT registradas</p>
            <strong><?= (int) ($kpis['aoat_total'] ?? 0) ?></strong>
        </article>
        <article class="boletin-kpi is-teal">
            <p>Asesoría</p>
            <strong><?= (int) ($kpis['asesoria'] ?? 0) ?></strong>
        </article>
        <article class="boletin-kpi is-orange" title="Cálculo: cantidad de registros AoAT cuyo tipo de actividad es «Asistencia técnica». No incluye Asesoría ni Actividad.">
            <p>Asistencia técnica (AT)</p>
            <strong><?= (int) ($kpis['at'] ?? 0) ?></strong>
            <span>Cálculo: Nº de AoAT con tipo «Asistencia técnica»</span>
        </article>
        <article class="boletin-kpi is-purple">
            <p>Actividad</p>
            <strong><?= (int) ($kpis['actividad'] ?? 0) ?></strong>
        </article>
        <article class="boletin-kpi is-navy">
            <p>Beneficiarios</p>
            <strong><?= (int) ($benef['total'] ?? 0) ?></strong>
            <span>Listado de asistencia</span>
        </article>
        <article class="boletin-kpi is-gold">
            <p>Personas únicas</p>
            <strong><?= (int) ($benef['unicos'] ?? 0) ?></strong>
        </article>
    </div>

    <div class="boletin-panels boletin-panels-2">
        <section class="boletin-card">
            <h2>Totales por actividad realizada</h2>
            <?php if ($byActivity === []): ?>
                <p class="boletin-empty">Sin datos en este recorte.</p>
            <?php else: ?>
                <div class="boletin-col-chart" role="img" aria-label="Totales de Asesoría, Asistencia técnica y Actividad">
                    <div class="boletin-col-chart-bars">
                        <?php foreach ($byActivity as $row): ?>
                            <?php
                            $value = (int) ($row['value'] ?? 0);
                            $pct = $activityMax > 0 ? (int) round(($value / $activityMax) * 100) : 0;
                            $color = htmlspecialchars((string) ($row['color'] ?? '#1f8a4c'), ENT_QUOTES, 'UTF-8');
                            $label = htmlspecialchars((string) ($row['label'] ?? ''), ENT_QUOTES, 'UTF-8');
                            ?>
                            <div class="boletin-col">
                                <span class="boletin-col-value"><?= $value ?></span>
                                <span class="boletin-col-plot">
                                    <span class="boletin-col-bar" style="height:<?= max($pct, $value > 0 ? 6 : 0) ?>%;background:<?= $color ?>;"></span>
                                </span>
                                <span class="boletin-col-label"><?= $label ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="boletin-chart-total">Total general: <strong><?= $activityTotal ?></strong></div>
                </div>
            <?php endif; ?>
        </section>
        <section class="boletin-card">
            <h2>Por mes según actividad que realizó</h2>
            <?php if ($byMonth === []): ?>
                <p class="boletin-empty">Sin datos en este recorte.</p>
            <?php else: ?>
                <div class="boletin-month-legend">
                    <span><i class="is-asesoria"></i> Asesoría</span>
                    <span><i class="is-at"></i> Asistencia técnica</span>
                    <span><i class="is-actividad"></i> Actividad</span>
                </div>
                <div class="boletin-month-chart">
                    <?php foreach ($byMonth as $row): ?>
                        <?php
                        $asesoria = (int) ($row['asesoria'] ?? 0);
                        $at = (int) ($row['at'] ?? 0);
                        $actividad = (int) ($row['actividad'] ?? 0);
                        $h = static function (int $value) use ($monthMax): int {
                            if ($monthMax <= 0 || $value <= 0) {
                                return $value > 0 ? 6 : 0;
                            }

                            return max(6, (int) round(($value / $monthMax) * 100));
                        };
                        ?>
                        <div class="boletin-month-item">
                            <div class="boletin-month-group">
                                <span class="boletin-month-bar is-asesoria" style="height:<?= $h($asesoria) ?>%" title="Asesoría: <?= $asesoria ?>"></span>
                                <span class="boletin-month-bar is-at" style="height:<?= $h($at) ?>%" title="Asistencia técnica: <?= $at ?>"></span>
                                <span class="boletin-month-bar is-actividad" style="height:<?= $h($actividad) ?>%" title="Actividad: <?= $actividad ?>"></span>
                            </div>
                            <span class="boletin-month-label"><?= htmlspecialchars((string) ($row['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="boletin-month-values"><?= $asesoria ?> / <?= $at ?> / <?= $actividad ?></span>
                            <span class="boletin-month-total">Total <?= (int) ($row['total'] ?? 0) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="boletin-chart-total">Total general: <strong><?= $monthGrand ?></strong></div>
            <?php endif; ?>
        </section>
    </div>

    <div class="boletin-panels">
        <section class="boletin-card">
            <h2>Por rol profesional</h2>
            <?= $barRows($byRole) ?>
        </section>
        <section class="boletin-card">
            <h2>Sexo</h2>
            <?= $barRows(is_array($benef['sexo'] ?? null) ? $benef['sexo'] : []) ?>
        </section>
        <section class="boletin-card">
            <h2>Zona</h2>
            <?= $barRows(is_array($benef['zona'] ?? null) ? $benef['zona'] : []) ?>
        </section>
        <section class="boletin-card">
            <h2>Etnia</h2>
            <?= $barRows(is_array($benef['etnia'] ?? null) ? $benef['etnia'] : []) ?>
        </section>
    </div>

    <div class="boletin-panels boletin-panels-2">
        <section class="boletin-card">
            <h2>Total según edad</h2>
            <?= $barRows(is_array($benef['edad'] ?? null) ? $benef['edad'] : []) ?>
        </section>
        <section class="boletin-card">
            <h2>Grupo poblacional</h2>
            <?= $barRows(is_array($benef['grupo_poblacional'] ?? null) ? $benef['grupo_poblacional'] : []) ?>
        </section>
    </div>

    <section class="boletin-card boletin-table-card">
        <div class="boletin-table-head">
            <h2>Tabla por subregión y municipio</h2>
            <p>AoAT y beneficiarios del listado de asistencia en el mismo recorte.</p>
        </div>
        <div class="table-responsive">
            <table class="table boletin-table mb-0">
                <thead>
                    <tr>
                        <th>Subregión</th>
                        <th>Municipio</th>
                        <th>AoAT</th>
                        <th>Asesoría</th>
                        <th>AT</th>
                        <th>Actividad</th>
                        <th>Listados</th>
                        <th>Beneficiarios</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($territory === []): ?>
                        <tr>
                            <td colspan="8" class="text-muted">No hay registros con los filtros actuales.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($territory as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($row['subregion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($row['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= (int) ($row['aoat'] ?? 0) ?></td>
                                <td><?= (int) ($row['asesoria'] ?? 0) ?></td>
                                <td><?= (int) ($row['at'] ?? 0) ?></td>
                                <td><?= (int) ($row['actividad'] ?? 0) ?></td>
                                <td><?= (int) ($row['listados'] ?? 0) ?></td>
                                <td><?= (int) ($row['beneficiarios'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
