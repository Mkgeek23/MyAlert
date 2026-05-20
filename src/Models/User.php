<?php

declare(strict_types=1);

namespace MyAlert\Models;

use PDO;

class User
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a new user with email and password hash.
     *
     * @return int The new user's ID
     */
    public function create(string $email, string $passwordHash): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, password_hash) VALUES (:email, :password_hash)'
        );
        $stmt->execute([
            ':email' => $email,
            ':password_hash' => $passwordHash,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Find a user by email address.
     *
     * @return array|null User row or null if not found
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);

        $user = $stmt->fetch();

        return $user !== false ? $user : null;
    }

    /**
     * Find a user by ID.
     *
     * @return array|null User row or null if not found
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);

        $user = $stmt->fetch();

        return $user !== false ? $user : null;
    }

    /**
     * Increment failed login attempts for a user.
     * If count reaches 5, lock the account for 15 minutes.
     */
    public function incrementFailedAttempts(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = :id'
        );
        $stmt->execute([':id' => $userId]);

        // Check if threshold reached and lock if necessary
        $stmt = $this->pdo->prepare(
            'SELECT failed_login_attempts FROM users WHERE id = :id'
        );
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();

        if ($row && (int) $row['failed_login_attempts'] >= 5) {
            $lockedUntil = date('Y-m-d H:i:s', time() + 900); // 15 minutes
            $stmt = $this->pdo->prepare(
                'UPDATE users SET locked_until = :locked_until WHERE id = :id'
            );
            $stmt->execute([
                ':locked_until' => $lockedUntil,
                ':id' => $userId,
            ]);
        }
    }

    /**
     * Reset failed login attempts and clear lockout.
     */
    public function resetFailedAttempts(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = :id'
        );
        $stmt->execute([':id' => $userId]);
    }

    /**
     * Check if a user account is currently locked.
     *
     * @return bool True if locked_until is in the future
     */
    public function isLocked(int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT locked_until FROM users WHERE id = :id'
        );
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();

        if (!$row || $row['locked_until'] === null) {
            return false;
        }

        return strtotime($row['locked_until']) > time();
    }
}
