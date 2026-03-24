<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a value is safe for use in PZ config files (server.ini, SandboxVars.lua).
 * Uses an allowlist approach to prevent Lua code injection and INI newline injection.
 */
class SafeConfigValue implements ValidationRule
{
    /**
     * Allowlist of safe characters for PZ config values.
     * Permits: alphanumeric, spaces, commas, periods, colons, semicolons (PZ list separator),
     * slashes, hyphens, underscores, equals, plus, at, hash, exclamation, percent, caret,
     * asterisk, square brackets, single quotes, question marks.
     */
    private const SAFE_PATTERN = '/^[a-zA-Z0-9 ,.:;\/\-_=+@#!%^*\[\]\'?]+$/';

    /**
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $str = (string) $value;

        // Allow empty values (PZ uses empty values like Password=)
        if ($str === '') {
            return;
        }

        // Reject Lua concatenation operator
        if (str_contains($str, '..')) {
            $fail('The :attribute contains unsafe characters for config files.');

            return;
        }

        // Allowlist: only safe characters permitted
        if (! preg_match(self::SAFE_PATTERN, $str)) {
            $fail('The :attribute contains unsafe characters for config files.');
        }
    }
}
