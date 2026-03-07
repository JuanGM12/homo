<?php use App\Services\Auth; ?>

<section class="home-hero">
    <span class="home-hero__badge">Gobernación de Antioquia</span>
    <h1 class="home-hero__title">Equipo de <span>Promoción</span> y <span>Prevención</span></h1>
    <p class="home-hero__text">
        Plataforma para planear, ejecutar y dar seguimiento a las acciones de salud mental en el territorio,
        con información centralizada para todo el equipo.
    </p>
    <a href="<?= Auth::check() ? '/aoat' : '/login' ?>" class="home-hero__cta">
        <?= Auth::check() ? 'Ir al panel' : 'Ingresar' ?>
        <i class="bi bi-arrow-right"></i>
    </a>
</section>

<section class="home-section">
    <div class="home-section__head">
        <h2 class="home-section__title">Evaluaciones · Test</h2>
        <p class="home-section__sub">Pre y Post Test para medir el cambio en el conocimiento. Acceso libre.</p>
    </div>

    <div class="home-rule d-flex align-items-center gap-2">
        <span class="home-rule__icon"><i class="bi bi-info-circle-fill"></i></span>
        <div>
            <strong>Regla importante:</strong> cada persona debe diligenciar su <strong>PRE - TEST</strong> y su <strong>POST - TEST</strong> con el mismo número de documento.
        </div>
    </div>

    <div class="eval-grid">
        <?php foreach ($tests as $key => $test): ?>
        <div class="eval-card">
            <div class="eval-card__icon eval-card__icon--<?= htmlspecialchars($test['color'], ENT_QUOTES, 'UTF-8') ?>">
                <i class="bi bi-clipboard2-pulse"></i>
            </div>
            <h3 class="eval-card__title"><?= htmlspecialchars($test['name'], ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="eval-card__desc">Evalúa el nivel de conocimiento antes y después de la intervención.</p>
            <div class="eval-card__actions">
                <a href="/evaluaciones/<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>/pre" class="btn btn-outline-primary">PRE - TEST</a>
                <a href="/evaluaciones/<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>/post" class="btn btn-primary">POST - TEST</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
