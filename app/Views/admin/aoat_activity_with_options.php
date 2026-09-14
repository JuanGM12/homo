<?php
/** @var array $options */

$options = is_array($options ?? null) ? $options : [];
if ($options === []) {
    $options = [['label' => '', 'sort_order' => 10, 'active' => 1]];
}
?>

<section class="mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb" class="mb-2">
                <ol class="breadcrumb small mb-0">
                    <li class="breadcrumb-item"><a href="/admin/usuarios">Administracion</a></li>
                    <li class="breadcrumb-item active">Con quién realizó AoAT</li>
                </ol>
            </nav>
            <h1 class="section-title mb-1">Desplegable de con quién realizó AoAT</h1>
            <p class="section-subtitle mb-0">
                Estas opciones alimentan el campo <strong>Con quién realizó la actividad</strong> al registrar una AoAT.
            </p>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <form method="post" action="/admin/aoat-con-quien" id="aoat-activity-with-form">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 110px;">Orden</th>
                                <th>Opción</th>
                                <th class="text-center" style="width: 90px;">Activa</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="aoat-activity-with-body">
                            <?php foreach ($options as $idx => $option): ?>
                                <tr data-option-row>
                                    <td>
                                        <input
                                            type="number"
                                            min="1"
                                            step="1"
                                            name="options[<?= (int) $idx ?>][sort_order]"
                                            class="form-control form-control-sm"
                                            value="<?= (int) ($option['sort_order'] ?? (($idx + 1) * 10)) ?>"
                                        >
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            name="options[<?= (int) $idx ?>][label]"
                                            class="form-control form-control-sm"
                                            maxlength="180"
                                            value="<?= htmlspecialchars((string) ($option['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            placeholder="Nombre de la opción"
                                        >
                                    </td>
                                    <td class="text-center">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            name="options[<?= (int) $idx ?>][active]"
                                            value="1"
                                            <?= !empty($option['active']) ? 'checked' : '' ?>
                                        >
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-outline-danger btn-sm" data-remove-option>Quitar</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4">
                    <button type="button" class="btn btn-outline-primary" id="aoat-add-activity-with">
                        <i class="bi bi-plus-circle me-1"></i>Agregar opción
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Guardar configuración
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

<template id="aoat-activity-with-template">
    <tr data-option-row>
        <td>
            <input type="number" min="1" step="1" class="form-control form-control-sm" data-name="sort_order" value="10">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm" data-name="label" maxlength="180" placeholder="Nombre de la opción">
        </td>
        <td class="text-center">
            <input type="checkbox" class="form-check-input" data-name="active" value="1" checked>
        </td>
        <td class="text-end">
            <button type="button" class="btn btn-outline-danger btn-sm" data-remove-option>Quitar</button>
        </td>
    </tr>
</template>

<script>
(function () {
    var body = document.getElementById('aoat-activity-with-body');
    var addBtn = document.getElementById('aoat-add-activity-with');
    var template = document.getElementById('aoat-activity-with-template');

    function reindexRows() {
        if (!body) return;
        Array.from(body.querySelectorAll('[data-option-row]')).forEach(function (row, idx) {
            Array.from(row.querySelectorAll('[data-name], [name]')).forEach(function (field) {
                if (field.closest('button')) return;
                var key = field.getAttribute('data-name');
                if (!key) {
                    var current = field.getAttribute('name') || '';
                    var match = current.match(/\]\[(.+?)\]$/);
                    key = match ? match[1] : '';
                }
                if (!key) return;
                field.setAttribute('name', 'options[' + idx + '][' + key + ']');
            });
            var orderField = row.querySelector('[data-name="sort_order"], [name$="[sort_order]"]');
            if (orderField && (!orderField.value || orderField.value === '10') && !orderField.dataset.touched) {
                orderField.value = String((idx + 1) * 10);
            }
        });
    }

    function addRow() {
        if (!template || !body) return;
        var fragment = template.content.cloneNode(true);
        body.appendChild(fragment);
        reindexRows();
        var rows = body.querySelectorAll('[data-option-row]');
        var last = rows[rows.length - 1];
        if (last) {
            var input = last.querySelector('input[data-name="label"], input[name$="[label]"]');
            if (input) input.focus();
        }
    }

    if (addBtn) {
        addBtn.addEventListener('click', addRow);
    }

    if (body) {
        body.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-remove-option]');
            if (!trigger) return;
            var rows = body.querySelectorAll('[data-option-row]');
            if (rows.length <= 1) return;
            var row = trigger.closest('[data-option-row]');
            if (row) {
                row.remove();
                reindexRows();
            }
        });
        body.addEventListener('input', function (event) {
            var field = event.target;
            if (field && field.matches('[data-name="sort_order"], [name$="[sort_order]"]')) {
                field.dataset.touched = '1';
            }
        });
    }

    reindexRows();
})();
</script>
