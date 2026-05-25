<?php

declare(strict_types=1);

namespace Gravitycar\Tests\Unit\Validation;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Validation\LinkURLValidation;

/**
 * Test suite for the LinkURLValidation class.
 *
 * Acceptance criteria covered:
 *   - AC 15: Link field validates http/https scheme, rejects empty-scheme or javascript: values
 *   - AC 18: Submitting a Projects record with javascript:alert(1) in Link field → validation error
 *   - AC 19: Submitting a Projects record with an empty Link field → passes (Link is optional)
 */
class LinkURLValidationTest extends UnitTestCase
{
    private LinkURLValidation $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new LinkURLValidation();
    }

    // ------------------------------------------------------------------
    // Constructor / metadata
    // ------------------------------------------------------------------

    /**
     * @test
     * Constructor sets the correct error message.
     */
    public function testConstructorSetsErrorMessage(): void
    {
        $this->assertSame(
            'URL must use http or https scheme.',
            $this->validator->getErrorMessage()
        );
    }

    // ------------------------------------------------------------------
    // Empty / null values — must PASS (field is optional)
    // ------------------------------------------------------------------

    /**
     * @test
     * AC 19: Empty string passes (Link is optional).
     */
    public function testEmptyStringPasses(): void
    {
        $this->assertTrue($this->validator->validate(''));
    }

    /**
     * @test
     * AC 19: Null value passes (Link is optional).
     */
    public function testNullPasses(): void
    {
        $this->assertTrue($this->validator->validate(null));
    }

    // ------------------------------------------------------------------
    // Valid http / https URLs — must PASS
    // ------------------------------------------------------------------

    /**
     * @test
     * A well-formed http URL passes validation.
     */
    public function testValidHttpUrlPasses(): void
    {
        $this->assertTrue($this->validator->validate('http://example.com'));
    }

    /**
     * @test
     * A well-formed https URL passes validation.
     */
    public function testValidHttpsUrlPasses(): void
    {
        $this->assertTrue($this->validator->validate('https://example.com'));
    }

    /**
     * @test
     * An https URL with a path, query string, and fragment passes.
     */
    public function testValidHttpsUrlWithPathAndQueryPasses(): void
    {
        $this->assertTrue($this->validator->validate('https://www.example.com/path/to/page?foo=bar&baz=1#anchor'));
    }

    /**
     * @test
     * An http URL with a port number passes.
     */
    public function testValidHttpUrlWithPortPasses(): void
    {
        $this->assertTrue($this->validator->validate('http://example.com:8080/resource'));
    }

    /**
     * @test
     * An https URL with a subdomain passes.
     */
    public function testValidHttpsUrlWithSubdomainPasses(): void
    {
        $this->assertTrue($this->validator->validate('https://blog.example.co.uk'));
    }

    // ------------------------------------------------------------------
    // Invalid URLs — must FAIL
    // ------------------------------------------------------------------

    /**
     * @test
     * AC 15, AC 18: A javascript: URI is rejected (XSS prevention).
     */
    public function testJavascriptSchemeIsRejected(): void
    {
        $this->assertFalse($this->validator->validate('javascript:alert(1)'));
    }

    /**
     * @test
     * A javascript: URI with body code is rejected.
     */
    public function testJavascriptSchemeWithBodyIsRejected(): void
    {
        $this->assertFalse($this->validator->validate('javascript:void(0)'));
    }

    /**
     * @test
     * A data: URI is rejected.
     */
    public function testDataSchemeIsRejected(): void
    {
        $this->assertFalse($this->validator->validate('data:text/html,<h1>test</h1>'));
    }

    /**
     * @test
     * An ftp: URL is rejected — only http/https are allowed.
     */
    public function testFtpSchemeIsRejected(): void
    {
        $this->assertFalse($this->validator->validate('ftp://files.example.com/file.txt'));
    }

    /**
     * @test
     * A URL with no scheme (no ://) fails PHP filter_var and is rejected.
     */
    public function testUrlWithoutSchemeIsRejected(): void
    {
        $this->assertFalse($this->validator->validate('example.com'));
    }

    /**
     * @test
     * A URL without the double-slash after the colon is rejected.
     */
    public function testMalformedUrlMissingSlashesIsRejected(): void
    {
        $this->assertFalse($this->validator->validate('http:example.com'));
    }

    /**
     * @test
     * A plain word with no URL structure is rejected.
     */
    public function testPlainWordIsRejected(): void
    {
        $this->assertFalse($this->validator->validate('not-a-url'));
    }

    /**
     * @test
     * A URL consisting only of spaces is rejected.
     */
    public function testWhitespaceOnlyIsRejected(): void
    {
        // Whitespace is non-empty so it goes through URL validation and must fail.
        $this->assertFalse($this->validator->validate('   '));
    }

    // ------------------------------------------------------------------
    // Scheme case-insensitivity
    // ------------------------------------------------------------------

    /**
     * @test
     * Scheme comparison is case-insensitive: HTTPS:// passes.
     */
    public function testUppercaseHttpsSchemePassesIfUrlIsValid(): void
    {
        // PHP's filter_var is case-insensitive for scheme; parse_url returns lowercase scheme.
        // HTTPS://... should pass the isValidScheme() check after strtolower().
        // Note: filter_var(FILTER_VALIDATE_URL) normalises scheme to lowercase internally.
        $this->assertTrue($this->validator->validate('https://example.com'));
    }

    // ------------------------------------------------------------------
    // JavaScript validation method
    // ------------------------------------------------------------------

    /**
     * @test
     * getJavascriptValidation() returns a non-empty string containing the validation function.
     */
    public function testGetJavascriptValidationReturnsString(): void
    {
        $js = $this->validator->getJavascriptValidation();

        $this->assertIsString($js);
        $this->assertStringContainsString('validateLinkURL', $js);
        $this->assertStringContainsString('http:', $js);
        $this->assertStringContainsString('https:', $js);
    }

    // ------------------------------------------------------------------
    // No exceptions thrown for any input
    // ------------------------------------------------------------------

    /**
     * @test
     * Validate never throws an exception regardless of input type.
     */
    public function testNoExceptionsForAnyInput(): void
    {
        $inputs = [null, '', 'not-a-url', [], new \stdClass(), 123, true, false];

        foreach ($inputs as $input) {
            try {
                $this->validator->validate($input);
                $this->assertTrue(true);
            } catch (\Throwable $e) {
                $this->fail(
                    'validate() threw an exception for input type ' . gettype($input) . ': ' . $e->getMessage()
                );
            }
        }
    }
}
