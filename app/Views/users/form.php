<?php
/** @var string $mode */
/** @var array|null $user */
/** @var array $roles */

$isEdit = $mode === 'edit' && $user !== null;
$selectedRoles = $isEdit ? (array) ($user['roles'] ?? []) : [];
?>

<section class="mt-4 mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h1 class="section-title mb-1">
                <?= $isEdit ? 'Editar usuario' : 'Crear usuario' ?>
            </h1>
            <p class="section-subtitle mb-0">
                <?= $isEdit
                    ? 'Actualiza los datos básicos y roles asignados al usuario.'
                    : 'Registra un nuevo usuario y define sus roles de acceso.'
                ?>
            </p>
        </div>
        <a href="/admin/usuarios" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>
            Volver al listado
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 p-md-5">
                    <form method="post" action="<?= $isEdit ? '/admin/usuarios/editar' : '/admin/usuarios/nuevo' ?>">
                        <?php if ($isEdit): ?>
                            <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Nombre completo <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                name="name"
                                class="form-control"
                                required
                                value="<?= htmlspecialchars($isEdit ? (string) $user['name'] : '', ENT_QUOTES, 'UTF-8') ?>"
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Documento de identidad <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                name="document_number"
                                class="form-control"
                                required
                                maxlength="50"
                                inputmode="numeric"
                                autocomplete="off"
                                value="<?= htmlspecialchars($isEdit ? (string) ($user['document_number'] ?? '') : '', ENT_QUOTES, 'UTF-8') ?>"
                                aria-describedby="help-document-number"
                            >
                            <div id="help-document-number" class="form-text">
                                Cédula de ciudadanía u otro documento. Debe ser único en el sistema (evaluaciones y reportes lo usan como identificador).
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Correo electrónico <span class="text-danger">*</span></label>
                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                required
                                value="<?= htmlspecialchars($isEdit ? (string) $user['email'] : '', ENT_QUOTES, 'UTF-8') ?>"
                            >
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">
                                    <?= $isEdit ? 'Nueva contraseña' : 'Contraseña' ?>
                                    <?= $isEdit ? '' : ' <span class="text-danger">*</span>' ?>
                                </label>
                                <input
                                    type="password"
                                    name="password"
                                    class="form-control"
                                    <?= $isEdit ? '' : 'required' ?>
                                    placeholder="<?= $isEdit ? 'Dejar en blanco para mantener la actual' : '' ?>"
                                >
                            </div>
                            <div class="col-md-6 d-flex align-items-center">
                                <div class="form-check mt-4">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        id="user-active"
                                        name="active"
                                        value="1"
                                        <?= !$isEdit || (int) ($user['active'] ?? 1) === 1 ? 'checked' : '' ?>
                                    >
                                    <label class="form-check-label" for="user-active">
                                        Usuario activo
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label d-block">Roles asignados</label>
                            <p class="text-muted small mb-2">
                                Selecciona los roles que determinan a qué módulos y permisos tendrá acceso este usuario.
                            </p>
                            <div class="row">
                                <?php foreach ($roles as $role): ?>
                                    <?php
                                    $roleName = (string) $role['name'];
                                    $roleLabel = (string) ($role['description'] ?? $roleName);
                                    ?>
                                    <div class="col-md-4 mb-2">
                                        <div class="form-check">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                name="roles[]"
                                                id="role-<?= htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8') ?>"
                                                value="<?= htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8') ?>"
                                                <?= in_array($roleName, $selectedRoles, true) ? 'checked' : '' ?>
                                            >
                                            <label class="form-check-label small" for="role-<?= htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i>
                                <?= $isEdit ? 'Guardar cambios' : 'Crear usuario' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

