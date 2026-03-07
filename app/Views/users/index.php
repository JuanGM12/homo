<?php
/** @var array $users */
/** @var array $roles */
/** @var array $filters */
?>

<section class="mt-4 mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h1 class="section-title mb-1">Usuarios del sistema</h1>
            <p class="section-subtitle mb-0">
                Administración de cuentas y roles para acceder a los módulos de la plataforma.
            </p>
        </div>
        <a href="/admin/usuarios/nuevo" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i>
            Nuevo usuario
        </a>
    </div>

    <form method="get" action="/admin/usuarios" class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Buscar por nombre o correo</label>
                    <input
                        type="text"
                        name="q"
                        class="form-control"
                        value="<?= htmlspecialchars((string) ($filters['query'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Ej. Ana, juan@correo.gov.co"
                    >
                </div>
                <div class="col-md-3">
                    <label class="form-label">Rol</label>
                    <select name="role" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($roles as $role): ?>
                            <?php
                            $roleName = (string) $role['name'];
                            $roleLabel = (string) ($role['description'] ?? $roleName);
                            ?>
                            <option
                                value="<?= htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8') ?>"
                                <?= ($filters['role'] ?? '') === $roleName ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Estado</label>
                    <select name="active" class="form-select">
                        <?php $activeFilter = (string) ($filters['active'] ?? ''); ?>
                        <option value="" <?= $activeFilter === '' ? 'selected' : '' ?>>Todos</option>
                        <option value="1" <?= $activeFilter === '1' ? 'selected' : '' ?>>Activos</option>
                        <option value="0" <?= $activeFilter === '0' ? 'selected' : '' ?>>Inactivos</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-grow-1">
                        <i class="bi bi-funnel me-1"></i>
                        Filtrar
                    </button>
                    <a href="/admin/usuarios" class="btn btn-outline-secondary">
                        Limpiar
                    </a>
                </div>
            </div>
        </div>
    </form>

    <?php if (empty($users)): ?>
        <div class="alert alert-info border-0 shadow-sm">
            No se encontraron usuarios con los filtros seleccionados.
        </div>
    <?php else: ?>
        <div class="table-responsive shadow-sm rounded-4 bg-white p-3">
            <table class="table align-middle mb-0">
                <thead>
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Nombre</th>
                    <th scope="col">Correo</th>
                    <th scope="col">Roles</th>
                    <th scope="col">Estado</th>
                    <th scope="col" class="text-end">Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= (int) $user['id'] ?></td>
                        <td><?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($user['roles_list'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if ((int) $user['active'] === 1): ?>
                                <span class="badge rounded-pill text-bg-success">Activo</span>
                            <?php else: ?>
                                <span class="badge rounded-pill text-bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm" role="group">
                                <a
                                    href="/admin/usuarios/editar?id=<?= (int) $user['id'] ?>"
                                    class="btn btn-outline-primary"
                                >
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <?php if ((int) $user['active'] === 1): ?>
                                    <button
                                        type="button"
                                        class="btn btn-outline-danger"
                                        data-user-id="<?= (int) $user['id'] ?>"
                                        data-user-name="<?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-user-deactivate
                                    >
                                        <i class="bi bi-person-dash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <form id="deactivate-user-form" method="post" action="/admin/usuarios/desactivar" class="d-none">
            <input type="hidden" name="id" id="deactivate-user-id" value="">
        </form>
    <?php endif; ?>
</section>

