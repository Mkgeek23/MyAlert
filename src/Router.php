<?php

declare(strict_types=1);

namespace MyAlert;

/**
 * Router
 *
 * Resolves the requested page from the URL and dispatches to the appropriate handler.
 * Supports URL parameter-based routing using index.php?page={page_name}.
 */
class Router
{
    /**
     * Registered routes.
     * Each entry: page_name => ['handler' => handlerClass, 'requiresAuth' => bool]
     *
     * @var array<string, array{handler: string, requiresAuth: bool}>
     */
    private array $routes = [];

    /**
     * Application configuration array.
     */
    private array $config;

    /**
     * PDO database connection.
     */
    private \PDO $pdo;

    /**
     * @param array $config Application configuration
     * @param \PDO  $pdo    Database connection
     */
    public function __construct(array $config, \PDO $pdo)
    {
        $this->config = $config;
        $this->pdo = $pdo;
    }

    /**
     * Register a route with its handler class and authentication requirement.
     *
     * @param string $page         The page name (e.g., 'dashboard', 'login')
     * @param string $handlerClass The fully-qualified handler class name
     * @param bool   $requiresAuth Whether this route requires authentication (default: true)
     */
    public function register(string $page, string $handlerClass, bool $requiresAuth = true): void
    {
        $this->routes[$page] = [
            'handler' => $handlerClass,
            'requiresAuth' => $requiresAuth,
        ];
    }

    /**
     * Resolve a page name to its handler configuration.
     *
     * @param string $pageName The page name to resolve
     * @return array{0: string, 1: bool}|null Returns [handlerClass, requiresAuth] or null if not found
     */
    public function resolve(string $pageName): ?array
    {
        if (!isset($this->routes[$pageName])) {
            return null;
        }

        $route = $this->routes[$pageName];

        return [$route['handler'], $route['requiresAuth']];
    }

    /**
     * Dispatch the request to the appropriate handler based on page name.
     *
     * Resolves the page, applies authentication middleware if required,
     * and instantiates/calls the handler. Returns 404 for unrecognized pages.
     *
     * @param string $pageName The page name to dispatch
     */
    public function dispatch(string $pageName): void
    {
        $resolved = $this->resolve($pageName);

        if ($resolved === null) {
            http_response_code(404);
            $templatePath = __DIR__ . '/../templates/errors/404.php';
            if (file_exists($templatePath)) {
                require $templatePath;
            }
            return;
        }

        [$handlerClass, $requiresAuth] = $resolved;

        if ($requiresAuth) {
            $authMiddleware = new Middleware\AuthMiddleware($this->config);
            $authMiddleware->enforce();
        }

        $handler = new $handlerClass($this->config, $this->pdo);
        $handler->handle();
    }
}
