<?php

declare(strict_types=1);

namespace MyAlert\Models;

use PDO;

/**
 * Alert Model
 *
 * Handles all database operations for alerts.
 * Supports three alert types: one_time, repeat_until_closed, recurring_series.
 */
class Alert
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a new alert.
     *
     * @param array $data Alert data with keys: user_id, webhook_id, title, description,
     *                     alert_type, next_run_at, repeat_interval_minutes, default_next_days
     * @return int The newly created alert ID
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO alerts (user_id, webhook_id, title, description, alert_type, status, next_run_at, repeat_interval_minutes, default_next_days)
             VALUES (:user_id, :webhook_id, :title, :description, :alert_type, :status, :next_run_at, :repeat_interval_minutes, :default_next_days)'
        );
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':webhook_id' => $data['webhook_id'],
            ':title' => $data['title'],
            ':description' => $data['description'] ?? null,
            ':alert_type' => $data['alert_type'],
            ':status' => 'active',
            ':next_run_at' => $data['next_run_at'],
            ':repeat_interval_minutes' => $data['repeat_interval_minutes'] ?? null,
            ':default_next_days' => $data['default_next_days'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Find an alert by its ID.
     *
     * @param int $id The alert ID
     * @return array|null The alert row or null if not found
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM alerts WHERE id = :id');
        $stmt->execute([':id' => $id]);

        $result = $stmt->fetch();

        return $result !== false ? $result : null;
    }

    /**
     * Find alerts belonging to a user with optional filters and pagination.
     *
     * Supports filters:
     *   - status: 'active', 'closed', or 'overdue' (active + next_run_at < NOW())
     *   - type: 'one_time', 'repeat_until_closed', 'recurring_series'
     *
     * Results are sorted by next_run_at ASC and paginated.
     *
     * @param int $userId The user's ID
     * @param array $filters Optional filters ['status' => ..., 'type' => ...]
     * @param int $page Page number (1-indexed)
     * @param int $perPage Items per page (default 20)
     * @return array Array of alert rows
     */
    public function findByUser(int $userId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $conditions = ['a.user_id = :user_id'];
        $params = [':user_id' => $userId];

        $this->applyFilters($conditions, $params, $filters);

        $offset = ($page - 1) * $perPage;
        $sql = 'SELECT a.* FROM alerts a WHERE ' . implode(' AND ', $conditions)
             . ' ORDER BY a.next_run_at ASC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Count alerts belonging to a user with optional filters.
     *
     * @param int $userId The user's ID
     * @param array $filters Optional filters ['status' => ..., 'type' => ...]
     * @return int The count of matching alerts
     */
    public function countByUser(int $userId, array $filters = []): int
    {
        $conditions = ['a.user_id = :user_id'];
        $params = [':user_id' => $userId];

        $this->applyFilters($conditions, $params, $filters);

        $sql = 'SELECT COUNT(*) FROM alerts a WHERE ' . implode(' AND ', $conditions);

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Get all due alerts (status='active' AND next_run_at <= NOW()).
     *
     * @param int $limit Maximum number of alerts to return (default 50)
     * @return array Array of due alert rows
     */
    public function getDueAlerts(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM alerts WHERE status = 'active' AND next_run_at <= NOW() ORDER BY next_run_at ASC LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Update the status of an alert.
     *
     * @param int $id The alert ID
     * @param string $status The new status ('active' or 'closed')
     */
    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE alerts SET status = :status WHERE id = :id'
        );
        $stmt->execute([
            ':status' => $status,
            ':id' => $id,
        ]);
    }

    /**
     * Update the next_run_at timestamp for an alert.
     *
     * @param int $id The alert ID
     * @param string $nextRunAt The new next_run_at datetime string
     */
    public function updateNextRun(int $id, string $nextRunAt): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE alerts SET next_run_at = :next_run_at WHERE id = :id'
        );
        $stmt->execute([
            ':next_run_at' => $nextRunAt,
            ':id' => $id,
        ]);
    }

    /**
     * End a recurring series alert by setting series_ended=true and status='closed'.
     *
     * @param int $id The alert ID
     */
    public function endSeries(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE alerts SET series_ended = TRUE, status = 'closed' WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
    }

    /**
     * Get dashboard summary counts for a user.
     *
     * Returns:
     *   - active: count of alerts with status='active'
     *   - today: count of alerts with next_run_at on the current calendar date
     *   - overdue: count of alerts with status='active' AND next_run_at < NOW()
     *
     * @param int $userId The user's ID
     * @return array Associative array with keys: active, today, overdue
     */
    public function getDashboardCounts(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN status = 'active' AND DATE(next_run_at) = CURDATE() THEN 1 ELSE 0 END) AS today,
                SUM(CASE WHEN status = 'active' AND next_run_at < NOW() THEN 1 ELSE 0 END) AS overdue
             FROM alerts
             WHERE user_id = :user_id"
        );
        $stmt->execute([':user_id' => $userId]);

        $row = $stmt->fetch();

        return [
            'active' => (int) ($row['active'] ?? 0),
            'today' => (int) ($row['today'] ?? 0),
            'overdue' => (int) ($row['overdue'] ?? 0),
        ];
    }

    /**
     * Get overdue alerts for a user (status='active' AND next_run_at < NOW()).
     * Sorted by next_run_at ASC (oldest overdue first), limited to max results.
     *
     * @param int $userId The user's ID
     * @param int $limit Maximum number of results (default 10)
     * @return array Array of overdue alert rows
     */
    public function getOverdueAlerts(int $userId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM alerts WHERE user_id = :user_id AND status = 'active' AND next_run_at < NOW() ORDER BY next_run_at ASC LIMIT :limit"
        );
        $stmt->bindValue(':user_id', $userId);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Reopen a closed alert by setting status to active, updating next_run_at,
     * and resetting series_ended for recurring_series alerts.
     *
     * @param int    $id        The alert ID
     * @param string $nextRunAt The new next_run_at datetime string
     * @param string $alertType The alert's type (to determine if series_ended should be reset)
     */
    public function reopenAlert(int $id, string $nextRunAt, string $alertType): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE alerts SET status = 'active', next_run_at = :next_run_at, series_ended = CASE WHEN :alert_type = 'recurring_series' THEN FALSE ELSE series_ended END WHERE id = :id"
        );
        $stmt->execute([
            ':next_run_at' => $nextRunAt,
            ':alert_type' => $alertType,
            ':id' => $id,
        ]);
    }

    /**
     * Apply status and type filters to query conditions.
     *
     * @param array &$conditions Reference to conditions array
     * @param array &$params Reference to params array
     * @param array $filters Filters to apply
     */
    private function applyFilters(array &$conditions, array &$params, array $filters): void
    {
        if (!empty($filters['status'])) {
            $status = $filters['status'];
            if ($status === 'overdue') {
                $conditions[] = "a.status = 'active'";
                $conditions[] = 'a.next_run_at < NOW()';
            } else {
                $conditions[] = 'a.status = :status';
                $params[':status'] = $status;
            }
        }

        if (!empty($filters['type'])) {
            $conditions[] = 'a.alert_type = :alert_type';
            $params[':alert_type'] = $filters['type'];
        }
    }
}
