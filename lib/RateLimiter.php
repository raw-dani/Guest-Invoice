<?php

namespace GuestInvoice\Lib;

use WHMCS\Database\Capsule;

class RateLimiter
{
    /**
     * Check if the current IP address is permitted to perform the specified action.
     *
     * @param string $actionKey Action identifier (e.g. 'token_lookup', 'pay_attempt')
     * @param int $maxAttempts Max failed/requested attempts allowed before lockout
     * @param int $windowSeconds Rolling window timeframe in seconds
     * @return bool True if allowed, false if locked out or threshold exceeded
     */
    public static function isAllowed(string $actionKey = 'token_lookup', int $maxAttempts = 15, int $windowSeconds = 60): bool
    {
        try {
            $ip = AuditLogger::getClientIp();
            $salt = self::getRateLimitSalt();
            $ipHash = hash('sha256', $salt . $ip);

            $now = date('Y-m-d H:i:s');
            $record = Capsule::table('mod_guestinvoice_rate_limits')
                ->where('ip_hash', $ipHash)
                ->where('action_key', $actionKey)
                ->first();

            if (!$record) {
                return true;
            }

            // Check explicit lockout
            if (!empty($record->locked_until) && $record->locked_until > $now) {
                return false;
            }

            // Check if window expired; if so, count resets automatically on next hit
            $windowStart = date('Y-m-d H:i:s', strtotime("-{$windowSeconds} seconds"));
            if ($record->last_attempt_at < $windowStart) {
                return true;
            }

            return $record->attempt_count < $maxAttempts;
        } catch (\Throwable $e) {
            // Fail open on database error to avoid blocking legitimate customers
            return true;
        }
    }

    /**
     * Record an attempt for rate limiting and trigger lockout if limit reached.
     *
     * @param string $actionKey
     * @param int $maxAttempts
     * @param int $windowSeconds
     * @param int $lockoutMinutes
     */
    public static function recordAttempt(string $actionKey = 'token_lookup', int $maxAttempts = 15, int $windowSeconds = 60, int $lockoutMinutes = 15): void
    {
        try {
            $ip = AuditLogger::getClientIp();
            $salt = self::getRateLimitSalt();
            $ipHash = hash('sha256', $salt . $ip);

            $now = date('Y-m-d H:i:s');
            $windowStart = date('Y-m-d H:i:s', strtotime("-{$windowSeconds} seconds"));

            $record = Capsule::table('mod_guestinvoice_rate_limits')
                ->where('ip_hash', $ipHash)
                ->where('action_key', $actionKey)
                ->first();

            if (!$record) {
                Capsule::table('mod_guestinvoice_rate_limits')->insert([
                    'ip_hash' => $ipHash,
                    'action_key' => $actionKey,
                    'attempt_count' => 1,
                    'first_attempt_at' => $now,
                    'last_attempt_at' => $now,
                    'locked_until' => null,
                ]);
                return;
            }

            // If existing window has lapsed and not locked, reset attempt count to 1
            if ($record->last_attempt_at < $windowStart && (empty($record->locked_until) || $record->locked_until <= $now)) {
                Capsule::table('mod_guestinvoice_rate_limits')
                    ->where('id', $record->id)
                    ->update([
                        'attempt_count' => 1,
                        'first_attempt_at' => $now,
                        'last_attempt_at' => $now,
                        'locked_until' => null,
                    ]);
                return;
            }

            $newCount = $record->attempt_count + 1;
            $lockedUntil = $record->locked_until;

            if ($newCount >= $maxAttempts) {
                $lockedUntil = date('Y-m-d H:i:s', strtotime("+{$lockoutMinutes} minutes"));

                AuditLogger::log([
                    'event_type' => 'rate_limited',
                    'result' => 'denied',
                    'actor_type' => 'guest',
                    'details' => json_encode([
                        'action_key' => $actionKey,
                        'attempts' => $newCount,
                        'locked_until' => $lockedUntil,
                    ]),
                ]);
            }

            Capsule::table('mod_guestinvoice_rate_limits')
                ->where('id', $record->id)
                ->update([
                    'attempt_count' => $newCount,
                    'last_attempt_at' => $now,
                    'locked_until' => $lockedUntil,
                ]);
        } catch (\Throwable $e) {
            // Ignore DB errors in rate limit recording
        }
    }

    /**
     * Clear rate limit for an action upon successful authentication/flow.
     */
    public static function clear(string $actionKey = 'token_lookup'): void
    {
        try {
            $ip = AuditLogger::getClientIp();
            $salt = self::getRateLimitSalt();
            $ipHash = hash('sha256', $salt . $ip);

            Capsule::table('mod_guestinvoice_rate_limits')
                ->where('ip_hash', $ipHash)
                ->where('action_key', $actionKey)
                ->delete();
        } catch (\Throwable $e) {
            // Ignore
        }
    }

    /**
     * Get remaining lockout duration in seconds if locked out.
     */
    public static function getRemainingLockoutSeconds(string $actionKey = 'token_lookup'): int
    {
        try {
            $ip = AuditLogger::getClientIp();
            $salt = self::getRateLimitSalt();
            $ipHash = hash('sha256', $salt . $ip);

            $record = Capsule::table('mod_guestinvoice_rate_limits')
                ->where('ip_hash', $ipHash)
                ->where('action_key', $actionKey)
                ->first();

            if ($record && !empty($record->locked_until)) {
                $rem = strtotime($record->locked_until) - time();
                return max(0, $rem);
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        return 0;
    }

    private static function getRateLimitSalt(): string
    {
        return 'guest_invoice_rate_limit_salt_2026';
    }
}
