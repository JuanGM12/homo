<?php
/** @var array<string, mixed> $dashboard */

$dashboard = is_array($dashboard ?? null) ? $dashboard : [];
$kpis = is_array($dashboard['kpis'] ?? null) ? $dashboard['kpis'] : [];
$benef = is_array($dashboard['beneficiarios'] ?? null) ? $dashboard['beneficiarios'] : [];
$territory = is_array($dashboard['territory'] ?? null) ? $dashboard['territory'] : [];
$byRole = is_array($dashboard['by_role'] ?? null) ? $dashboard['by_role'] : [];

$barRows = static function (array $rows): string {
    $max = 0;
    foreach ($rows as $row) {
        $max = max($max, (int) ($row['value'] ?? 0));
    }
    $html = '';
    foreach ($rows as $row) {
        $label = htmlspecialchars((string) ($row['label'] ?? ''), ENT_QUOTES, 'UTF-8');
        $value = (int) ($row['value'] ?? 0);
        $pct = $max > 0 ? (int) round(($value / $max) * 100) : 0;
        $html .= '<div class="boletin-bar-row">'
            . '<span class="boletin-bar-label" title="' . $label . '">' . $label . '</span>'
            . '<span class="boletin-bar-track"><span class="boletin-bar-fill" style="width:' . $pct . '%"></span></span>'
            . '<span class="boletin-bar-value">' . $value . '</span>'
            . '</div>';
    }

    return $html !== '' ? $html : '<p class="boletin-empty">Sin datos en este recorte.</p>';
};
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
        <article class="boletin-kpi is-orange">
            <p>Asistencia técnica</p>
            <strong><?= (int) ($kpis['at'] ?? 0) ?></strong>
            <span>KPI AT</span>
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
