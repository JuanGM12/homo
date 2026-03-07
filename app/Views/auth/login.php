<div class="login-wrap">
    <div class="login-card">
        <h1 class="h4 fw-bold text-center mb-4">Iniciar sesión</h1>
        <form method="post" action="/login">
            <div class="mb-3">
                <label for="email" class="form-label">Correo institucional</label>
                <input type="email" class="form-control" id="email" name="email" required autocomplete="email">
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary w-100">Ingresar</button>
        </form>
    </div>
</div>
