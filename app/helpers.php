<?php

/**
 * Global helper functions for HTE-CLI.
 *
 * Only pure functions with no side effects are kept here.
 * Filesystem and process operations have been moved to service classes
 * (SystemFilesystem, SystemProcessExecutor).
 */

if (!function_exists('validate_domain')) {
    /**
     * Validate a domain name and return its lowercase version.
     *
     * Accepts valid domain names (e.g., "example.com", "sub.domain.co.uk")
     * and IP addresses. Returns null if the input is not a valid domain.
     *
     * @param string $domain The domain name or IP address to validate.
     *
     * @return string|null The lowercase domain on success, null on failure.
     */
    function validate_domain(string $domain)
    {
        $valid = preg_match('/^(?!-)([a-z0-9-]*[a-z0-9]+(\.[a-z0-9-]*[a-z0-9]+)*\.[a-z]{2,})$/i', $domain) === 1
            || filter_var($domain, FILTER_VALIDATE_IP);

        return $valid ? mb_strtolower($domain) : null;
    }
}

if (!function_exists('answer_to_bool')) {
    /**
     * Convert a user answer to a boolean value.
     *
     * Recognizes "1", "y", "yes", "true" (case-insensitive) as true,
     * integer 1 as true, integer 0 as false, and boolean values as-is.
     * Returns null if the value cannot be interpreted as a boolean.
     *
     * @param mixed $value The user input to convert.
     *
     * @return bool|null A boolean if convertible, null otherwise.
     */
    function answer_to_bool($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower($value);
            if (in_array($value, ['1', 'y', 'yes', 'true'])) {
                return true;
            }
        }

        if (is_int($value)) {
            return intval($value) === 1;
        }

        return null;
    }
}
