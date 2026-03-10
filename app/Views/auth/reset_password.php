<?php
/** @var string $token */
/** @var string $email */
?>

<section class="auth-page py-4 py-md-5">
    <div class="row justify-content-center align-items-center auth-page-row">
        <div class="col-md-8 col-lg-6 col-xl-4">
            <div class="auth-card card border-0 shadow-lg rounded-4">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="auth-card-logo mb-3">
                            <span class="avatar-circle avatar-circle-lg bg-primary-subtle text-primary">
                                <i class="bi bi-shield-lock-fill"></i>
                            </span>
                        </div>
                        <h1 class="h5 fw-semibold mb-1">Definir nueva contraseña</h1>
                        <p class="text-muted small mb-0">
                            Estás restableciendo la contraseña para
                            <strong><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></strong>.
                        </p>
                    </div>

                    <form method="post" action="/restablecer-clave" autocomplete="off">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="mb-3">
                            <label for="password" class="form-label">Nueva contraseña</label>
                            <input
                                type="password"
                                class="form-control"
                                id="password"
                                name="password"
                                required
                                minlength="6"
                                autocomplete="new-password"
                            >
                        </div>

                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label">Confirmar nueva contraseña</label>
                            <input
                                type="password"
                                class="form-control"
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                minlength="6"
                                autocomplete="new-password"
                            >
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-key-fill me-1"></i>
                                Guardar nueva contraseña
                            </button>
                            <a href="/login" class="btn btn-outline-primary">
                                <i class="bi bi-arrow-left me-1"></i>
                                Volver a iniciar sesión
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

