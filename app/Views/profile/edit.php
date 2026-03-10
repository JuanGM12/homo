<?php
/** @var array $user */
$mustChangePassword = !empty($user['must_change_password']);
?>

<section class="mt-4 mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h1 class="section-title mb-1">Mi perfil</h1>
            <p class="section-subtitle mb-0">
                <?php if ($mustChangePassword): ?>
                    Debes cambiar tu contraseña antes de poder usar el sistema. Completa el formulario del cuadro que aparece al entrar.
                <?php else: ?>
                    Actualiza tus datos personales y, si lo deseas, cambia tu contraseña.
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 p-md-5">
                    <form method="post" action="/perfil">
                        <div class="mb-3">
                            <label class="form-label">Nombre completo <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                name="name"
                                class="form-control"
                                required
                                value="<?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?>"
                            >
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Correo electrónico <span class="text-danger">*</span></label>
                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                required
                                value="<?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?>"
                            >
                        </div>

                        <hr class="my-4">

                        <div class="mb-3">
                            <h2 class="h6 fw-semibold mb-1">Cambiar contraseña</h2>
                            <p class="text-muted small mb-0">
                                <?php if ($mustChangePassword): ?>
                                    <span class="text-danger fw-semibold">Es obligatorio.</span> Establece una contraseña nueva para poder continuar.
                                <?php else: ?>
                                    Este paso es opcional. Completa los campos solo si deseas actualizar tu contraseña.
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Contraseña actual<?= $mustChangePassword ? ' <span class="text-danger">*</span>' : '' ?></label>
                                <input
                                    type="password"
                                    name="current_password"
                                    class="form-control"
                                    <?= $mustChangePassword ? 'required' : '' ?>
                                    autocomplete="current-password"
                                >
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nueva contraseña<?= $mustChangePassword ? ' <span class="text-danger">*</span>' : '' ?></label>
                                <input
                                    type="password"
                                    name="new_password"
                                    class="form-control"
                                    <?= $mustChangePassword ? 'required' : '' ?>
                                    autocomplete="new-password"
                                >
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Confirmar nueva contraseña<?= $mustChangePassword ? ' <span class="text-danger">*</span>' : '' ?></label>
                                <input
                                    type="password"
                                    name="new_password_confirmation"
                                    class="form-control"
                                    <?= $mustChangePassword ? 'required' : '' ?>
                                    autocomplete="new-password"
                                >
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-person-check me-1"></i>
                                Guardar cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($mustChangePassword): ?>
<!-- Modal obligatorio: no se puede cerrar hasta cambiar la contraseña -->
<div class="modal fade" id="modalCambioContrasenaObligatorio" tabindex="-1" aria-labelledby="modalCambioContrasenaObligatorioLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 bg-light">
                <h2 class="modal-title h5 mb-0" id="modalCambioContrasenaObligatorioLabel">
                    <i class="bi bi-shield-lock-fill text-primary me-2" aria-hidden="true"></i>
                    Cambio de contraseña obligatorio
                </h2>
                <!-- Sin botón de cerrar: el modal no se puede cerrar hasta cambiar la contraseña -->
            </div>
            <div class="modal-body">
                <p class="mb-4">
                    Por seguridad, debes establecer una contraseña nueva antes de continuar. No podrás usar el sistema ni navegar a otras pantallas hasta que la cambies.
                </p>
                <p class="text-muted small mb-0">
                    Tu contraseña actual es la que te dieron al registrarte (por ejemplo, tu número de cédula). Completa los campos y haz clic en <strong>Cambiar contraseña</strong>.
                </p>
                <form method="post" action="/perfil" id="formCambioContrasenaModal">
                    <input type="hidden" name="name" value="<?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="email" value="<?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <label class="form-label">Contraseña actual <span class="text-danger">*</span></label>
                        <input type="password" name="current_password" class="form-control" required autocomplete="current-password" placeholder="Ej. tu cédula">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nueva contraseña <span class="text-danger">*</span></label>
                        <input type="password" name="new_password" class="form-control" required autocomplete="new-password" minlength="6" placeholder="Mínimo 6 caracteres">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Confirmar nueva contraseña <span class="text-danger">*</span></label>
                        <input type="password" name="new_password_confirmation" class="form-control" required autocomplete="new-password" minlength="6" placeholder="Repite la nueva contraseña">
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-key-fill me-1"></i>
                            Cambiar contraseña
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('modalCambioContrasenaObligatorio');
    if (!modalEl) return;
    if (typeof bootstrap === 'undefined') {
        console.warn('Bootstrap no cargado: no se puede mostrar el modal de cambio de contraseña.');
        return;
    }
    var modal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });
    modal.show();
});
</script>
<?php endif; ?>

