<?php

declare(strict_types=1);

namespace MyAlert\Handlers;

use MyAlert\Middleware\CsrfMiddleware;
use MyAlert\Models\User;
use PDO;

/**
 * Login Handler
 *
 * Handles GET (show login form) and POST (process login credentials).
 * Implements account lockout after 5 failed attempts within 15 minutes.
 * Regenerates session ID on successful authentication to prevent session fixation.
 */
class LoginHandler
{
    private array $config;
    private PDO $pdo;

    public function __construct(array $config, PDO $pdo)
    {
        $this->config = $config;
        $this->pdo = $pdo;
    }

    /**
     * Main request handler. Routes to GET or POST logic.
     */
    public function handle(): void
    {
        // If already logged in, redirect to dashboard
        if (isset($_SESSION['user_id'])) {
            $this->redirect('/dashboard');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
        } else {
            $this->handleGet();
        }
    }

    /**
     * Display the login form.
     */
    private function handleGet(): void
    {
        $csrf = new CsrfMiddleware();

        $this->render([
            'csrfToken' => $csrf->getToken(),
            'error' => '',
            'email' => '',
        ]);
    }

    /**
     * Process login form submission.
     *
     * Validates credentials with password_verify(), checks account lockout,
     * increments failed attempts on failure, and regenerates session on success.
     */
    private function handlePost(): void
    {
        $csrf = new CsrfMiddleware();
        $userModel = new User($this->pdo);

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Find user by email
        $user = $userModel->findByEmail($email);

        // If user exists, check lockout status
        if ($user !== null) {
            if ($userModel->isLocked((int) $user['id'])) {
                $this->render([
                    'csrfToken' => $csrf->getToken(),
                    'error' => 'Account is temporarily locked. Please try again later.',
                    'email' => $email,
                ]);
                return;
            }
        }

        // Verify credentials
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            // Increment failed attempts if user exists
            if ($user !== null) {
                $userModel->incrementFailedAttempts((int) $user['id']);
            }

            $this->render([
                'csrfToken' => $csrf->getToken(),
                'error' => 'Invalid email or password',
                'email' => $email,
            ]);
            return;
        }

        // Successful login: reset failed attempts
        $userModel->resetFailedAttempts((int) $user['id']);

        // Regenerate session ID to prevent session fixation attacks
        session_regenerate_id(true);

        // Store user data in session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['last_activity'] = time();

        $this->redirect('/dashboard');
    }

    /**
     * Render the login template within the layout.
     *
     * @param array $data Template variables
     */
    private function render(array $data): void
    {
        $pageTitle = 'Login';
        $config = $this->config;

        // Extract template variables
        $csrfToken = $data['csrfToken'];
        $error = $data['error'];
        $email = $data['email'];

        // Capture page content
        ob_start();
        require __DIR__ . '/../../templates/login.php';
        $content = ob_get_clean();

        // Render within layout
        require __DIR__ . '/../../templates/layout.php';
    }

    /**
     * Redirect to a relative URL using the configured base_url.
     */
    private function redirect(string $path): void
    {
        $baseUrl = rtrim($this->config['base_path'] ?? '', '/');
        header('Location: ' . $baseUrl . $path, true, 302);
        exit;
    }
}
