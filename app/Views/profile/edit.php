<?php
/** @var array $user */
?>

<section class="mt-4 mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h1 class="section-title mb-1">Mi perfil</h1>
            <p class="section-subtitle mb-0">
                Actualiza tus datos personales y, si lo deseas, cambia tu contraseña.
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
                                Este paso es opcional. Completa los campos solo si deseas actualizar tu contraseña.
                            </p>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Contraseña actual</label>
                                <input
                                    type="password"
                                    name="current_password"
                                    class="form-control"
                                    autocomplete="current-password"
                                >
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nueva contraseña</label>
                                <input
                                    type="password"
                                    name="new_password"
                                    class="form-control"
                                    autocomplete="new-password"
                                >
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Confirmar nueva contraseña</label>
                                <input
                                    type="password"
                                    name="new_password_confirmation"
                                    class="form-control"
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

