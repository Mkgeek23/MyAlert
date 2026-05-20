<?php

declare(strict_types=1);

namespace MyAlert\Models;

use PDO;
use RuntimeException;

/**
 * Webhook Model
 *
 * Handles all database operations for Discord webhooks.
 * Enforces a maximum of 25 webhooks per user.
 */
class Webhook
{
    private const MAX_WEBHOOKS_PER_USER = 25;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a new webhook for a user.
     *
     * @param int $userId The owning user's ID
     * @param string $name The webhook display name
     * @param string $url The Discord webhook URL
     * @return int The newly created webhook ID
     * @throws RuntimeException If the user has reached the 25 webhook limit
     */
    public function create(int $userId, string $name, string $url): int
    {
        if ($this->countByUser($userId) >= self::MAX_WEBHOOKS_PER_USER) {
            throw new RuntimeException('Maximum webhook limit of 25 reached.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO webhooks (user_id, name, url) VALUES (:user_id, :name, :url)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':name' => $name,
            ':url' => $url,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Find all webhooks belonging to a user.
     *
     * @param int $userId The user's ID
     * @return array Array of webhook rows
     */
    public function findByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, name, url, created_at FROM webhooks WHERE user_id = :user_id ORDER BY created_at DESC'
        );
        $stmt->execute([':user_id' => $userId]);

        return $stmt->fetchAll();
    }

    /**
     * Find a webhook by its ID.
     *
     * @param int $id The webhook ID
     * @return array|null The webhook row or null if not found
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, name, url, created_at FROM webhooks WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);

        $result = $stmt->fetch();

        return $result !== false ? $result : null;
    }

    /**
     * Delete a webhook by its ID.
     *
     * @param int $id The webhook ID
     * @return bool True if a row was deleted
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM webhooks WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Count the number of webhooks for a user.
     *
     * @param int $userId The user's ID
     * @return int The webhook count
     */
    public function countByUser(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM webhooks WHERE user_id = :user_id'
        );
        $stmt->execute([':user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Check if a webhook name is unique for a given user.
     *
     * @param int $userId The user's ID
     * @param string $name The webhook name to check
     * @return bool True if no other webhook with this name exists for the user
     */
    public function isNameUnique(int $userId, string $name): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM webhooks WHERE user_id = :user_id AND name = :name'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':name' => $name,
        ]);

        return (int) $stmt->fetchColumn() === 0;
    }

    /**
     * Check if a webhook has any active alerts referencing it.
     *
     * @param int $id The webhook ID
     * @return bool True if active alerts reference this webhook
     */
    public function hasActiveAlerts(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM alerts WHERE webhook_id = :webhook_id AND status = 'active'"
        );
        $stmt->execute([':webhook_id' => $id]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
