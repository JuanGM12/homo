<section class="auth-page py-4 py-md-5">
    <div class="row justify-content-center align-items-center auth-page-row">
        <div class="col-md-8 col-lg-6 col-xl-4">
            <div class="auth-card card border-0 shadow-lg rounded-4">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="auth-card-logo mb-3">
                            <span class="avatar-circle avatar-circle-lg bg-primary-subtle text-primary">
                                <i class="bi bi-key-fill"></i>
                            </span>
                        </div>
                        <h1 class="h5 fw-semibold mb-1">Recuperar contraseña</h1>
                        <p class="text-muted small mb-0">
                            Ingresa tu correo institucional. Generaremos una contraseña temporal para que puedas ingresar y cambiarla.
                        </p>
                    </div>

                    <form method="post" action="/recuperar-clave" autocomplete="on">
                        <div class="mb-4">
                            <label for="email" class="form-label">Correo institucional</label>
                            <input
                                type="email"
                                class="form-control"
                                id="email"
                                name="email"
                                required
                                autofocus
                                autocomplete="email"
                            >
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-arrow-repeat me-1"></i>
                                Generar contraseña temporal
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

