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

<h1 class="mb-4">Delivery History</h1>

<?php if (empty($deliveries)): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <p class="text-muted mb-0">No delivery history available.</p>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Sent At</th>
                    <th>Alert Title</th>
                    <th>Status</th>
                    <th>HTTP Code</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($deliveries as $delivery): ?>
                <?php
                    $sentAt = new \DateTime($delivery['sent_at'], new \DateTimeZone('UTC'));
                    $sentAt->setTimezone(new \DateTimeZone($timezone));
                    $formattedDate = $sentAt->format('M j, Y g:i A');

                    $isSuccess = $delivery['status'] === 'success';
                    $badgeClass = $isSuccess ? 'bg-success' : 'bg-danger';
                    $badgeText = $isSuccess ? 'Success' : 'Failed';
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
<nav aria-label="Delivery history pagination" class="mt-3">
    <ul class="pagination justify-content-center">
        <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/history?page=<?= (int) $currentPage - 1 ?>" aria-label="Previous">
                <span aria-hidden="true">&laquo;</span> Previous
            </a>
        </li>
        <li class="page-item disabled">
            <span class="page-link">Page <?= (int) $currentPage ?> of <?= (int) $totalPages ?></span>
        </li>
        <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/history?page=<?= (int) $currentPage + 1 ?>" aria-label="Next">
                Next <span aria-hidden="true">&raquo;</span>
            </a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<p class="text-muted mt-2 small"><?= (int) $totalCount ?> total delivery record<?= $totalCount !== 1 ? 's' : '' ?>.</p>
<?php endif; ?>
