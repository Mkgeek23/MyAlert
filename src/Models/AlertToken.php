<?php

declare(strict_types=1);

namespace MyAlert\Models;

use PDO;

class AlertToken
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a new alert token.
     *
     * @param int $alertId The alert this token belongs to
     * @param string $token The 64-character hex token string
     * @param string $expiresAt The expiration datetime string (Y-m-d H:i:s)
     * @return int The new token record's ID
     */
    public function create(int $alertId, string $token, string $expiresAt): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO alert_tokens (alert_id, token, expires_at) VALUES (:alert_id, :token, :expires_at)'
        );
        $stmt->execute([
            ':alert_id' => $alertId,
            ':token' => $token,
            ':expires_at' => $expiresAt,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Find a token record by its token string.
     *
     * @param string $token The 64-character hex token string
     * @return array|null The full token row or null if not found
     */
    public function findByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM alert_tokens WHERE token = :token');
        $stmt->execute([':token' => $token]);

        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Mark a token as used.
     *
     * @param int $id The token record ID
     */
    public function markUsed(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE alert_tokens SET used = 1 WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * Check if a token record is valid (not used and not expired).
     *
     * A token is valid when:
     * - used is false (0)
     * - expires_at is in the future (current time < expires_at)
     *
     * @param array $tokenRecord The full token row from the database
     * @return bool True if the token is valid
     */
    public function isValid(array $tokenRecord): bool
    {
        // Check if token has been used
        if (!empty($tokenRecord['used'])) {
            return false;
        }

        // Check if token has expired
        $expiresAt = strtotime($tokenRecord['expires_at']);
        if ($expiresAt === false) {
            return false;
        }

        return time() < $expiresAt;
    }
}
