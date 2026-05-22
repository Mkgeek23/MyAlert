<?php
/**
 * Delivery History Template
 *
 * Displays a paginated table of alert delivery records for the authenticated user.
 * Shows sent_at (formatted in app timezone), alert title, status badge, and HTTP code.
 *
 * Variables expected:
 *   $deliveries  - array of delivery records (with alert_title)
 *   $currentPage - int, current page number
 *   $totalPages  - int, total number of pages
 *   $totalCount  - int, total delivery records
 *   $config      - array, application configuration (for base_url, timezone)
 */

$baseUrl = rtrim($config['base_path'] ?? '', '/');
$timezone = $config['timezone'] ?? 'UTC';
?>

<h1 class="mb-4">Historia wysyłek</h1>

<?php if (empty($deliveries)): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <p class="text-muted mb-0">Brak historii wysyłek.</p>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Data wysłania</th>
                    <th>Tytuł alertu</th>
                    <th>Status</th>
                    <th>Kod HTTP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($deliveries as $delivery): ?>
                <?php
                    $sentAt = new \DateTime($delivery['sent_at'], new \DateTimeZone('UTC'));
                    $sentAt->setTimezone(new \DateTimeZone($timezone));
                    $formattedDate = $sentAt->format('d.m.Y H:i');

                    $isSuccess = $delivery['status'] === 'success';
                    $badgeClass = $isSuccess ? 'bg-success' : 'bg-danger';
                    $badgeText = $isSuccess ? 'Sukces' : 'Błąd';
                ?>
                <tr>
                    <td><?= htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($delivery['alert_title'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span></td>
                    <td><?= htmlspecialchars((string) ($delivery['http_status_code'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<nav aria-label="Paginacja historii wysyłek" class="mt-3">
    <ul class="pagination justify-content-center">
        <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/history?page=<?= (int) $currentPage - 1 ?>" aria-label="Poprzednia">
                <span aria-hidden="true">&laquo;</span> Poprzednia
            </a>
        </li>
        <li class="page-item disabled">
            <span class="page-link">Strona <?= (int) $currentPage ?> z <?= (int) $totalPages ?></span>
        </li>
        <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/history?page=<?= (int) $currentPage + 1 ?>" aria-label="Następna">
                Następna <span aria-hidden="true">&raquo;</span>
            </a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<p class="text-muted mt-2 small">Łącznie <?= (int) $totalCount ?> <?= $totalCount === 1 ? 'rekord' : 'rekordów' ?>.</p>
<?php endif; ?>
