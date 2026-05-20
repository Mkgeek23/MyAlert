<?php

declare(strict_types=1);

namespace MyAlert\Models;

use PDO;

/**
 * AlertDelivery Model
 *
 * Handles all database operations for alert delivery records.
 * Records the outcome of each Discord webhook delivery attempt.
 */
class AlertDelivery
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a new alert delivery record.
     *
     * @param array $data Associative array with keys: alert_id, status, http_status_code, response_body
     * @return int The newly created delivery record ID
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO alert_deliveries (alert_id, status, http_status_code, response_body)
             VALUES (:alert_id, :status, :http_status_code, :response_body)'
        );
        $stmt->execute([
            ':alert_id' => $data['alert_id'],
            ':status' => $data['status'],
            ':http_status_code' => $data['http_status_code'] ?? null,
            ':response_body' => $data['response_body'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Find delivery records for a user's alerts, sorted by sent_at DESC, paginated.
     *
     * Joins alert_deliveries with alerts to filter by user ownership
     * and includes the alert title in the result.
     *
     * @param int $userId The authenticated user's ID
     * @param int $page The page number (1-indexed)
     * @param int $perPage Number of records per page (default 20)
     * @return array Array of delivery records with alert title included
     */
    public function findByUser(int $userId, int $page, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare(
            'SELECT ad.id, ad.alert_id, ad.status, ad.http_status_code, ad.response_body, ad.sent_at,
                    a.title AS alert_title
             FROM alert_deliveries ad
             INNER JOIN alerts a ON ad.alert_id = a.id
             WHERE a.user_id = :user_id
             ORDER BY ad.sent_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Count total delivery records for a user's alerts.
     *
     * @param int $userId The user's ID
     * @return int Total count of delivery records
     */
    public function countByUser(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM alert_deliveries ad
             INNER JOIN alerts a ON ad.alert_id = a.id
             WHERE a.user_id = :user_id'
        );
        $stmt->execute([':user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }
}
