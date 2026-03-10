<?php use App\Services\Auth; ?>

<section class="hero mb-5">
    <div class="row align-items-center g-4">
        <div class="col-lg-6">
            <span class="badge rounded-pill bg-light text-secondary border border-secondary border-opacity-25 text-uppercase small fw-semibold mb-3">
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
                <button type="button" class="btn btn-outline-secondary btn-lg px-4" data-frase-mes>
                    Ver frase del mes
                </button>
            </div>
            <div class="row g-3 hero-quick-actions">
                <div class="col-sm-6 col-lg-4">
                    <a href="<?= Auth::check() ? '/aoat/nueva' : '/login' ?>" class="quick-action-card">
                        <div class="quick-action-icon bg-teal-soft text-teal">
                            <i class="bi bi-clipboard-plus"></i>
                        </div>
                        <div class="quick-action-body">
                            <p class="quick-action-title mb-1">Registrar AoAT</p>
                            <p class="quick-action-text mb-0">Crea un nuevo registro de asesoría o asistencia técnica en territorio.</p>
                        </div>
                    </a>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <a href="<?= Auth::check() ? '/planeacion' : '/login' ?>" class="quick-action-card">
                        <div class="quick-action-icon bg-indigo-soft text-indigo">
                            <i class="bi bi-calendar3-event"></i>
                        </div>
                        <div class="quick-action-body">
                            <p class="quick-action-title mb-1">Planeación anual</p>
                            <p class="quick-action-text mb-0">Consulta o edita tu plan de capacitaciones y actividades programadas.</p>
                        </div>
                    </a>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <a href="<?= Auth::check() ? '/asistencia' : '/login' ?>" class="quick-action-card">
                        <div class="quick-action-icon bg-amber-soft text-amber">
                            <i class="bi bi-people-check"></i>
                        </div>
                        <div class="quick-action-body">
                            <p class="quick-action-title mb-1">Listado de asistencia</p>
                            <p class="quick-action-text mb-0">Registra participantes y descarga tus listados de cada actividad.</p>
                        </div>
                    </a>
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
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h2 class="section-title mb-1">Evaluaciones · Test</h2>
            <p class="section-subtitle mb-0">
                Pre y Post Test para medir el cambio en el conocimiento de las personas después de cada temática.
            </p>
        </div>
    </div>

    <?php require __DIR__ . '/../evaluaciones/_listado.php'; ?>
</section>
