<?php
use App\Services\Auth;
/** @var array $dashboard */
$dashboard = $dashboard ?? [];
$kpis = $dashboard['kpis'] ?? [];
$moduleMix = $dashboard['module_mix'] ?? [];
$recentActivities = $dashboard['recent_activities'] ?? [];
$scopeGlobal = (bool) ($dashboard['scope_is_global'] ?? false);
$isAuthenticated = Auth::check();
?>

<?php if ($isAuthenticated): ?>
    <section class="hero mb-5">
        <div class="row align-items-start g-4">
            <div class="col-lg-8">
                <div class="hero-logos mb-3">
                    <img src="/assets/img/logoAntioquia.png" alt="Gobernación de Antioquia" class="hero-logo-antioquia">
                    <img src="/assets/img/logoHomo.png" alt="HOMO" class="hero-logo-homo">
                </div>
                <span class="badge rounded-pill bg-light text-secondary border border-secondary border-opacity-25 text-uppercase small fw-semibold mb-3">
                    <?= $scopeGlobal ? 'Panel Global · Gestión Territorial' : 'Panel Personal · Gestión Territorial' ?>
                </span>
                <h1 class="hero-title mb-2">
                    Dashboard de <span>promoción</span> y <span>prevención</span>
                </h1>
                <p class="hero-subtitle mb-4">
                    Indicadores operativos y de seguimiento para la toma de decisiones.
                    <?= $scopeGlobal ? 'Vista consolidada de todo el programa.' : 'Vista de tu actividad y resultados.' ?>
                </p>

                <div class="row g-3 dashboard-kpi-grid">
                    <div class="col-sm-6 col-xl-3"><div class="dashboard-kpi-card"><p class="dashboard-kpi-label">AoAT registradas</p><p class="dashboard-kpi-value"><?= (int) ($kpis['aoat_total'] ?? 0) ?></p></div></div>
                    <div class="col-sm-6 col-xl-3"><div class="dashboard-kpi-card"><p class="dashboard-kpi-label">Evaluaciones</p><p class="dashboard-kpi-value"><?= (int) ($kpis['evaluaciones_total'] ?? 0) ?></p></div></div>
                    <div class="col-sm-6 col-xl-3"><div class="dashboard-kpi-card"><p class="dashboard-kpi-label">Actividades asistencia</p><p class="dashboard-kpi-value"><?= (int) ($kpis['asistencias_actividades'] ?? 0) ?></p></div></div>
                    <div class="col-sm-6 col-xl-3"><div class="dashboard-kpi-card"><p class="dashboard-kpi-label">Asistentes impactados</p><p class="dashboard-kpi-value"><?= (int) ($kpis['asistentes_registrados'] ?? 0) ?></p></div></div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="hero-panel shadow-sm rounded-4 bg-white">
                    <div class="hero-panel-header d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 class="h6 mb-1">Ejecución operativa</h2>
                            <small class="text-muted">Cumplimiento de registros</small>
                        </div>
                        <span class="badge rounded-pill bg-success-subtle text-success"><?= (int) ($dashboard['aoat_completion_pct'] ?? 0) ?>%</span>
                    </div>

                    <div class="dashboard-progress mb-3">
                        <div class="dashboard-progress-bar" style="width: <?= (int) ($dashboard['aoat_completion_pct'] ?? 0) ?>%"></div>
                    </div>

                    <ul class="list-unstyled dashboard-mini-list mb-0">
                        <li><span>AoAT aprobadas</span><strong><?= (int) ($kpis['aoat_aprobadas'] ?? 0) ?></strong></li>
                        <li><span>Planes anuales</span><strong><?= (int) ($kpis['planes_total'] ?? 0) ?></strong></li>
                        <li><span>Entrenamientos</span><strong><?= (int) ($kpis['entrenamientos_total'] ?? 0) ?></strong></li>
                        <li><span>Registros PIC</span><strong><?= (int) ($kpis['pic_total'] ?? 0) ?></strong></li>
                        <li><span>PRE / POST</span><strong><?= (int) ($dashboard['evaluaciones_pre'] ?? 0) ?> / <?= (int) ($dashboard['evaluaciones_post'] ?? 0) ?></strong></li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-5">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body p-4">
                        <h3 class="h6 fw-semibold mb-3">Distribución por módulos</h3>
                        <?php $maxMix = 1; foreach ($moduleMix as $mixItem) { $maxMix = max($maxMix, (int) ($mixItem['value'] ?? 0)); } ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($moduleMix as $mixItem): ?>
                                <?php $mixValue = (int) ($mixItem['value'] ?? 0); $mixPct = (int) round(($mixValue / $maxMix) * 100); ?>
                                <div class="dashboard-mix-row">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="small fw-semibold"><?= htmlspecialchars((string) ($mixItem['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="small text-muted"><?= $mixValue ?></span>
                                    </div>
                                    <div class="dashboard-progress dashboard-progress--thin"><div class="dashboard-progress-bar" style="width: <?= $mixPct ?>%"></div></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body p-4">
                        <h3 class="h6 fw-semibold mb-3">Actividad reciente</h3>
                        <div class="d-flex flex-column gap-3">
                            <?php if ($recentActivities === []): ?>
                                <p class="text-muted small mb-0">Aún no hay actividad reciente para mostrar.</p>
                            <?php else: foreach ($recentActivities as $item): ?>
                                <div class="dashboard-activity-item">
                                    <div class="dashboard-activity-dot"></div>
                                    <div>
                                        <p class="mb-1 fw-semibold small"><?= htmlspecialchars((string) ($item['event'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="mb-0 text-muted small"><?= htmlspecialchars((string) ($item['place'] ?? ''), ENT_QUOTES, 'UTF-8') ?><?php if (!empty($item['date'])): ?> · <?= htmlspecialchars((string) $item['date'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?></p>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php else: ?>
    <section class="hero mb-5">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <div class="hero-logos mb-3">
                    <img src="/assets/img/logoAntioquia.png" alt="Gobernación de Antioquia" class="hero-logo-antioquia">
                    <img src="/assets/img/logoHomo.png" alt="HOMO" class="hero-logo-homo">
                </div>
                <span class="badge rounded-pill bg-light text-secondary border border-secondary border-opacity-25 text-uppercase small fw-semibold mb-3">
                    Bienvenido · Acción en Territorio
                </span>
                <h1 class="hero-title mb-2">Plataforma de promoción y prevención en salud mental</h1>
                <p class="hero-subtitle mb-4">
                    Diligencia los pre y post test de evaluación o inicia sesión para gestionar AoAT,
                    asistencia, planeación, PIC y reportes de seguimiento.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="#evaluaciones-tests" class="btn btn-primary">
                        <i class="bi bi-clipboard2-pulse me-1"></i>
                        Ir a evaluaciones
                    </a>
                    <a href="/login" class="btn btn-outline-secondary">
                        <i class="bi bi-box-arrow-in-right me-1"></i>
                        Iniciar sesión
                    </a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="hero-panel shadow-sm rounded-4 bg-white">
                    <div class="hero-panel-header d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 class="h6 mb-1">Acceso rápido</h2>
                            <small class="text-muted">Flujo sugerido para visitantes</small>
                        </div>
                    </div>
                    <ul class="list-unstyled dashboard-mini-list mb-0">
                        <li><span>1. Selecciona temática de evaluación</span><strong>PRE / POST</strong></li>
                        <li><span>2. Diligencia el formulario completo</span><strong>En línea</strong></li>
                        <li><span>3. Para gestión interna</span><strong>Iniciar sesión</strong></li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="mb-5" id="evaluaciones-tests">
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
