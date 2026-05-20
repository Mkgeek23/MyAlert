<?php
/**
 * Alerts List Template
 *
 * Displays the user's alerts with filter controls, paginated list,
 * and action buttons. Supports filtering by status and type.
 *
 * Variables expected:
 *   $alerts      - array of alert rows
 *   $filters     - array of active filters ['status' => ..., 'type' => ...]
 *   $currentPage - int, current page number
 *   $totalPages  - int, total number of pages
 *   $totalCount  - int, total number of matching alerts
 *   $config      - array, application configuration (for base_url)
 */

$baseUrl = rtrim($config['base_path'] ?? '', '/');
$currentStatus = $filters['status'] ?? '';
$currentType = $filters['type'] ?? '';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Alerts</h1>
    <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/alerts-create" class="btn btn-primary d-none d-md-inline-block">New Alert</a>
</div>

<!-- Filter Controls -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/alerts" id="filter-form">
            <div class="row g-3 align-items-end">
                <div class="col-sm-6 col-md-4">
                    <label for="filter-status" class="form-label">Status</label>
                    <select name="status" id="filter-status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="active"<?= $currentStatus === 'active' ? ' selected' : '' ?>>Active</option>
                        <option value="closed"<?= $currentStatus === 'closed' ? ' selected' : '' ?>>Closed</option>
                        <option value="overdue"<?= $currentStatus === 'overdue' ? ' selected' : '' ?>>Overdue</option>
                    </select>
                </div>
                <div class="col-sm-6 col-md-4">
                    <label for="filter-type" class="form-label">Type</label>
                    <select name="type" id="filter-type" class="form-select" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        <option value="one_time"<?= $currentType === 'one_time' ? ' selected' : '' ?>>One-Time</option>
                        <option value="repeat_until_closed"<?= $currentType === 'repeat_until_closed' ? ' selected' : '' ?>>Repeat</option>
                        <option value="recurring_series"<?= $currentType === 'recurring_series' ? ' selected' : '' ?>>Series</option>
                    </select>
                </div>
                <div class="col-sm-12 col-md-4">
                    <noscript><button type="submit" class="btn btn-secondary w-100">Apply Filters</button></noscript>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (empty($alerts)): ?>
<!-- No Alerts Found -->
<div class="card">
    <div class="card-body text-center py-5">
        <p class="text-muted mb-3">No alerts found for the current filter selection.</p>
        <?php if (!empty($filters)): ?>
        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/alerts" class="btn btn-outline-secondary me-2">Clear Filters</a>
        <?php endif; ?>
        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/alerts-create" class="btn btn-outline-primary">Create New Alert</a>
    </div>
</div>
<?php else: ?>
<!-- Alerts List -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Title</th>
                    <th>Next Run</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($alerts as $alert): ?>
                <?php
                    // Determine display status (overdue if active and next_run_at is past)
                    $isOverdue = ($alert['status'] === 'active' && strtotime($alert['next_run_at']) < time());
                    $displayStatus = $isOverdue ? 'overdue' : $alert['status'];
                ?>
                <tr>
                    <td>
                        <?php if ($alert['alert_type'] === 'one_time'): ?>
                            <span class="badge bg-info">One-Time</span>
                        <?php elseif ($alert['alert_type'] === 'repeat_until_closed'): ?>
                            <span class="badge bg-warning text-dark">Repeat</span>
                        <?php elseif ($alert['alert_type'] === 'recurring_series'): ?>
                            <span class="badge bg-purple text-white" style="background-color: #6f42c1;">Series</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($displayStatus === 'active'): ?>
                            <span class="badge bg-success">Active</span>
                        <?php elseif ($displayStatus === 'closed'): ?>
                            <span class="badge bg-secondary">Closed</span>
                        <?php elseif ($displayStatus === 'overdue'): ?>
                            <span class="badge bg-danger">Overdue</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($alert['title'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if (!empty($alert['next_run_at'])): ?>
                            <?= htmlspecialchars(date('M j, Y g:i A', strtotime($alert['next_run_at'])), ENT_QUOTES, 'UTF-8') ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/alerts-edit?id=<?= htmlspecialchars((string) $alert['id'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-primary">View</a>
                        <?php if ($displayStatus === 'active' || $displayStatus === 'overdue'): ?>
                            <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/alerts-edit?id=<?= htmlspecialchars((string) $alert['id'], ENT_QUOTES, 'UTF-8') ?>&action=close" class="btn btn-sm btn-outline-danger">Close</a>
                        <?php endif; ?>
                        <?php if ($displayStatus === 'closed'): ?>
                            <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/alerts-edit?id=<?= htmlspecialchars((string) $alert['id'], ENT_QUOTES, 'UTF-8') ?>&action=reopen" class="btn btn-sm btn-outline-success">Reopen</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<?php
    // Build base URL for pagination links preserving current filters
    $paginationParams = [];
    if (!empty($currentStatus)) {
        $paginationParams['status'] = $currentStatus;
    }
    if (!empty($currentType)) {
        $paginationParams['type'] = $currentType;
    }
?>
<nav aria-label="Alerts pagination" class="mt-3">
    <ul class="pagination justify-content-center">
        <!-- Previous Button -->
        <li class="page-item<?= $currentPage <= 1 ? ' disabled' : '' ?>">
            <?php
                $prevParams = array_merge($paginationParams, ['p' => $currentPage - 1]);
                $prevUrl = $baseUrl . '/alerts?' . http_build_query($prevParams);
            ?>
            <a class="page-link" href="<?= htmlspecialchars($prevUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Previous">
                <span aria-hidden="true">&laquo;</span>
            </a>
        </li>

        <!-- Page Numbers -->
        <?php
            // Show a window of pages around the current page
            $startPage = max(1, $currentPage - 2);
            $endPage = min($totalPages, $currentPage + 2);

            if ($startPage > 1): ?>
                <?php
                    $firstParams = array_merge($paginationParams, ['p' => 1]);
                    $firstUrl = $baseUrl . '/alerts?' . http_build_query($firstParams);
                ?>
                <li class="page-item">
                    <a class="page-link" href="<?= htmlspecialchars($firstUrl, ENT_QUOTES, 'UTF-8') ?>">1</a>
                </li>
                <?php if ($startPage > 2): ?>
                    <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php endif; ?>
            <?php endif; ?>

        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
            <?php
                $pageParams = array_merge($paginationParams, ['p' => $i]);
                $pageUrl = $baseUrl . '/alerts?' . http_build_query($pageParams);
            ?>
            <li class="page-item<?= $i === $currentPage ? ' active' : '' ?>">
                <a class="page-link" href="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>"><?= (int) $i ?></a>
            </li>
        <?php endfor; ?>

            <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?>
                    <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php endif; ?>
                <?php
                    $lastParams = array_merge($paginationParams, ['p' => $totalPages]);
                    $lastUrl = $baseUrl . '/alerts?' . http_build_query($lastParams);
                ?>
                <li class="page-item">
                    <a class="page-link" href="<?= htmlspecialchars($lastUrl, ENT_QUOTES, 'UTF-8') ?>"><?= (int) $totalPages ?></a>
                </li>
            <?php endif; ?>

        <!-- Next Button -->
        <li class="page-item<?= $currentPage >= $totalPages ? ' disabled' : '' ?>">
            <?php
                $nextParams = array_merge($paginationParams, ['p' => $currentPage + 1]);
                $nextUrl = $baseUrl . '/alerts?' . http_build_query($nextParams);
            ?>
            <a class="page-link" href="<?= htmlspecialchars($nextUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Next">
                <span aria-hidden="true">&raquo;</span>
            </a>
        </li>
    </ul>
</nav>
<p class="text-center text-muted small">Showing page <?= (int) $currentPage ?> of <?= (int) $totalPages ?> (<?= (int) $totalCount ?> alerts total)</p>
<?php endif; ?>
<?php endif; ?>

<!-- Floating Action Button for Mobile -->
<a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/alerts-create" class="fab d-md-none" aria-label="Create new alert">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
</a>
