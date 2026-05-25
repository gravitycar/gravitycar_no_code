<?php
namespace Gravitycar\Validation;

/**
 * LinkURLValidation: Validates that a value is a well-formed URL using the
 * http or https scheme.
 *
 * Empty and null values pass validation — the Required rule handles emptiness.
 * Non-empty values must satisfy two conditions:
 *   1. PHP's filter_var FILTER_VALIDATE_URL accepts the value as a valid URL.
 *   2. The scheme extracted via parse_url is exactly 'http' or 'https'.
 *
 * This two-step approach blocks unsafe schemes such as javascript: and data:
 * while allowing all standards-compliant http/https URLs.
 */
class LinkURLValidation extends ValidationRuleBase
{
    /** @var array Schemes considered safe for link fields */
    private const ALLOWED_SCHEMES = ['http', 'https'];

    public function __construct()
    {
        parent::__construct('LinkURL', 'URL must use http or https scheme.');
    }

    /**
     * Validate that the value is empty or a well-formed http/https URL.
     *
     * @param mixed                           $value The value to validate
     * @param \Gravitycar\Models\ModelBase|null $model Optional model context (unused)
     * @return bool True if valid, false otherwise
     */
    public function validate($value, $model = null): bool
    {
        if (empty($value)) {
            return true;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);

        return $this->isValidScheme((string) $scheme);
    }

    /**
     * Check whether the given scheme is in the allowed list.
     * Comparison is case-insensitive to handle HTTPS:// style input.
     *
     * @param string $scheme The URL scheme to check
     * @return bool
     */
    private function isValidScheme(string $scheme): bool
    {
        return in_array(strtolower($scheme), self::ALLOWED_SCHEMES, true);
    }

    /**
     * Return JavaScript validation logic for client-side enforcement.
     * Mirrors the PHP logic: empty passes, then checks URL structure and scheme.
     *
     * @return string JavaScript function body as a string
     */
    public function getJavascriptValidation(): string
    {
        return "
        function validateLinkURL(value) {
            if (!value || value === '') {
                return { valid: true };
            }
            try {
                const url = new URL(value);
                if (url.protocol !== 'http:' && url.protocol !== 'https:') {
                    return { valid: false, message: 'URL must use http or https scheme.' };
                }
                return { valid: true };
            } catch (e) {
                return { valid: false, message: 'URL must use http or https scheme.' };
            }
        }";
    }
}
