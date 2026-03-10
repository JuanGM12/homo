<section class="auth-page py-4 py-md-5">
    <div class="row justify-content-center align-items-center auth-page-row">
        <div class="col-xl-5 d-none d-lg-block">
            <div class="auth-hero">
                <div class="auth-hero-orbit auth-hero-orbit-1"></div>
                <div class="auth-hero-orbit auth-hero-orbit-2"></div>
                <div class="auth-hero-content">
                    <div class="auth-hero-logo mb-3">
                        <span class="auth-hero-logo-mark">
                            <i class="bi bi-heart-pulse-fill"></i>
                        </span>
                        <span class="auth-hero-logo-text">
                            Acción en Territorio
                        </span>
                    </div>
                    <h1 class="auth-hero-title mb-3">
                        Bienvenido al módulo de gestión
                    </h1>
                    <p class="auth-hero-subtitle mb-0">
                        Registra acciones en territorio, planea tus actividades y realiza el seguimiento clínico en un solo lugar, de forma segura.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-8 col-lg-6 col-xl-4">
            <div class="auth-card card border-0 shadow-lg rounded-4">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="auth-card-logo mb-3">
                            <span class="avatar-circle avatar-circle-lg bg-primary-subtle text-primary">
                                <i class="bi bi-heart-pulse-fill"></i>
                            </span>
                        </div>
                        <h2 class="h4 fw-semibold mb-1">Iniciar sesión</h2>
                        <p class="text-muted small mb-0">
                            Usa tu correo institucional y la contraseña asignada para ingresar.
                        </p>
                    </div>

                    <form method="post" action="/login" autocomplete="on">
                        <div class="mb-3">
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
                        <div class="mb-2">
                            <label for="password" class="form-label d-flex justify-content-between align-items-center">
                                <span>Contraseña</span>
                            </label>
                            <input
                                type="password"
                                class="form-control"
                                id="password"
                                name="password"
                                required
                                autocomplete="current-password"
                            >
                        </div>
                        <div class="mb-4 d-flex justify-content-end">
                            <a href="/recuperar-clave" class="auth-link-small">¿Olvidaste tu contraseña?</a>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-box-arrow-in-right me-1"></i>
                            Ingresar
                        </button>
                    </form>
                </div>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary w-100">Ingresar</button>
        </form>
    </div>
</section>

