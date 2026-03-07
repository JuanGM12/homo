<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 mt-4">
            <div class="card-body p-4">
                <h1 class="h4 fw-bold text-center mb-4">Iniciar sesión</h1>
                <form method="post" action="/login">
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo institucional</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Ingresar</button>
                </form>
            </div>
        </div>
    </div>
</div>

