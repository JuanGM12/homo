<?php
/** @var string $pageTitle */

use App\Config\Config;
use App\Services\Auth;

$flash = \App\Services\Flash::get();
$flashJson = $flash ? htmlspecialchars(json_encode($flash, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') : '';
$currentUser = Auth::user();
$currentPath = $_SERVER['REQUEST_URI'] ?? '/';
<<<<<<< HEAD
$pathForNav = parse_url($currentPath, PHP_URL_PATH) ?: '/';
$isLogin = ($pathForNav === '/login');

// Forzar cambio de contraseña si el usuario tiene la marca activa
if ($currentUser && !empty($currentUser['must_change_password'])) {
    $allowedPaths = ['/perfil', '/logout', '/login'];
    if (!in_array($pathForNav, $allowedPaths, true)) {
        header('Location: /perfil');
        exit;
    }
}
$showSidebar = $currentUser && !$isLogin;
$currentUserRoles = $currentUser['roles'] ?? [];
$initials = '';
if ($currentUser) {
    $parts = preg_split('/\s+/', (string) $currentUser['name']);
    if ($parts) {
        $initials .= mb_substr($parts[0], 0, 1, 'UTF-8');
        if (isset($parts[1])) {
            $initials .= mb_substr($parts[1], 0, 1, 'UTF-8');
        }
    }
    $initials = mb_strtoupper($initials, 'UTF-8');
}
=======
$currentPath = strtok($currentPath, '?') ?: '/';
$isHomePage = (rtrim($currentPath, '/') === '' || $currentPath === '/');
>>>>>>> 28b0fc2c9bad440eaecdbfda8da61b3bb368f497

if (!function_exists('asset_url')) {
    function asset_url(string $relativePath): string
    {
        $root = dirname(__DIR__, 2);
        $publicPath = $root . '/public' . $relativePath;
        $version = file_exists($publicPath) ? (string) filemtime($publicPath) : (string) Config::env('ASSETS_VERSION', time());
        return $relativePath . '?v=' . $version;
    }
}

$initials = '';
if ($currentUser) {
    $parts = preg_split('/\s+/', (string) $currentUser['name']);
    if ($parts) {
        $initials .= mb_substr($parts[0], 0, 1, 'UTF-8');
        if (isset($parts[1])) $initials .= mb_substr($parts[1], 0, 1, 'UTF-8');
    }
    $initials = mb_strtoupper($initials, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Acción en Territorio', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= asset_url('/assets/css/app.css') ?>">
</head>
<<<<<<< HEAD
<body class="app-body<?= !$showSidebar ? ' app-body--home' : '' ?>" <?= $flashJson !== '' ? 'data-flash="' . $flashJson . '"' : '' ?>>
<?php if ($showSidebar): ?>
<header class="app-topbar d-lg-none" aria-label="Menú">
    <a class="app-topbar-brand" href="/">
        <span class="app-topbar-brand-mark"><i class="bi bi-heart-pulse-fill"></i></span>
        <span class="app-topbar-brand-text">Acción en Territorio</span>
    </a>
    <button class="app-topbar-toggle" type="button" aria-label="Abrir menú" data-sidebar-toggle>
        <i class="bi bi-list"></i>
    </button>
</header>
<?php endif; ?>

<main class="app-shell">
    <div class="container-fluid">
        <div class="app-layout d-flex<?= !$showSidebar ? ' app-layout--home' : '' ?>">
            <?php if ($showSidebar): ?>
            <aside class="app-sidebar flex-column">
                <div class="app-sidebar-inner">
                    <div class="app-sidebar-head">
                        <a href="/" class="app-sidebar-brand-link">
                            <span class="app-sidebar-brand"><i class="bi bi-heart-pulse-fill"></i></span>
                            <span class="app-sidebar-head-title">Acción en Territorio</span>
                            <span class="app-sidebar-head-subtitle">Promoción y Prevención</span>
                        </a>
                        <?php if ($currentUser):
                            $primaryRole = !empty($currentUserRoles[0]) ? (string) $currentUserRoles[0] : '';
                            $hasEspecialista = in_array('especialista', $currentUserRoles, true);
                            $specializableRoles = ['medico', 'psicologo', 'abogado', 'profesional social', 'profesional_social'];
                            $displayRole = $primaryRole;
                            if ($primaryRole !== '' && $hasEspecialista && in_array($primaryRole, $specializableRoles, true)) {
                                $displayRole .= ' (Especializado)';
                            }
                        ?>
                            <div class="dropdown app-sidebar-user-dropdown">
                                <button class="app-sidebar-user dropdown-toggle" type="button" id="sidebarUserDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="avatar-circle avatar-circle-lg"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span>
                                    <div class="app-sidebar-user-info">
                                        <span class="app-sidebar-user-name"><?= htmlspecialchars((string) $currentUser['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if ($displayRole !== ''): ?>
                                            <span class="app-sidebar-user-role"><?= htmlspecialchars($displayRole, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <i class="bi bi-chevron-down app-sidebar-user-chevron"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-dark w-100 border-0 rounded-2 shadow-lg" aria-labelledby="sidebarUserDropdown">
                                    <li><h6 class="dropdown-header text-white-50">Sesión iniciada</h6></li>
                                    <li><a class="dropdown-item text-light" href="/perfil"><i class="bi bi-person-circle me-2"></i>Mi perfil</a></li>
                                    <?php if (in_array('admin', $currentUserRoles, true)): ?>
                                        <li><a class="dropdown-item text-light" href="/admin/usuarios"><i class="bi bi-shield-lock me-2"></i>Usuarios</a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider dropdown-divider-dark"></li>
                                    <li class="px-2 pb-2">
                                        <form method="post" action="/logout">
                                            <button type="submit" class="btn btn-outline-light btn-sm w-100"><i class="bi bi-box-arrow-right me-1"></i>Cerrar sesión</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                    <nav class="app-sidebar-nav" aria-label="Navegación principal">
                        <a href="/" class="app-sidebar-link <?= $pathForNav === '/' ? 'active' : '' ?>">
                            <i class="bi bi-house-door" aria-hidden="true"></i>
                            <span>Inicio</span>
                        </a>
                        <?php if ($currentUser):
                            $aoatRoles = ['abogado', 'medico', 'psicologo', 'profesional social', 'profesional_social', 'especialista', 'coordinadora', 'coordinador'];
                            $canAccessAoat = !empty(array_intersect($aoatRoles, $currentUserRoles)) || in_array('admin', $currentUserRoles, true);
                            if ($canAccessAoat): ?>
                                <a href="/aoat" class="app-sidebar-link <?= str_starts_with($currentPath, '/aoat') ? 'active' : '' ?>">
                                    <i class="bi bi-clipboard-data" aria-hidden="true"></i>
                                    <span>Seguimiento AoAT</span>
                                </a>
                            <?php endif;
                            $plannerRoles = ['abogado', 'medico', 'psicologo'];
                            $canAccessPlaneacion = !empty(array_intersect($plannerRoles, $currentUserRoles)) || in_array('admin', $currentUserRoles, true);
                            if ($canAccessPlaneacion): ?>
                                <a href="/planeacion" class="app-sidebar-link <?= str_starts_with($currentPath, '/planeacion') ? 'active' : '' ?>">
                                    <i class="bi bi-calendar3" aria-hidden="true"></i>
                                    <span>Planeación anual</span>
                                </a>
                            <?php endif;
                            $canAccessEntrenamiento = in_array('psicologo', $currentUserRoles, true) || in_array('admin', $currentUserRoles, true);
                            if ($canAccessEntrenamiento): ?>
                                <a href="/entrenamiento" class="app-sidebar-link <?= str_starts_with($currentPath, '/entrenamiento') ? 'active' : '' ?>">
                                    <i class="bi bi-journal-check" aria-hidden="true"></i>
                                    <span>Plan de Entrenamiento</span>
                                </a>
                            <?php endif;
                            $picRoles = ['medico', 'psicologo', 'profesional social', 'profesional_social'];
                            $canAccessPic = !empty(array_intersect($picRoles, $currentUserRoles)) || in_array('admin', $currentUserRoles, true);
                            if ($canAccessPic): ?>
                                <a href="/pic" class="app-sidebar-link <?= str_starts_with($currentPath, '/pic') ? 'active' : '' ?>">
                                    <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                    <span>Seguimiento PIC</span>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a href="/asistencia" class="app-sidebar-link <?= str_starts_with($currentPath, '/asistencia') ? 'active' : '' ?>">
                            <i class="bi bi-list-check" aria-hidden="true"></i>
                            <span>Listado de Asistencia</span>
                        </a>
                        <a href="/evaluaciones" class="app-sidebar-link <?= str_starts_with($currentPath, '/evaluaciones') ? 'active' : '' ?>">
                            <i class="bi bi-clipboard2-pulse" aria-hidden="true"></i>
                            <span>Evaluaciones · Test</span>
                        </a>
                        <a href="/encuesta-opinion-aoat" class="app-sidebar-link <?= $pathForNav === '/encuesta-opinion-aoat' ? 'active' : '' ?>">
                            <i class="bi bi-chat-square-text" aria-hidden="true"></i>
                            <span>Encuesta de Opinión AoAT</span>
                        </a>
                        <?php
                        $encuestaConsultaRoles = ['admin', 'coordinadora', 'coordinador', 'especialista'];
                        $canConsultarEncuesta = $currentUser && (bool) array_intersect($encuestaConsultaRoles, $currentUserRoles);
                        if ($canConsultarEncuesta): ?>
                            <a href="/encuesta-opinion-aoat/listar" class="app-sidebar-link <?= str_starts_with($currentPath, '/encuesta-opinion-aoat/listar') ? 'active' : '' ?>">
                                <i class="bi bi-table" aria-hidden="true"></i>
                                <span>Consultar encuestas AoAT</span>
                            </a>
                        <?php endif; ?>
                        <?php if ($currentUser && in_array('admin', $currentUserRoles, true)): ?>
                            <div class="app-sidebar-divider">
                                <span class="app-sidebar-divider-label">Administración</span>
                            </div>
                            <a href="/admin/usuarios" class="app-sidebar-link <?= str_starts_with($currentPath, '/admin/usuarios') ? 'active' : '' ?>">
                                <i class="bi bi-people-gear" aria-hidden="true"></i>
                                <span>Usuarios</span>
                            </a>
                        <?php endif; ?>
                        <div class="app-sidebar-divider mt-auto">
                            <span class="app-sidebar-divider-label">Acciones</span>
                        </div>
                        <button type="button" class="app-sidebar-link app-sidebar-btn" data-frase-mes>
                            <i class="bi bi-chat-quote" aria-hidden="true"></i>
                            <span>Frase del mes</span>
                        </button>
                        <?php if (!$currentUser): ?>
                            <a href="/login" class="app-sidebar-link app-sidebar-link-cta">
                                <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                                <span>Ingresar</span>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            </aside>

            <div class="app-sidebar-backdrop d-lg-none" aria-hidden="true"></div>
            <?php endif; ?>


            <div class="app-main flex-grow-1">
=======
<body class="app-body<?= $isHomePage ? ' is-home' : '' ?>" <?= $flashJson !== '' ? 'data-flash="' . $flashJson . '"' : '' ?>>

<?php if (!$isHomePage): ?>
<nav class="navbar navbar-expand-lg navbar-dark navbar-glass fixed-top">
    <div class="container-fluid px-3 px-md-4">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/">
            <span class="brand-mark rounded-circle d-inline-flex align-items-center justify-content-center">
                <i class="bi bi-heart-pulse-fill"></i>
            </span>
            <span class="d-flex flex-column lh-1">
                <span>Acción en Territorio</span>
                <small class="text-white-50">Promoción y Prevención</small>
            </span>
        </a>
        <div class="d-flex align-items-center gap-2 ms-auto">
            <?php if ($currentUser): ?>
                <button class="btn btn-outline-light btn-sm d-lg-none" type="button" aria-label="Menú" data-sidebar-toggle>
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
                    <li class="nav-item"><a class="nav-link <?= $currentPath === '/' ? 'active' : '' ?>" href="/">Inicio</a></li>
                    <li class="nav-item"><a class="nav-link <?= str_starts_with($currentPath, '/evaluaciones') ? 'active' : '' ?>" href="/evaluaciones">Evaluaciones</a></li>
                    <li class="nav-item"><a class="nav-link" href="/encuesta-opinion-aoat">Encuesta AoAT</a></li>
                    <li class="nav-item"><a class="nav-link" href="/asesores">Asesores</a></li>
                </ul>
            <?php endif; ?>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <?php if ($currentUser):
                    $currentUserRoles = $currentUser['roles'] ?? [];
                ?>
                    <div class="dropdown dropdown-user">
                        <button class="btn btn-light btn-sm d-flex align-items-center gap-2 dropdown-toggle" type="button" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="avatar-circle"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="d-none d-sm-inline"><?= htmlspecialchars((string) $currentUser['name'], ENT_QUOTES, 'UTF-8') ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuDropdown">
                            <li><h6 class="dropdown-header">Sesión iniciada</h6></li>
                            <li><a class="dropdown-item" href="/perfil"><i class="bi bi-person-circle me-2"></i>Mi perfil</a></li>
                            <?php if (in_array('admin', $currentUserRoles, true)): ?>
                                <li><a class="dropdown-item" href="/admin/usuarios"><i class="bi bi-shield-lock me-2"></i>Administración</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="post" action="/logout">
                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</button>
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
<?php endif; ?>

<main class="app-shell<?= !$isHomePage ? ' has-navbar' : '' ?>">
    <div class="app-layout<?= ($currentUser && !$isHomePage) ? '' : ' no-sidebar' ?>">
        <?php if ($currentUser && !$isHomePage):
            $currentUserRoles = $currentUser['roles'] ?? [];
            $aoatRoles = ['abogado', 'medico', 'psicologo', 'profesional social', 'profesional_social'];
            $canAoat = !empty(array_intersect($aoatRoles, $currentUserRoles)) || in_array('admin', $currentUserRoles, true);
            $plannerRoles = ['abogado', 'medico', 'psicologo'];
            $canPlaneacion = !empty(array_intersect($plannerRoles, $currentUserRoles)) || in_array('admin', $currentUserRoles, true);
            $canEntrenamiento = in_array('psicologo', $currentUserRoles, true) || in_array('admin', $currentUserRoles, true);
            $picRoles = ['medico', 'psicologo', 'profesional social', 'profesional_social'];
            $canPic = !empty(array_intersect($picRoles, $currentUserRoles)) || in_array('admin', $currentUserRoles, true);
            $encuestaConsultaRoles = ['admin', 'coordinadora', 'especialista'];
            $canConsultarEncuesta = (bool) array_intersect($encuestaConsultaRoles, $currentUserRoles);
        ?>
        <div class="app-sidebar-backdrop d-lg-none" data-sidebar-backdrop></div>
        <aside class="app-sidebar flex-column">
            <div class="app-sidebar-inner">
                <div class="app-sidebar-user mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar-circle avatar-circle-lg"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span>
                        <div class="d-flex flex-column">
                            <span class="fw-semibold small"><?= htmlspecialchars((string) $currentUser['name'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if (!empty($currentUserRoles[0])): ?>
                                <small class="text-muted"><?= htmlspecialchars((string) $currentUserRoles[0], ENT_QUOTES, 'UTF-8') ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <nav class="app-sidebar-nav">
                    <a href="/" class="app-sidebar-link <?= $currentPath === '/' ? 'active' : '' ?>"><i class="bi bi-house-door"></i>Inicio</a>
                    <?php if ($canAoat): ?><a href="/aoat" class="app-sidebar-link <?= str_starts_with($currentPath, '/aoat') ? 'active' : '' ?>"><i class="bi bi-clipboard-data"></i>Seguimiento AoAT</a><?php endif; ?>
                    <?php if ($canPlaneacion): ?><a href="/planeacion" class="app-sidebar-link <?= str_starts_with($currentPath, '/planeacion') ? 'active' : '' ?>"><i class="bi bi-calendar3"></i>Planeación anual</a><?php endif; ?>
                    <?php if ($canEntrenamiento): ?><a href="/entrenamiento" class="app-sidebar-link <?= str_starts_with($currentPath, '/entrenamiento') ? 'active' : '' ?>"><i class="bi bi-journal-check"></i>Plan de Entrenamiento</a><?php endif; ?>
                    <?php if ($canPic): ?><a href="/pic" class="app-sidebar-link <?= str_starts_with($currentPath, '/pic') ? 'active' : '' ?>"><i class="bi bi-geo-alt"></i>Seguimiento PIC</a><?php endif; ?>
                    <a href="/asistencia" class="app-sidebar-link <?= str_starts_with($currentPath, '/asistencia') ? 'active' : '' ?>"><i class="bi bi-list-check"></i>Listado de Asistencia</a>
                    <a href="/evaluaciones" class="app-sidebar-link <?= str_starts_with($currentPath, '/evaluaciones') ? 'active' : '' ?>"><i class="bi bi-clipboard2-pulse"></i>Evaluaciones</a>
                    <a href="/encuesta-opinion-aoat" class="app-sidebar-link <?= $currentPath === '/encuesta-opinion-aoat' ? 'active' : '' ?>"><i class="bi bi-chat-square-text"></i>Encuesta AoAT</a>
                    <?php if ($canConsultarEncuesta): ?><a href="/encuesta-opinion-aoat/listar" class="app-sidebar-link <?= str_starts_with($currentPath, '/encuesta-opinion-aoat/listar') ? 'active' : '' ?>"><i class="bi bi-table"></i>Consultar encuestas</a><?php endif; ?>
                    <?php if (in_array('admin', $currentUserRoles, true)): ?>
                        <div class="mt-3 pt-2 border-top border-secondary small text-white-50">Administración</div>
                        <a href="/admin/usuarios" class="app-sidebar-link <?= str_starts_with($currentPath, '/admin/usuarios') ? 'active' : '' ?>"><i class="bi bi-people-gear"></i>Usuarios</a>
                    <?php endif; ?>
                </nav>
            </div>
        </aside>
        <?php endif; ?>

        <div class="app-main flex-grow-1">
            <?php if ($isHomePage): ?>
                <div class="page-home">
                    <?php require $viewFile; ?>
                </div>
            <?php else: ?>
>>>>>>> 28b0fc2c9bad440eaecdbfda8da61b3bb368f497
                <div class="container">
                    <?php require $viewFile; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= asset_url('/assets/js/app.js') ?>"></script>
</body>
</html>
