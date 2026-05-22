<?php
/**
 * Dashboard Template
 *
 * Displays summary cards (active, today's, overdue alert counts) and a list
 * of overdue alerts with Close/View action buttons.
 *
 * Variables expected:
 *   $counts        - array with keys: active, today, overdue
 *   $overdueAlerts - array of overdue alert rows (max 10, sorted by next_run_at ASC)
 *   $config        - array, application configuration (for base_url)
 */

$baseUrl = rtrim($config['base_path'] ?? '', '/');
?>

<h1 class="mb-4">Panel</h1>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card text-center h-100">
            <div class="card-body">
                <h5 class="card-title text-muted">Aktywne alerty</h5>
                <p class="display-4 fw-bold text-primary mb-0"><?= (int) $counts['active'] ?></p>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card text-center h-100">
            <div class="card-body">
                <h5 class="card-title text-muted">Dzisiejsze alerty</h5>
                <p class="display-4 fw-bold text-info mb-0"><?= (int) $counts['today'] ?></p>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card text-center h-100">
            <div class="card-body">
                <h5 class="card-title text-muted">Zaległe alerty</h5>
                <p class="display-4 fw-bold text-danger mb-0"><?= (int) $counts['overdue'] ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Overdue Alerts List -->
<h2 class="mb-3">Zaległe alerty</h2>

<?php if (empty($overdueAlerts)): ?>
<div class="alert alert-success" role="alert">
    Brak zaległych alertów. Wszystko na bieżąco!
</div>
<?php else: ?>
<div class="list-group mb-4">
    <?php foreach ($overdueAlerts as $alert): ?>
    <div class="list-group-item d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
        <div>
            <h6 class="mb-1"><?= htmlspecialchars($alert['title'], ENT_QUOTES, 'UTF-8') ?></h6>
            <small class="text-muted">Termin: <?= htmlspecialchars($alert['next_run_at'], ENT_QUOTES, 'UTF-8') ?></small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/alerts-edit?id=<?= (int) $alert['id'] ?>&action=close"
               class="btn btn-sm btn-warning"
               aria-label="Zamknij alert: <?= htmlspecialchars($alert['title'], ENT_QUOTES, 'UTF-8') ?>">Zamknij</a>
            <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/alerts-edit?id=<?= (int) $alert['id'] ?>"
               class="btn btn-sm btn-outline-primary"
               aria-label="Zobacz alert: <?= htmlspecialchars($alert['title'], ENT_QUOTES, 'UTF-8') ?>">Zobacz</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Floating Action Button for mobile (new alert) -->
<a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/alerts-create"
   class="fab d-md-none"
   aria-label="Utwórz nowy alert"
   title="Utwórz nowy alert"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></a>
