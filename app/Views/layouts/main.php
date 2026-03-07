<?php
/** @var string $pageTitle */

use App\Config\Config;
use App\Services\Auth;

$flash = \App\Services\Flash::get();
$flashJson = $flash ? htmlspecialchars(json_encode($flash, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') : '';
$currentUser = Auth::user();
$currentPath = $_SERVER['REQUEST_URI'] ?? '/';

if (!function_exists('asset_url')) {
    function asset_url(string $relativePath): string
    {
        $root = dirname(__DIR__, 2);
        $publicPath = $root . '/public' . $relativePath;

        $version = file_exists($publicPath) ? (string) filemtime($publicPath) : (string) Config::env('ASSETS_VERSION', time());

        return $relativePath . '?v=' . $version;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Acción en Territorio', ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= asset_url('/assets/css/app.css') ?>">
</head>
<body class="app-body" <?= $flashJson !== '' ? 'data-flash="' . $flashJson . '"' : '' ?>>
<nav class="navbar navbar-expand-lg navbar-dark navbar-glass fixed-top">
    <div class="container-fluid px-3 px-md-4">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/">
            <span class="brand-mark rounded-circle d-inline-flex align-items-center justify-content-center">
                <i class="bi bi-heart-pulse-fill"></i>
            </span>
            <span class="d-flex flex-column lh-1">
                <span class="fw-semibold">Acción en Territorio</span>
                <small class="text-white-50">Promoción y Prevención</small>
            </span>
        </a>
        <div class="d-flex align-items-center gap-2 ms-auto">
            <?php if ($currentUser): ?>
                <button
                    class="btn btn-outline-light btn-sm d-lg-none me-1"
                    type="button"
                    aria-label="Abrir menú de navegación"
                    data-sidebar-toggle
                >
                    <i class="bi bi-list"></i>
                </button>
            <?php endif; ?>
            <?php if (!$currentUser): ?>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
                    <span class="navbar-toggler-icon"></span>
                </button>
            <?php endif; ?>
        </div>
        <div class="collapse navbar-collapse<?= $currentUser ? ' show' : '' ?>" id="mainNavbar">
            <?php if (!$currentUser): ?>
                <ul class="navbar-nav mb-2 mb-lg-0 me-3">
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPath === '/' ? 'active' : '' ?>" href="/">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= str_starts_with($currentPath, '/evaluaciones') ? 'active' : '' ?>" href="/evaluaciones">Evaluaciones</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= str_starts_with($currentPath, '/encuesta-opinion-aoat') && !str_contains($currentPath, '/listar') && !str_contains($currentPath, '/exportar') ? 'active' : '' ?>" href="/encuesta-opinion-aoat">Encuesta de Opinión AoAT</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= str_starts_with($currentPath, '/asesores') ? 'active' : '' ?>" href="/asesores">Asesores</a>
                    </li>
                </ul>
            <?php endif; ?>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <button type="button" class="btn btn-outline-light btn-sm d-none d-md-inline-flex" data-frase-mes>
                    Frase del mes
                </button>
                <?php if ($currentUser): ?>
                    <div class="dropdown dropdown-user">
                        <button
                            class="btn btn-light btn-sm d-flex align-items-center gap-2 dropdown-toggle"
                            type="button"
                            id="userMenuDropdown"
                            data-bs-toggle="dropdown"
                            data-bs-boundary="viewport"
                            data-bs-placement="bottom-end"
                            aria-expanded="false"
                        >
                            <span class="avatar-circle">
                                <?php
                                $initials = '';
                                $parts = preg_split('/\s+/', (string) $currentUser['name']);
                                if ($parts) {
                                    $initials .= mb_substr($parts[0], 0, 1, 'UTF-8');
                                    if (isset($parts[1])) {
                                        $initials .= mb_substr($parts[1], 0, 1, 'UTF-8');
                                    }
                                }
                                echo htmlspecialchars(mb_strtoupper($initials, 'UTF-8'), ENT_QUOTES, 'UTF-8');
                                ?>
                            </span>
                            <span class="d-none d-sm-inline">
                                <?= htmlspecialchars((string) $currentUser['name'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuDropdown">
                            <li>
                                <h6 class="dropdown-header">
                                    Sesión iniciada
                                </h6>
                            </li>
                            <li>
                                <a class="dropdown-item" href="/perfil">
                                    <i class="bi bi-person-circle me-2"></i>
                                    Mi perfil
                                </a>
                            </li>
                            <?php
                            $currentUserRoles = $currentUser['roles'] ?? [];
                            if (in_array('admin', $currentUserRoles, true)): ?>
                                <li>
                                    <a class="dropdown-item" href="/admin/usuarios">
                                        <i class="bi bi-shield-lock me-2"></i>
                                        Administración de usuarios
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="post" action="/logout" class="px-3 mb-0">
                                    <button type="submit" class="btn btn-outline-danger w-100 btn-sm">
                                        <i class="bi bi-box-arrow-right me-1"></i>
                                        Cerrar sesión
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="/login" class="btn btn-light btn-sm">Ingresar</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<main class="app-shell py-4">
    <div class="container-fluid">
        <div class="app-layout d-flex">
            <?php if ($currentUser):
                $currentUserRoles = $currentUser['roles'] ?? [];
                ?>
                <aside class="app-sidebar flex-column">
                    <div class="app-sidebar-inner">
                        <div class="app-sidebar-user mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar-circle avatar-circle-lg">
                                    <?= htmlspecialchars(mb_strtoupper($initials ?? '', 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold small">
                                        <?= htmlspecialchars((string) $currentUser['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <?php if (!empty($currentUserRoles[0])): ?>
                                        <small class="text-muted">
                                            <?= htmlspecialchars((string) $currentUserRoles[0], ENT_QUOTES, 'UTF-8') ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <nav class="app-sidebar-nav">
                            <a href="/" class="app-sidebar-link <?= $currentPath === '/' ? 'active' : '' ?>">
                                <i class="bi bi-house-door me-2"></i>
                                Inicio
                            </a>
                            <?php
                            $aoatRoles = ['abogado', 'medico', 'psicologo', 'profesional social', 'profesional_social'];
                            $canAccessAoat = !empty(array_intersect($aoatRoles, $currentUserRoles)) || in_array('admin', $currentUserRoles, true);
                            if ($canAccessAoat): ?>
                                <a href="/aoat" class="app-sidebar-link <?= str_starts_with($currentPath, '/aoat') ? 'active' : '' ?>">
                                    <i class="bi bi-clipboard-data me-2"></i>
                                    Seguimiento AoAT
                                </a>
                            <?php endif; ?>
                            <?php
                            $plannerRoles = ['abogado', 'medico', 'psicologo'];
                            $canAccessPlaneacion = !empty(array_intersect($plannerRoles, $currentUserRoles)) || in_array('admin', $currentUserRoles, true);
                            if ($canAccessPlaneacion): ?>
                                <a href="/planeacion" class="app-sidebar-link <?= str_starts_with($currentPath, '/planeacion') ? 'active' : '' ?>">
                                    <i class="bi bi-calendar3 me-2"></i>
                                    Planeación anual
                                </a>
                            <?php endif; ?>
                            <?php
                            $canAccessEntrenamiento = in_array('psicologo', $currentUserRoles, true) || in_array('admin', $currentUserRoles, true);
                            if ($canAccessEntrenamiento): ?>
                                <a href="/entrenamiento" class="app-sidebar-link <?= str_starts_with($currentPath, '/entrenamiento') ? 'active' : '' ?>">
                                    <i class="bi bi-journal-check me-2"></i>
                                    Plan de Entrenamiento
                                </a>
                            <?php endif; ?>
                            <?php
                            $picRoles = ['medico', 'psicologo', 'profesional social', 'profesional_social'];
                            $canAccessPic = !empty(array_intersect($picRoles, $currentUserRoles)) || in_array('admin', $currentUserRoles, true);
                            if ($canAccessPic): ?>
                                <a href="/pic" class="app-sidebar-link <?= str_starts_with($currentPath, '/pic') ? 'active' : '' ?>">
                                    <i class="bi bi-geo-alt me-2"></i>
                                    Seguimiento PIC
                                </a>
                            <?php endif; ?>
                            <a href="/asistencia" class="app-sidebar-link <?= str_starts_with($currentPath, '/asistencia') ? 'active' : '' ?>">
                                <i class="bi bi-list-check me-2"></i>
                                Listado de Asistencia
                            </a>
                            <a href="/evaluaciones" class="app-sidebar-link <?= str_starts_with($currentPath, '/evaluaciones') ? 'active' : '' ?>">
                                <i class="bi bi-clipboard2-pulse me-2"></i>
                                Evaluaciones · Test
                            </a>
                            <a href="/encuesta-opinion-aoat" class="app-sidebar-link <?= $currentPath === '/encuesta-opinion-aoat' ? 'active' : '' ?>">
                                <i class="bi bi-chat-square-text me-2"></i>
                                Encuesta de Opinión AoAT
                            </a>
                            <?php
                            $encuestaConsultaRoles = ['admin', 'coordinadora', 'especialista'];
                            $canConsultarEncuesta = (bool) array_intersect($encuestaConsultaRoles, $currentUserRoles);
                            if ($canConsultarEncuesta): ?>
                                <a href="/encuesta-opinion-aoat/listar" class="app-sidebar-link <?= str_starts_with($currentPath, '/encuesta-opinion-aoat/listar') ? 'active' : '' ?>">
                                    <i class="bi bi-table me-2"></i>
                                    Consultar encuestas AoAT
                                </a>
                            <?php endif; ?>
                            <?php if (in_array('admin', $currentUserRoles, true)): ?>
                                <div class="mt-3 pt-2 border-top border-light-subtle small text-muted">
                                    Administración
                                </div>
                                <a href="/admin/usuarios" class="app-sidebar-link <?= str_starts_with($currentPath, '/admin/usuarios') ? 'active' : '' ?>">
                                    <i class="bi bi-people-gear me-2"></i>
                                    Usuarios
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </aside>

                <div class="app-sidebar-backdrop d-lg-none"></div>
            <?php endif; ?>

            <div class="app-main flex-grow-1">
                <div class="container">
                    <?php require $viewFile; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= asset_url('/assets/js/app.js') ?>"></script>
</body>
</html>

