<?php

declare(strict_types=1);

namespace App\Core;

final class App
{
    private Router $router;

    public function __construct()
    {
        $this->router = new Router();
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        // Página de inicio pública
        $this->router->get('/', [\App\Controllers\HomeController::class, 'index']);

        // Autenticación básica (luego la expandimos)
        $this->router->get('/login', [\App\Controllers\AuthController::class, 'showLoginForm'])->name('login');
        $this->router->post('/login', [\App\Controllers\AuthController::class, 'login']);
        $this->router->post('/logout', [\App\Controllers\AuthController::class, 'logout'])->middleware('auth');
        $this->router->get('/recuperar-clave', [\App\Controllers\AuthController::class, 'showForgotPasswordForm']);
        $this->router->post('/recuperar-clave', [\App\Controllers\AuthController::class, 'handleForgotPassword']);
        $this->router->get('/restablecer-clave', [\App\Controllers\AuthController::class, 'showResetPasswordForm']);
        $this->router->post('/restablecer-clave', [\App\Controllers\AuthController::class, 'handleResetPassword']);

        // Módulo 1: Evaluaciones - Test (acceso libre)
        $this->router->get('/evaluaciones', [\App\Controllers\EvaluacionesController::class, 'index']);
        $this->router->get('/evaluaciones/detalle', [\App\Controllers\EvaluacionesController::class, 'showDetail'])->middleware('auth');
        $this->router->post('/evaluaciones/eliminar', [\App\Controllers\EvaluacionesController::class, 'destroyResponse'])->middleware('auth', 'role:admin');
        $this->router->get('/evaluaciones/exportar-csv', [\App\Controllers\EvaluacionesController::class, 'exportCsv'])->middleware('auth');
        $this->router->get('/evaluaciones/exportar-pdf', [\App\Controllers\EvaluacionesController::class, 'exportPdf'])->middleware('auth');

        // Utilidad: verificación de PRE para POST (AJAX)
        $this->router->post('/evaluaciones/check-pre', [\App\Controllers\EvaluacionesController::class, 'checkPre']);

        // Violencias
        $this->router->get('/evaluaciones/violencias/pre', [\App\Controllers\EvaluacionesController::class, 'preViolencias']);
        $this->router->post('/evaluaciones/violencias/pre', [\App\Controllers\EvaluacionesController::class, 'preViolencias']);
        $this->router->get('/evaluaciones/violencias/post', [\App\Controllers\EvaluacionesController::class, 'postViolencias']);
        $this->router->post('/evaluaciones/violencias/post', [\App\Controllers\EvaluacionesController::class, 'postViolencias']);

        // Suicidios
        $this->router->get('/evaluaciones/suicidios/pre', [\App\Controllers\EvaluacionesController::class, 'preSuicidios']);
        $this->router->post('/evaluaciones/suicidios/pre', [\App\Controllers\EvaluacionesController::class, 'preSuicidios']);
        $this->router->get('/evaluaciones/suicidios/post', [\App\Controllers\EvaluacionesController::class, 'postSuicidios']);
        $this->router->post('/evaluaciones/suicidios/post', [\App\Controllers\EvaluacionesController::class, 'postSuicidios']);

        // Adicciones
        $this->router->get('/evaluaciones/adicciones/pre', [\App\Controllers\EvaluacionesController::class, 'preAdicciones']);
        $this->router->post('/evaluaciones/adicciones/pre', [\App\Controllers\EvaluacionesController::class, 'preAdicciones']);
        $this->router->get('/evaluaciones/adicciones/post', [\App\Controllers\EvaluacionesController::class, 'postAdicciones']);
        $this->router->post('/evaluaciones/adicciones/post', [\App\Controllers\EvaluacionesController::class, 'postAdicciones']);

        // Hospitales
        $this->router->get('/evaluaciones/hospitales/pre', [\App\Controllers\EvaluacionesController::class, 'preHospitales']);
        $this->router->post('/evaluaciones/hospitales/pre', [\App\Controllers\EvaluacionesController::class, 'preHospitales']);
        $this->router->get('/evaluaciones/hospitales/post', [\App\Controllers\EvaluacionesController::class, 'postHospitales']);
        $this->router->post('/evaluaciones/hospitales/post', [\App\Controllers\EvaluacionesController::class, 'postHospitales']);

        // Módulo 6: Encuesta de Opinión AoAT (acceso libre para registrar; consulta/export solo roles específicos)
        $this->router->get('/encuesta-opinion-aoat', [\App\Controllers\EncuestaOpinionAoatController::class, 'form']);
        $this->router->post('/encuesta-opinion-aoat', [\App\Controllers\EncuestaOpinionAoatController::class, 'store']);
        $this->router->get('/encuesta-opinion-aoat/listar', [\App\Controllers\EncuestaOpinionAoatController::class, 'index'])->middleware('auth');
        $this->router->get('/encuesta-opinion-aoat/exportar', [\App\Controllers\EncuestaOpinionAoatController::class, 'export'])->middleware('auth');
        $this->router->post('/encuesta-opinion-aoat/eliminar', [\App\Controllers\EncuestaOpinionAoatController::class, 'destroy'])->middleware('auth', 'role:admin');

        // Módulo 2: Registro de AoAT (requiere autenticación)
        $this->router->get('/aoat/seguimiento', [\App\Controllers\AoatSeguimientoController::class, 'index'])->middleware('auth');
        $this->router->get('/aoat/seguimiento/datos', [\App\Controllers\AoatSeguimientoController::class, 'data'])->middleware('auth');
        $this->router->get('/aoat/seguimiento/exportar-csv', [\App\Controllers\AoatSeguimientoController::class, 'exportCsv'])->middleware('auth');
        $this->router->get('/aoat/seguimiento/exportar-pdf', [\App\Controllers\AoatSeguimientoController::class, 'exportPdf'])->middleware('auth');
        $this->router->get('/aoat', [\App\Controllers\AoatController::class, 'index'])->middleware('auth');
        $this->router->get('/aoat/nueva', [\App\Controllers\AoatController::class, 'create'])->middleware('auth');
        $this->router->post('/aoat/nueva', [\App\Controllers\AoatController::class, 'store'])->middleware('auth');
        $this->router->get('/aoat/editar', [\App\Controllers\AoatController::class, 'edit'])->middleware('auth');
        $this->router->post('/aoat/editar', [\App\Controllers\AoatController::class, 'update'])->middleware('auth');
        $this->router->post('/aoat/cambiar-estado', [\App\Controllers\AoatController::class, 'updateState'])->middleware('auth');
        $this->router->post('/aoat/cambiar-estado-masivo', [\App\Controllers\AoatController::class, 'updateStateBulk'])->middleware('auth');
        $this->router->post('/aoat/eliminar', [\App\Controllers\AoatController::class, 'destroy'])->middleware('auth', 'role:admin');
        $this->router->post('/aoat/marcar-realizado', [\App\Controllers\AoatController::class, 'markAsRealizado'])->middleware('auth');
        $this->router->get('/aoat/reportes', [\App\Controllers\AoatController::class, 'reportForm'])->middleware('auth');
        $this->router->post('/aoat/reportes/enviar', [\App\Controllers\AoatController::class, 'sendWeeklyReport'])->middleware('auth');
        $this->router->get('/aoat/exportar', [\App\Controllers\AoatController::class, 'export'])->middleware('auth');

        // Módulo 3: Planeación anual de capacitaciones (requiere autenticación y rol específico)
        $this->router->get('/planeacion', [\App\Controllers\PlaneacionController::class, 'index'])->middleware('auth');
        $this->router->get('/planeacion/nueva', [\App\Controllers\PlaneacionController::class, 'create'])->middleware('auth');
        $this->router->post('/planeacion/nueva', [\App\Controllers\PlaneacionController::class, 'store'])->middleware('auth');
        $this->router->get('/planeacion/editar', [\App\Controllers\PlaneacionController::class, 'edit'])->middleware('auth');
        $this->router->post('/planeacion/editar', [\App\Controllers\PlaneacionController::class, 'update'])->middleware('auth');
        $this->router->get('/planeacion/exportar', [\App\Controllers\PlaneacionController::class, 'export'])->middleware('auth');
        $this->router->post('/planeacion/eliminar', [\App\Controllers\PlaneacionController::class, 'destroy'])->middleware('auth', 'role:admin');

        // Módulo 4: Plan de Entrenamiento (solo Psicólogos)
        $this->router->get('/entrenamiento', [\App\Controllers\EntrenamientoController::class, 'index'])->middleware('auth');
        $this->router->get('/entrenamiento/nuevo', [\App\Controllers\EntrenamientoController::class, 'create'])->middleware('auth');
        $this->router->post('/entrenamiento/nuevo', [\App\Controllers\EntrenamientoController::class, 'store'])->middleware('auth');
        $this->router->get('/entrenamiento/editar', [\App\Controllers\EntrenamientoController::class, 'edit'])->middleware('auth');
        $this->router->post('/entrenamiento/editar', [\App\Controllers\EntrenamientoController::class, 'update'])->middleware('auth');
        $this->router->get('/entrenamiento/exportar', [\App\Controllers\EntrenamientoController::class, 'export'])->middleware('auth');
        $this->router->get('/entrenamiento/exportar-pdf', [\App\Controllers\EntrenamientoController::class, 'exportPdf'])->middleware('auth');
        $this->router->post('/entrenamiento/eliminar', [\App\Controllers\EntrenamientoController::class, 'destroy'])->middleware('auth', 'role:admin');

        // Módulo 7: Listado de Asistencia (solo usuarios con rol)
        $this->router->get('/asistencia', [\App\Controllers\AsistenciaController::class, 'index'])->middleware('auth');
        $this->router->get('/asistencia/nueva', [\App\Controllers\AsistenciaController::class, 'create'])->middleware('auth');
        $this->router->post('/asistencia/nueva', [\App\Controllers\AsistenciaController::class, 'store'])->middleware('auth');
        $this->router->get('/asistencia/ver', [\App\Controllers\AsistenciaController::class, 'show'])->middleware('auth');
        $this->router->get('/asistencia/exportar-csv', [\App\Controllers\AsistenciaController::class, 'exportCsv'])->middleware('auth');
        $this->router->get('/asistencia/exportar-pdf', [\App\Controllers\AsistenciaController::class, 'exportPdf'])->middleware('auth');
        $this->router->post('/asistencia/eliminar', [\App\Controllers\AsistenciaController::class, 'delete'])->middleware('auth');
        $this->router->post('/asistencia/cambiar-estado', [\App\Controllers\AsistenciaController::class, 'updateStatus'])->middleware('auth');
        // Registro público de asistencia (enlace automático por código)
        $this->router->get('/asistencia/registrar', [\App\Controllers\AsistenciaController::class, 'registrarForm']);
        $this->router->post('/asistencia/registrar', [\App\Controllers\AsistenciaController::class, 'registrarStore']);
        $this->router->get('/asistencia/buscar-documento', [\App\Controllers\AsistenciaController::class, 'buscarPorDocumento']);

        // Módulo 5: Seguimiento PIC (no aplica para Abogados)
        $this->router->get('/pic', [\App\Controllers\PicController::class, 'index'])->middleware('auth');
        $this->router->get('/pic/nuevo', [\App\Controllers\PicController::class, 'create'])->middleware('auth');
        $this->router->post('/pic/nuevo', [\App\Controllers\PicController::class, 'store'])->middleware('auth');
        $this->router->get('/pic/editar', [\App\Controllers\PicController::class, 'edit'])->middleware('auth');
        $this->router->post('/pic/editar', [\App\Controllers\PicController::class, 'update'])->middleware('auth');
        $this->router->get('/pic/exportar', [\App\Controllers\PicController::class, 'export'])->middleware('auth');
        $this->router->post('/pic/eliminar', [\App\Controllers\PicController::class, 'destroy'])->middleware('auth', 'role:admin');

        // Perfil de usuario autenticado
        $this->router->get('/perfil', [\App\Controllers\ProfileController::class, 'edit'])->middleware('auth');
        $this->router->post('/perfil', [\App\Controllers\ProfileController::class, 'update'])->middleware('auth');

        // Administración de usuarios (solo rol admin)
        $this->router->get('/admin/usuarios', [\App\Controllers\UsersController::class, 'index'])->middleware('auth', 'role:admin');
        $this->router->get('/admin/usuarios/nuevo', [\App\Controllers\UsersController::class, 'create'])->middleware('auth', 'role:admin');
        $this->router->post('/admin/usuarios/nuevo', [\App\Controllers\UsersController::class, 'store'])->middleware('auth', 'role:admin');
        $this->router->get('/admin/usuarios/editar', [\App\Controllers\UsersController::class, 'edit'])->middleware('auth', 'role:admin');
        $this->router->post('/admin/usuarios/editar', [\App\Controllers\UsersController::class, 'update'])->middleware('auth', 'role:admin');
        $this->router->post('/admin/usuarios/desactivar', [\App\Controllers\UsersController::class, 'deactivate'])->middleware('auth', 'role:admin');
    }

    public function run(): void
    {
        $request = Request::fromGlobals();
        $response = $this->router->dispatch($request);
        $response->send();
    }
}

