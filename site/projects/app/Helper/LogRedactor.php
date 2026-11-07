<?php

namespace App\Helper;

/**
 * LogRedactor — strips PII from API response data before logging.
 *
 * PMS responses include guest names, emails, phone numbers, and addresses.
 * Logging these verbatim means they end up in log files that may be
 * accessed by support staff or stored longer than necessary.
 *
 * Usage:
 *   Log::info('adapter response', LogRedactor::clean($responseArray));
 */
class LogRedactor
{
    /** Fields whose values are replaced with '[REDACTED]' at any nesting level */
    private const SENSITIVE_KEYS = [
        // Guest identity
        'email', 'phone', 'phoneNumber', 'phone_number', 'mobile',
        'firstName', 'first_name', 'lastName', 'last_name', 'fullName', 'full_name',
        'guestFirstName', 'guestLastName', 'guestEmail', 'guestPhone',
        'name', 'guest_name',
        // Payment
        'cardNumber', 'card_number', 'cvv', 'cvc',
        'accountNumber', 'account_number', 'routingNumber',
        // Addresses
        'address', 'address1', 'address2', 'street', 'city', 'zip', 'postalCode',
        'postal_code', 'state', 'country',
        // Auth (shouldn't be in responses but just in case)
        'password', 'token', 'access_token', 'refresh_token', 'secret',
    ];

    /**
     * Recursively redact sensitive keys from an array, then truncate
     * large arrays to prevent log bloat.
     *
     * @param  mixed $data   Any value — arrays are recursed, scalars returned as-is
     * @param  int   $maxDepth  Stop recursing below this depth (prevents stack overflow on deep responses)
     */
    public static function clean(mixed $data, int $maxDepth = 6): mixed
    {
        if (!is_array($data) || $maxDepth <= 0) {
            return $data;
        }

        $cleaned = [];
        foreach ($data as $key => $value) {
            if (in_array($key, self::SENSITIVE_KEYS, true)) {
                $cleaned[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $cleaned[$key] = self::clean($value, $maxDepth - 1);
            } else {
                $cleaned[$key] = $value;
            }
        }

        return $cleaned;
    }

    /**
     * Convenience wrapper: clean + truncate to a max number of top-level keys.
     * Prevents enormous 100-property sync responses from filling log files.
     */
    public static function summary(mixed $data, int $maxKeys = 20): mixed
    {
        $cleaned = self::clean($data);
        if (is_array($cleaned) && count($cleaned) > $maxKeys) {
            return array_slice($cleaned, 0, $maxKeys, true) + ['...' => '(' . (count($cleaned) - $maxKeys) . ' more keys truncated)'];
        }
        return $cleaned;
    }
}
