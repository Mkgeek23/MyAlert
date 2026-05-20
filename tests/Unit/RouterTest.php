<?php

declare(strict_types=1);

namespace MyAlert\Tests\Unit;

use MyAlert\Router;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Router class.
 *
 * Tests register(), resolve(), and dispatch() methods.
 */
class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $config = [
            'base_path' => '/MyAlert/public',
            'timezone' => 'UTC',
        ];
        $pdo = $this->createMock(\PDO::class);
        $this->router = new Router($config, $pdo);
    }

    public function testRegisterAndResolveValidPage(): void
    {
        $this->router->register('dashboard', 'MyAlert\\Handlers\\DashboardHandler', true);

        $result = $this->router->resolve('dashboard');

        $this->assertNotNull($result);
        $this->assertSame('MyAlert\\Handlers\\DashboardHandler', $result[0]);
        $this->assertTrue($result[1]);
    }

    public function testResolveReturnsNullForUnregisteredPage(): void
    {
        $this->router->register('dashboard', 'MyAlert\\Handlers\\DashboardHandler');

        $result = $this->router->resolve('nonexistent');

        $this->assertNull($result);
    }

    public function testResolveReturnsNullForEmptyString(): void
    {
        $this->router->register('dashboard', 'MyAlert\\Handlers\\DashboardHandler');

        $result = $this->router->resolve('');

        $this->assertNull($result);
    }

    public function testRegisterWithAuthFalse(): void
    {
        $this->router->register('login', 'MyAlert\\Handlers\\LoginHandler', false);

        $result = $this->router->resolve('login');

        $this->assertNotNull($result);
        $this->assertSame('MyAlert\\Handlers\\LoginHandler', $result[0]);
        $this->assertFalse($result[1]);
    }

    public function testRegisterDefaultsToRequiresAuth(): void
    {
        $this->router->register('alerts', 'MyAlert\\Handlers\\AlertsHandler');

        $result = $this->router->resolve('alerts');

        $this->assertNotNull($result);
        $this->assertTrue($result[1]);
    }

    public function testAllValidPageNamesCanBeRegisteredAndResolved(): void
    {
        $validPages = [
            'dashboard' => 'MyAlert\\Handlers\\DashboardHandler',
            'login' => 'MyAlert\\Handlers\\LoginHandler',
            'register' => 'MyAlert\\Handlers\\RegisterHandler',
            'alerts' => 'MyAlert\\Handlers\\AlertsHandler',
            'alerts-create' => 'MyAlert\\Handlers\\AlertsCreateHandler',
            'alerts-edit' => 'MyAlert\\Handlers\\AlertsEditHandler',
            'webhooks' => 'MyAlert\\Handlers\\WebhooksHandler',
            'webhooks-create' => 'MyAlert\\Handlers\\WebhooksCreateHandler',
            'history' => 'MyAlert\\Handlers\\HistoryHandler',
            'close-alert' => 'MyAlert\\Handlers\\CloseAlertHandler',
        ];

        foreach ($validPages as $page => $handler) {
            $this->router->register($page, $handler);
        }

        foreach ($validPages as $page => $handler) {
            $result = $this->router->resolve($page);
            $this->assertNotNull($result, "Page '{$page}' should resolve to a handler");
            $this->assertSame($handler, $result[0]);
        }
    }

    public function testInvalidPageNamesReturnNull(): void
    {
        // Register all valid pages
        $validPages = [
            'dashboard', 'login', 'register', 'alerts',
            'alerts-create', 'alerts-edit', 'webhooks',
            'webhooks-create', 'history', 'close-alert',
        ];

        foreach ($validPages as $page) {
            $this->router->register($page, 'SomeHandler');
        }

        // Test invalid page names
        $invalidPages = ['admin', 'settings', 'profile', 'foo', 'alerts-delete', ''];

        foreach ($invalidPages as $page) {
            $result = $this->router->resolve($page);
            $this->assertNull($result, "Page '{$page}' should not resolve");
        }
    }

    public function testRegisterOverwritesPreviousHandler(): void
    {
        $this->router->register('dashboard', 'OldHandler', true);
        $this->router->register('dashboard', 'NewHandler', false);

        $result = $this->router->resolve('dashboard');

        $this->assertNotNull($result);
        $this->assertSame('NewHandler', $result[0]);
        $this->assertFalse($result[1]);
    }

    /**
     * @runInSeparateProcess
     */
    public function testDispatchSets404ForUnrecognizedPage(): void
    {
        $this->router->dispatch('nonexistent');

        $this->assertSame(404, http_response_code());
    }
}
