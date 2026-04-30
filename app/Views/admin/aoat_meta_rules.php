<?php
/** @var array $rules */
/** @var array $roleOptions */
/** @var array $scopeOptions */

$rules = is_array($rules ?? null) ? $rules : [];
$roleOptions = is_array($roleOptions ?? null) ? $roleOptions : [];
$scopeOptions = is_array($scopeOptions ?? null) ? $scopeOptions : [];
$months = [
    1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun',
    7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
];
?>

<section class="mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb" class="mb-2">
                <ol class="breadcrumb small mb-0">
                    <li class="breadcrumb-item"><a href="/admin/usuarios">Administracion</a></li>
                    <li class="breadcrumb-item active">Metas AoAT</li>
                </ol>
            </nav>
            <h1 class="section-title mb-1">Configuracion de metas AoAT</h1>
            <p class="section-subtitle mb-0">Define metas por rol, tramo de meses y tipo de medicion para el cuadro territorial.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <p class="small text-muted mb-3">
                Usa <strong>Por territorio</strong> cuando la meta aplica por profesional y municipio.
                Usa <strong>Global mensual</strong> cuando la meta se mide entre todos los registros del rol en cada mes.
                Para <strong>Abogado · Por territorio</strong> define por separado la meta mensual de <strong>SAFER</strong> y de <strong>política pública</strong> (Mesa / PPMSMYPA); el cuadro territorial y el saldo usan esos valores sin cambiar código.
            </p>

            <form method="post" action="/admin/aoat-metas" id="aoat-meta-rules-form">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Rol</th>
                                <th>Tipo</th>
                                <th class="aoat-rule-meta-th">Meta</th>
                                <th>Desde</th>
                                <th>Hasta</th>
                                <th>Año</th>
                                <th>Nota</th>
                                <th>Activa</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="aoat-meta-rules-body">
                            <?php foreach ($rules as $idx => $rule): ?>
                                <?php
                                $rk = (string) ($rule['role_key'] ?? '');
                                $sc = (string) ($rule['scope'] ?? '');
                                $dualAbogado = $rk === 'abogado' && $sc === 'per_territory';
                                $tsAdmin = (int) ($rule['target_safer'] ?? ($dualAbogado ? ($rule['target_value'] ?? 0) : ($rule['target_value'] ?? 0)));
                                $tpAdmin = (int) ($rule['target_politica'] ?? ($dualAbogado ? ($rule['target_value'] ?? 0) : ($rule['target_value'] ?? 0)));
                                ?>
                                <tr data-rule-row data-dual-abogado="<?= $dualAbogado ? '1' : '0' ?>">
                                    <td>
                                        <select data-name="role_key" name="rules[<?= (int) $idx ?>][role_key]" class="form-select form-select-sm aoat-rule-role">
                                            <?php foreach ($roleOptions as $option): ?>
                                                <option value="<?= htmlspecialchars((string) $option['value'], ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($rule['role_key'] ?? '') === (string) $option['value'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars((string) $option['label'], ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select data-name="scope" name="rules[<?= (int) $idx ?>][scope]" class="form-select form-select-sm aoat-rule-scope">
                                            <?php foreach ($scopeOptions as $option): ?>
                                                <option value="<?= htmlspecialchars((string) $option['value'], ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($rule['scope'] ?? '') === (string) $option['value'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars((string) $option['label'], ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="aoat-rule-meta-td">
                                        <div class="aoat-meta-standard <?= $dualAbogado ? 'd-none' : '' ?>" data-meta-standard>
                                            <input type="number" min="0" step="1" data-name="target_value" class="form-control form-control-sm aoat-in-target-value" value="<?= (int) ($rule['target_value'] ?? 0) ?>" <?= $dualAbogado ? 'disabled' : '' ?>>
                                        </div>
                                        <div class="aoat-meta-abogado <?= $dualAbogado ? '' : 'd-none' ?>" data-meta-abogado>
                                            <label class="small text-muted mb-0 text-uppercase">SAFER</label>
                                            <input type="number" min="0" step="1" data-name="target_safer" class="form-control form-control-sm mb-2 aoat-in-target-safer" value="<?= $dualAbogado ? $tsAdmin : 0 ?>" <?= $dualAbogado ? '' : 'disabled' ?>>
                                            <label class="small text-muted mb-0">Pol. pública</label>
                                            <input type="number" min="0" step="1" data-name="target_politica" class="form-control form-control-sm aoat-in-target-politica" value="<?= $dualAbogado ? $tpAdmin : 0 ?>" <?= $dualAbogado ? '' : 'disabled' ?>>
                                        </div>
                                    </td>
                                    <td>
                                        <select name="rules[<?= (int) $idx ?>][month_from]" class="form-select form-select-sm">
                                            <?php foreach ($months as $monthNum => $monthLabel): ?>
                                                <option value="<?= $monthNum ?>" <?= (int) ($rule['month_from'] ?? 1) === $monthNum ? 'selected' : '' ?>><?= $monthLabel ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="rules[<?= (int) $idx ?>][month_to]" class="form-select form-select-sm">
                                            <?php foreach ($months as $monthNum => $monthLabel): ?>
                                                <option value="<?= $monthNum ?>" <?= (int) ($rule['month_to'] ?? 12) === $monthNum ? 'selected' : '' ?>><?= $monthLabel ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" min="2020" max="2100" name="rules[<?= (int) $idx ?>][rule_year]" class="form-control form-control-sm" value="<?= htmlspecialchars((string) ($rule['rule_year'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Todos">
                                    </td>
                                    <td>
                                        <input type="text" name="rules[<?= (int) $idx ?>][notes]" class="form-control form-control-sm" value="<?= htmlspecialchars((string) ($rule['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Referencia o aclaracion">
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input" name="rules[<?= (int) $idx ?>][active]" value="1" <?= !empty($rule['active']) ? 'checked' : '' ?>>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-outline-danger btn-sm" data-remove-rule>Quitar</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4">
                    <button type="button" class="btn btn-outline-primary" id="aoat-add-rule">
                        <i class="bi bi-plus-circle me-1"></i>Agregar regla
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Guardar configuracion
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

<template id="aoat-meta-rule-template">
    <tr data-rule-row data-dual-abogado="0">
        <td>
            <select class="form-select form-select-sm aoat-rule-role" data-name="role_key">
                <?php foreach ($roleOptions as $option): ?>
                    <option value="<?= htmlspecialchars((string) $option['value'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $option['label'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm aoat-rule-scope" data-name="scope">
                <?php foreach ($scopeOptions as $option): ?>
                    <option value="<?= htmlspecialchars((string) $option['value'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $option['label'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td class="aoat-rule-meta-td">
            <div class="" data-meta-standard>
                <input type="number" min="0" step="1" class="form-control form-control-sm aoat-in-target-value" data-name="target_value" value="1">
            </div>
            <div class="d-none" data-meta-abogado>
                <label class="small text-muted mb-0 text-uppercase">SAFER</label>
                <input type="number" min="0" step="1" class="form-control form-control-sm mb-2 aoat-in-target-safer" data-name="target_safer" value="1">
                <label class="small text-muted mb-0">Pol. pública</label>
                <input type="number" min="0" step="1" class="form-control form-control-sm aoat-in-target-politica" data-name="target_politica" value="1">
            </div>
        </td>
        <td>
            <select class="form-select form-select-sm" data-name="month_from">
                <?php foreach ($months as $monthNum => $monthLabel): ?>
                    <option value="<?= $monthNum ?>"><?= $monthLabel ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm" data-name="month_to">
                <?php foreach ($months as $monthNum => $monthLabel): ?>
                    <option value="<?= $monthNum ?>" <?= $monthNum === 12 ? 'selected' : '' ?>><?= $monthLabel ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input type="number" min="2020" max="2100" class="form-control form-control-sm" data-name="rule_year" placeholder="Todos"></td>
        <td><input type="text" class="form-control form-control-sm" data-name="notes" placeholder="Referencia o aclaracion"></td>
        <td class="text-center"><input type="checkbox" class="form-check-input" data-name="active" value="1" checked></td>
        <td class="text-end"><button type="button" class="btn btn-outline-danger btn-sm" data-remove-rule>Quitar</button></td>
    </tr>
</template>

<script>
(function () {
    var body = document.getElementById('aoat-meta-rules-body');
    var addBtn = document.getElementById('aoat-add-rule');
    var template = document.getElementById('aoat-meta-rule-template');

    function reindexRows() {
        if (!body) return;
        Array.from(body.querySelectorAll('[data-rule-row]')).forEach(function (row, idx) {
            Array.from(row.querySelectorAll('[data-name], [name]')).forEach(function (field) {
                if (field.closest('button')) return;
                if (field.disabled) {
                    field.removeAttribute('name');
                    return;
                }
                var key = field.getAttribute('data-name');
                if (!key) {
                    var current = field.getAttribute('name') || '';
                    var match = current.match(/\]\[(.+?)\]$/);
                    key = match ? match[1] : '';
                }
                if (!key) return;
                field.setAttribute('name', 'rules[' + idx + '][' + key + ']');
            });
        });
    }

    function addRow() {
        if (!template || !body) return;
        var fragment = template.content.cloneNode(true);
        body.appendChild(fragment);
        refreshAllMetaEditors();
    }

    if (addBtn) {
        addBtn.addEventListener('click', addRow);
    }

    if (body) {
        body.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-remove-rule]');
            if (!trigger) return;
            var rows = body.querySelectorAll('[data-rule-row]');
            if (rows.length <= 1) return;
            var row = trigger.closest('[data-rule-row]');
            if (row) {
                row.remove();
                refreshAllMetaEditors();
            }
        });
    }

    function isDualAbogadoRow(row) {
        var roleEl = row.querySelector('.aoat-rule-role');
        var scopeEl = row.querySelector('.aoat-rule-scope');
        var role = roleEl ? String(roleEl.value || '') : '';
        var scope = scopeEl ? String(scopeEl.value || '') : '';
        return role === 'abogado' && scope === 'per_territory';
    }

    function syncMetaEditors(row) {
        var dual = isDualAbogadoRow(row);
        row.setAttribute('data-dual-abogado', dual ? '1' : '0');
        var std = row.querySelector('[data-meta-standard]');
        var abo = row.querySelector('[data-meta-abogado]');
        var inpVal = row.querySelector('.aoat-in-target-value');
        var inpS = row.querySelector('.aoat-in-target-safer');
        var inpP = row.querySelector('.aoat-in-target-politica');
        if (!std || !abo) return;
        if (dual) {
            std.classList.add('d-none');
            abo.classList.remove('d-none');
            if (inpVal) inpVal.disabled = true;
            if (inpS) inpS.disabled = false;
            if (inpP) inpP.disabled = false;
            if (inpS && inpP && (!inpS.value || inpS.value === '0') && (!inpP.value || inpP.value === '0') && inpVal && inpVal.value && inpVal.value !== '0') {
                inpS.value = inpVal.value;
                inpP.value = inpVal.value;
            }
        } else {
            abo.classList.add('d-none');
            std.classList.remove('d-none');
            if (inpVal) inpVal.disabled = false;
            if (inpS) inpS.disabled = true;
            if (inpP) inpP.disabled = true;
            if (inpVal && inpS && inpP && (!inpVal.value || inpVal.value === '0')) {
                var m = Math.max(parseInt(inpS.value, 10) || 0, parseInt(inpP.value, 10) || 0);
                if (m > 0) inpVal.value = String(m);
            }
        }
        reindexRows();
    }

    function refreshAllMetaEditors() {
        if (!body) return;
        Array.from(body.querySelectorAll('[data-rule-row]')).forEach(syncMetaEditors);
    }

    if (body) {
        body.addEventListener('change', function (event) {
            var t = event.target;
            if (!t.closest('.aoat-rule-role') && !t.closest('.aoat-rule-scope')) return;
            var row = t.closest('[data-rule-row]');
            if (row) syncMetaEditors(row);
        });
    }

    refreshAllMetaEditors();
})();
</script>
