<?php use App\Services\Auth; ?>

<section class="hero mb-5">
    <div class="row align-items-center g-4">
        <div class="col-lg-6">
            <span class="badge rounded-pill text-bg-light text-uppercase small fw-semibold mb-3">
                #AcciónEnTerritorio · Gobernación de Antioquia
            </span>
            <h1 class="hero-title mb-3">
                Equipo de <span>Promoción</span> y <span>Prevención</span>
            </h1>
            <p class="hero-subtitle mb-4">
                Plataforma interactiva para planear, ejecutar y seguir las acciones de salud mental
                en los territorios, con información clara y centralizada para todo el equipo.
            </p>
            <div class="d-flex flex-wrap gap-3 mb-4">
                <a
                    href="<?= Auth::check() ? '/aoat' : '/login' ?>"
                    class="btn btn-primary btn-lg px-4 shadow-sm"
                >
                    Ir al panel
                    <i class="bi bi-arrow-right-short ms-1"></i>
                </a>
                <button type="button" class="btn btn-outline-light btn-lg px-4" data-frase-mes>
                    Ver frase del mes
                </button>
            </div>
            <div class="row g-3 hero-metrics">
                <div class="col-4">
                    <div class="metric-card">
                        <span class="metric-label">Municipios</span>
                        <span class="metric-value">125</span>
                    </div>
                </div>
                <div class="col-4">
                    <div class="metric-card">
                        <span class="metric-label">Equipos</span>
                        <span class="metric-value">+80</span>
                    </div>
                </div>
                <div class="col-4">
                    <div class="metric-card">
                        <span class="metric-label">Formularios</span>
                        <span class="metric-value">Activos</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="hero-panel shadow-lg rounded-4 bg-white">
                <div class="hero-panel-header d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="h6 mb-1">Visión en territorio</h2>
                        <small class="text-muted">Seguimiento de actividades AoAT</small>
                    </div>
                    <span class="badge rounded-pill bg-success-subtle text-success">
                        <i class="bi bi-check-circle me-1"></i>En línea
                    </span>
                </div>
                <div class="hero-panel-body">
                    <div class="timeline">
                        <div class="timeline-item">
                            <span class="timeline-dot bg-primary"></span>
                            <div class="timeline-content">
                                <p class="timeline-title mb-1">Diagnóstico comunitario</p>
                                <p class="timeline-text mb-0">Identificación de factores protectores y de riesgo.</p>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <span class="timeline-dot bg-success"></span>
                            <div class="timeline-content">
                                <p class="timeline-title mb-1">Acciones en territorio</p>
                                <p class="timeline-text mb-0">Capacitaciones, asesorías y acompañamiento técnico.</p>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <span class="timeline-dot bg-warning"></span>
                            <div class="timeline-content">
                                <p class="timeline-title mb-1">Seguimiento y análisis</p>
                                <p class="timeline-text mb-0">Tableros de control para decisiones oportunas.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="mb-5">
    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
        <div>
            <h2 class="section-title mb-1">Explora la plataforma</h2>
            <p class="section-subtitle mb-0">Accede rápidamente a los módulos claves del programa.</p>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-md-3">
            <a href="#" class="menu-card text-decoration-none">
                <div class="menu-card-inner">
                    <div class="menu-card-icon bg-primary-subtle text-primary">
                        <i class="bi bi-journal-richtext"></i>
                    </div>
                    <h3 class="h5 fw-semibold mb-1">Programa</h3>
                    <p class="text-muted mb-2">
                        Contexto, lineamientos, materiales y documentos oficiales del programa.
                    </p>
                    <span class="menu-card-link">
                        Ver documentación
                        <i class="bi bi-arrow-right-short"></i>
                    </span>
                </div>
            </a>
        </div>
        <?php if (Auth::check()): ?>
            <div class="col-md-3">
                <a href="/aoat" class="menu-card text-decoration-none">
                    <div class="menu-card-inner">
                        <div class="menu-card-icon bg-info-subtle text-info">
                            <i class="bi bi-clipboard-data"></i>
                        </div>
                        <h3 class="h5 fw-semibold mb-1">Seguimiento de actividades AoAT</h3>
                        <p class="text-muted mb-2">
                            Registro, consulta y reporte de las Asesorías o Asistencias Técnicas realizadas por el equipo.
                        </p>
                        <span class="menu-card-link">
                            Entrar al módulo
                            <i class="bi bi-arrow-right-short"></i>
                        </span>
                    </div>
                </a>
            </div>
        <?php endif; ?>
        <div class="col-md-3">
            <a href="/evaluaciones" class="menu-card text-decoration-none">
                <div class="menu-card-inner">
                    <div class="menu-card-icon bg-danger-subtle text-danger">
                        <i class="bi bi-clipboard2-pulse"></i>
                    </div>
                    <h3 class="h5 fw-semibold mb-1">Evaluaciones · Test</h3>
                    <p class="text-muted mb-2">
                        Acceso libre a Pre y Post Test para evaluar conocimientos antes y después de las intervenciones.
                    </p>
                    <span class="menu-card-link">
                        Ir a evaluaciones
                        <i class="bi bi-arrow-right-short"></i>
                    </span>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="/asesores" class="menu-card text-decoration-none">
                <div class="menu-card-inner">
                    <div class="menu-card-icon bg-success-subtle text-success">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <h3 class="h5 fw-semibold mb-1">Asesores</h3>
                    <p class="text-muted mb-2">
                        Registro y seguimiento de las actividades de los equipos de promoción y prevención.
                    </p>
                    <span class="menu-card-link">
                        Entrar al módulo
                        <i class="bi bi-arrow-right-short"></i>
                    </span>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="#" class="menu-card text-decoration-none">
                <div class="menu-card-inner">
                    <div class="menu-card-icon bg-warning-subtle text-warning">
                        <i class="bi bi-tools"></i>
                    </div>
                    <h3 class="h5 fw-semibold mb-1">Utilidades</h3>
                    <p class="text-muted mb-2">
                        Atajos a reportes, tableros de datos y recursos descargables.
                    </p>
                    <span class="menu-card-link">
                        Ver herramientas
                        <i class="bi bi-arrow-right-short"></i>
                    </span>
                </div>
            </a>
        </div>
    </div>
</section>

