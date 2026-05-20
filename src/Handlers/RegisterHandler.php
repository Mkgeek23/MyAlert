<?php

declare(strict_types=1);

namespace MyAlert\Handlers;

use MyAlert\Middleware\CsrfMiddleware;
use MyAlert\Models\User;
use MyAlert\Validation\Validator;
use PDO;

/**
 * Register Handler
 *
 * Handles GET (show registration form) and POST (process registration).
 * Validates email format, password length, and email uniqueness.
 * On success: creates user with bcrypt-hashed password and shows confirmation.
 * On failure: re-renders form with validation errors and preserved input.
 */
class RegisterHandler
{
    private array $config;
    private PDO $pdo;

    public function __construct(array $config, PDO $pdo)
    {
        $this->config = $config;
        $this->pdo = $pdo;
    }

    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
        } else {
            $this->handleGet();
        }
    }

    private function handleGet(array $errors = [], array $old = []): void
    {
        $csrf = new CsrfMiddleware();
        $csrfToken = $csrf->getToken();

        $pageTitle = 'Register';
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../templates/register.php';
        $content = ob_get_clean();

        require __DIR__ . '/../../templates/layout.php';
    }

    private function handlePost(): void
    {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $errors = [];
        $validator = new Validator();

        // Validate required fields
        if ($email === '') {
            $errors[] = 'Email is required.';
        }
        if ($password === '') {
            $errors[] = 'Password is required.';
        }

        // Validate email format (only if not empty)
        if ($email !== '' && empty($errors)) {
            $emailResult = $validator->validateEmail($email);
            if (!$emailResult['valid']) {
                $errors = array_merge($errors, $emailResult['errors']);
            }
        }

        // Validate password length (only if not empty)
        if ($password !== '' && empty($errors)) {
            $passwordResult = $validator->validatePassword($password);
            if (!$passwordResult['valid']) {
                $errors = array_merge($errors, $passwordResult['errors']);
            }
        }

        // Check email uniqueness (only if no prior errors)
        if (empty($errors)) {
            $userModel = new User($this->pdo);
            $existingUser = $userModel->findByEmail($email);
            if ($existingUser !== null) {
                $errors[] = 'This email is already registered.';
            }
        }

        // If validation failed, re-render form with errors
        if (!empty($errors)) {
            $old = ['email' => $email];
            $this->handleGet($errors, $old);
            return;
        }

        // Create user with bcrypt-hashed password
        $userModel = new User($this->pdo);
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $userModel->create($email, $passwordHash);

        // Show success confirmation
        $this->showConfirmation($email);
    }

    private function showConfirmation(string $email): void
    {
        $pageTitle = 'Registration Successful';
        $config = $this->config;
        $registeredEmail = $email;

        ob_start();
        require __DIR__ . '/../../templates/register-success.php';
        $content = ob_get_clean();

        require __DIR__ . '/../../templates/layout.php';
    }
}
