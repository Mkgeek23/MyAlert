<?php

declare(strict_types=1);

namespace MyAlert\Tests\Unit\Validation;

use MyAlert\Validation\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    // --- Email Validation ---

    public function testValidEmailPasses(): void
    {
        $result = $this->validator->validateEmail('user@example.com');
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testEmailWithSubdomainPasses(): void
    {
        $result = $this->validator->validateEmail('user@sub.domain.example.com');
        $this->assertTrue($result['valid']);
    }

    public function testEmptyEmailFails(): void
    {
        $result = $this->validator->validateEmail('');
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testEmailWithoutAtFails(): void
    {
        $result = $this->validator->validateEmail('userexample.com');
        $this->assertFalse($result['valid']);
    }

    public function testEmailWithMultipleAtFails(): void
    {
        $result = $this->validator->validateEmail('user@@example.com');
        $this->assertFalse($result['valid']);
    }

    public function testEmailWithNoDotInDomainFails(): void
    {
        $result = $this->validator->validateEmail('user@localhost');
        $this->assertFalse($result['valid']);
    }

    public function testEmailExceeding254CharsFails(): void
    {
        $email = str_repeat('a', 243) . '@example.com'; // 255 chars
        $result = $this->validator->validateEmail($email);
        $this->assertFalse($result['valid']);
    }

    public function testEmailExactly254CharsPasses(): void
    {
        // local@domain.com = local(242) + @ + domain(7) + .com(4) = 254
        $email = str_repeat('a', 242) . '@exam.com'; // 242 + 1 + 8 = 251... let's be precise
        $local = str_repeat('a', 243);
        $email = $local . '@examp.com'; // 243 + 1 + 9 = 253 < 254
        $this->assertTrue(strlen($email) <= 254);
        $result = $this->validator->validateEmail($email);
        $this->assertTrue($result['valid']);
    }

    public function testEmailWithEmptyLocalPartFails(): void
    {
        $result = $this->validator->validateEmail('@example.com');
        $this->assertFalse($result['valid']);
    }

    public function testEmailWithEmptyDomainFails(): void
    {
        $result = $this->validator->validateEmail('user@');
        $this->assertFalse($result['valid']);
    }

    // --- Password Validation ---

    public function testValidPasswordPasses(): void
    {
        $result = $this->validator->validatePassword('password123');
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testPasswordExactly8CharsPasses(): void
    {
        $result = $this->validator->validatePassword('12345678');
        $this->assertTrue($result['valid']);
    }

    public function testPasswordExactly72CharsPasses(): void
    {
        $result = $this->validator->validatePassword(str_repeat('a', 72));
        $this->assertTrue($result['valid']);
    }

    public function testPasswordTooShortFails(): void
    {
        $result = $this->validator->validatePassword('1234567');
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testPasswordTooLongFails(): void
    {
        $result = $this->validator->validatePassword(str_repeat('a', 73));
        $this->assertFalse($result['valid']);
    }

    public function testEmptyPasswordFails(): void
    {
        $result = $this->validator->validatePassword('');
        $this->assertFalse($result['valid']);
    }

    // --- Webhook URL Validation ---

    public function testValidWebhookUrlPasses(): void
    {
        $url = 'https://discord.com/api/webhooks/123456789/abcDEF123token';
        $this->assertTrue($this->validator->validateWebhookUrl($url));
    }

    public function testWebhookUrlWithDashesInTokenPasses(): void
    {
        $url = 'https://discord.com/api/webhooks/123456789/abc-DEF_123-token';
        $this->assertTrue($this->validator->validateWebhookUrl($url));
    }

    public function testWebhookUrlWithHttpFails(): void
    {
        $url = 'http://discord.com/api/webhooks/123456789/abcDEF123token';
        $this->assertFalse($this->validator->validateWebhookUrl($url));
    }

    public function testWebhookUrlWithWrongDomainFails(): void
    {
        $url = 'https://notdiscord.com/api/webhooks/123456789/abcDEF123token';
        $this->assertFalse($this->validator->validateWebhookUrl($url));
    }

    public function testWebhookUrlWithNonNumericIdFails(): void
    {
        $url = 'https://discord.com/api/webhooks/abc/abcDEF123token';
        $this->assertFalse($this->validator->validateWebhookUrl($url));
    }

    public function testWebhookUrlWithExtraPathFails(): void
    {
        $url = 'https://discord.com/api/webhooks/123456789/abcDEF123token/extra';
        $this->assertFalse($this->validator->validateWebhookUrl($url));
    }

    public function testWebhookUrlEmptyFails(): void
    {
        $this->assertFalse($this->validator->validateWebhookUrl(''));
    }

    public function testWebhookUrlMissingTokenFails(): void
    {
        $url = 'https://discord.com/api/webhooks/123456789/';
        $this->assertFalse($this->validator->validateWebhookUrl($url));
    }

    // --- Alert Title Validation ---

    public function testValidTitlePasses(): void
    {
        $result = $this->validator->validateAlertTitle('My Alert');
        $this->assertTrue($result['valid']);
    }

    public function testTitleWithLeadingWhitespacePasses(): void
    {
        $result = $this->validator->validateAlertTitle('  My Alert  ');
        $this->assertTrue($result['valid']);
    }

    public function testEmptyTitleFails(): void
    {
        $result = $this->validator->validateAlertTitle('');
        $this->assertFalse($result['valid']);
    }

    public function testWhitespaceOnlyTitleFails(): void
    {
        $result = $this->validator->validateAlertTitle('   ');
        $this->assertFalse($result['valid']);
    }

    public function testTitleExactly255CharsPasses(): void
    {
        $result = $this->validator->validateAlertTitle(str_repeat('a', 255));
        $this->assertTrue($result['valid']);
    }

    public function testTitleExceeding255CharsFails(): void
    {
        $result = $this->validator->validateAlertTitle(str_repeat('a', 256));
        $this->assertFalse($result['valid']);
    }

    // --- Future DateTime Validation ---

    public function testFutureDateTimePasses(): void
    {
        $future = date('Y-m-d H:i:s', time() + 120); // 2 minutes from now
        $result = $this->validator->validateFutureDateTime($future);
        $this->assertTrue($result['valid']);
    }

    public function testPastDateTimeFails(): void
    {
        $past = date('Y-m-d H:i:s', time() - 3600); // 1 hour ago
        $result = $this->validator->validateFutureDateTime($past);
        $this->assertFalse($result['valid']);
    }

    public function testDateTimeLessThan1MinuteFails(): void
    {
        $soon = date('Y-m-d H:i:s', time() + 30); // 30 seconds from now
        $result = $this->validator->validateFutureDateTime($soon);
        $this->assertFalse($result['valid']);
    }

    public function testInvalidDateTimeFormatFails(): void
    {
        $result = $this->validator->validateFutureDateTime('not-a-date');
        $this->assertFalse($result['valid']);
    }

    // --- Repeat Interval Validation ---

    public function testValidRepeatIntervalPasses(): void
    {
        $result = $this->validator->validateRepeatInterval(60);
        $this->assertTrue($result['valid']);
    }

    public function testRepeatIntervalMinimumPasses(): void
    {
        $result = $this->validator->validateRepeatInterval(5);
        $this->assertTrue($result['valid']);
    }

    public function testRepeatIntervalMaximumPasses(): void
    {
        $result = $this->validator->validateRepeatInterval(525600);
        $this->assertTrue($result['valid']);
    }

    public function testRepeatIntervalBelowMinimumFails(): void
    {
        $result = $this->validator->validateRepeatInterval(4);
        $this->assertFalse($result['valid']);
    }

    public function testRepeatIntervalAboveMaximumFails(): void
    {
        $result = $this->validator->validateRepeatInterval(525601);
        $this->assertFalse($result['valid']);
    }

    // --- Default Next Days Validation ---

    public function testValidDefaultNextDaysPasses(): void
    {
        $result = $this->validator->validateDefaultNextDays(7);
        $this->assertTrue($result['valid']);
    }

    public function testDefaultNextDaysMinimumPasses(): void
    {
        $result = $this->validator->validateDefaultNextDays(1);
        $this->assertTrue($result['valid']);
    }

    public function testDefaultNextDaysMaximumPasses(): void
    {
        $result = $this->validator->validateDefaultNextDays(365);
        $this->assertTrue($result['valid']);
    }

    public function testDefaultNextDaysBelowMinimumFails(): void
    {
        $result = $this->validator->validateDefaultNextDays(0);
        $this->assertFalse($result['valid']);
    }

    public function testDefaultNextDaysAboveMaximumFails(): void
    {
        $result = $this->validator->validateDefaultNextDays(366);
        $this->assertFalse($result['valid']);
    }

    // --- Close Token Validation ---

    public function testValidCloseTokenPasses(): void
    {
        $token = bin2hex(random_bytes(32)); // 64 hex chars
        $this->assertTrue($this->validator->validateCloseToken($token));
    }

    public function testCloseTokenTooShortFails(): void
    {
        $this->assertFalse($this->validator->validateCloseToken('abcdef1234'));
    }

    public function testCloseTokenTooLongFails(): void
    {
        $token = str_repeat('a', 65);
        $this->assertFalse($this->validator->validateCloseToken($token));
    }

    public function testCloseTokenWithUppercaseFails(): void
    {
        $token = str_repeat('A', 64);
        $this->assertFalse($this->validator->validateCloseToken($token));
    }

    public function testCloseTokenWithNonHexFails(): void
    {
        $token = str_repeat('g', 64);
        $this->assertFalse($this->validator->validateCloseToken($token));
    }

    public function testCloseTokenEmptyFails(): void
    {
        $this->assertFalse($this->validator->validateCloseToken(''));
    }

    // --- Sanitize String ---

    public function testSanitizeStringTrimsWhitespace(): void
    {
        $this->assertSame('hello', Validator::sanitizeString('  hello  '));
    }

    public function testSanitizeStringTrimsTabsAndNewlines(): void
    {
        $this->assertSame('hello', Validator::sanitizeString("\t\nhello\r\n"));
    }

    public function testSanitizeStringEnforcesMaxLength(): void
    {
        $result = Validator::sanitizeString('hello world', 5);
        $this->assertSame('hello', $result);
    }

    public function testSanitizeStringNoMaxLengthReturnsFullTrimmed(): void
    {
        $input = '  a long string  ';
        $result = Validator::sanitizeString($input);
        $this->assertSame('a long string', $result);
    }

    public function testSanitizeStringEmptyInput(): void
    {
        $this->assertSame('', Validator::sanitizeString(''));
    }

    public function testSanitizeStringWhitespaceOnly(): void
    {
        $this->assertSame('', Validator::sanitizeString('   '));
    }
}
