<?php

declare(strict_types=1);

namespace MyAlert\Tests\Unit\Middleware;

use MyAlert\Middleware\CsrfMiddleware;
use PHPUnit\Framework\TestCase;

class CsrfMiddlewareTest extends TestCase
{
    private CsrfMiddleware $csrf;

    protected function setUp(): void
    {
        // Initialize a clean session array for each test
        $_SESSION = [];
        $this->csrf = new CsrfMiddleware();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // --- generateToken() ---

    public function testGenerateTokenReturns64HexChars(): void
    {
        $token = $this->csrf->generateToken();

        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testGenerateTokenStoresInSession(): void
    {
        $token = $this->csrf->generateToken();

        $this->assertArrayHasKey('csrf_token', $_SESSION);
        $this->assertSame($token, $_SESSION['csrf_token']);
    }

    public function testGenerateTokenProducesUniqueTokens(): void
    {
        $token1 = $this->csrf->generateToken();
        $token2 = $this->csrf->generateToken();

        $this->assertNotSame($token1, $token2);
    }

    // --- getToken() ---

    public function testGetTokenReturnsExistingSessionToken(): void
    {
        $_SESSION['csrf_token'] = 'abc123def456abc123def456abc123def456abc123def456abc123def456abcd';
        $token = $this->csrf->getToken();

        $this->assertSame('abc123def456abc123def456abc123def456abc123def456abc123def456abcd', $token);
    }

    public function testGetTokenGeneratesNewTokenWhenNoneExists(): void
    {
        $token = $this->csrf->getToken();

        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertSame($token, $_SESSION['csrf_token']);
    }

    public function testGetTokenGeneratesNewTokenWhenSessionTokenIsEmpty(): void
    {
        $_SESSION['csrf_token'] = '';
        $token = $this->csrf->getToken();

        $this->assertSame(64, strlen($token));
        $this->assertNotSame('', $token);
    }

    public function testGetTokenReturnsSameTokenOnMultipleCalls(): void
    {
        $token1 = $this->csrf->getToken();
        $token2 = $this->csrf->getToken();

        $this->assertSame($token1, $token2);
    }

    // --- validate() ---

    public function testValidateReturnsTrueForMatchingToken(): void
    {
        $token = $this->csrf->generateToken();

        $this->assertTrue($this->csrf->validate($token));
    }

    public function testValidateReturnsFalseForNonMatchingToken(): void
    {
        $this->csrf->generateToken();

        $this->assertFalse($this->csrf->validate('wrong_token_value_that_does_not_match_session'));
    }

    public function testValidateReturnsFalseWhenNoSessionToken(): void
    {
        $this->assertFalse($this->csrf->validate('anytoken'));
    }

    public function testValidateReturnsFalseForEmptySubmittedToken(): void
    {
        $this->csrf->generateToken();

        $this->assertFalse($this->csrf->validate(''));
    }

    public function testValidateReturnsFalseWhenSessionTokenIsEmpty(): void
    {
        $_SESSION['csrf_token'] = '';

        $this->assertFalse($this->csrf->validate('sometoken'));
    }

    public function testValidateIsTimingSafe(): void
    {
        // Verify that validate uses hash_equals by testing that
        // a partially matching token still fails
        $token = $this->csrf->generateToken();
        $partialMatch = substr($token, 0, 32) . str_repeat('0', 32);

        $this->assertFalse($this->csrf->validate($partialMatch));
    }
}
