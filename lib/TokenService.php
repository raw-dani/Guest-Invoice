<?php

namespace GuestInvoice\Lib;

use WHMCS\Database\Capsule;

class TokenService
{
    /**
     * Generate a cryptographically secure URL-safe token and persist its hash.
     *
     * @param int $invoiceId
     * @param int $expiryDays
     * @param int|null $adminId
     * @return array ['success' => bool, 'raw_token' => string|null, 'token_hash' => string|null, 'expires_at' => string|null, 'error' => string|null]
     */
    public static function createToken(int $invoiceId, int $expiryDays = 30, ?int $adminId = null): array
    {
        try {
            if ($invoiceId <= 0) {
                return ['success' => false, 'error' => 'Invalid invoice ID provided.'];
            }

            // Verify invoice actually exists in WHMCS tblinvoices
            $invoiceExists = Capsule::table('tblinvoices')->where('id', $invoiceId)->exists();
            if (!$invoiceExists) {
                return ['success' => false, 'error' => "Invoice #{$invoiceId} not found in billing database."];
            }

            // Enforce minimum and maximum expiry days (1 - 365)
            $expiryDays = max(1, min(365, $expiryDays));

            // Generate 32 bytes (256 bits) of cryptographic entropy
            $rawBytes = random_bytes(32);
            $rawToken = bin2hex($rawBytes); // 64 hex characters, completely URL-safe
            $tokenHash = hash('sha256', $rawToken);

            $now = date('Y-m-d H:i:s');
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));

            // Revoke any previous active tokens for this invoice if single active token policy is desired
            Capsule::table('mod_guestinvoice_tokens')
                ->where('invoice_id', $invoiceId)
                ->where('status', 'Active')
                ->update([
                    'status' => 'Revoked',
                    'revoked_at' => $now,
                    'updated_at' => $now,
                ]);

            $id = Capsule::table('mod_guestinvoice_tokens')->insertGetId([
                'invoice_id' => $invoiceId,
                'token_hash' => $tokenHash,
                'status' => 'Active',
                'created_at' => $now,
                'expires_at' => $expiresAt,
                'revoked_at' => null,
                'created_by_admin_id' => $adminId,
                'last_access_at' => null,
                'access_count' => 0,
                'updated_at' => $now,
            ]);

            AuditLogger::log([
                'token_id' => $id,
                'invoice_id' => $invoiceId,
                'event_type' => 'token_generated',
                'result' => 'success',
                'actor_type' => $adminId ? 'admin' : 'system',
                'actor_id' => $adminId,
                'details' => json_encode(['expiry_days' => $expiryDays, 'expires_at' => $expiresAt]),
            ]);

            return [
                'success' => true,
                'token_id' => $id,
                'raw_token' => $rawToken,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
                'invoice_id' => $invoiceId,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'Failed to generate guest token: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Find and validate a token record by raw token string.
     *
     * @param string $rawToken
     * @return object|null Returns token record row object if found and active, null otherwise.
     */
    public static function validateToken(string $rawToken): ?object
    {
        $rawToken = trim($rawToken);
        if (empty($rawToken) || strlen($rawToken) < 32 || strlen($rawToken) > 128) {
            return null;
        }

        $tokenHash = hash('sha256', $rawToken);
        $record = Capsule::table('mod_guestinvoice_tokens')
            ->where('token_hash', $tokenHash)
            ->first();

        if (!$record) {
            return null;
        }

        $now = date('Y-m-d H:i:s');

        // Check if revoked
        if ($record->status === 'Revoked' || !empty($record->revoked_at)) {
            return null;
        }

        // Check if expired
        if ($record->expires_at < $now) {
            if ($record->status !== 'Expired') {
                Capsule::table('mod_guestinvoice_tokens')
                    ->where('id', $record->id)
                    ->update(['status' => 'Expired', 'updated_at' => $now]);
            }
            return null;
        }

        if ($record->status !== 'Active') {
            return null;
        }

        return $record;
    }

    /**
     * Record a successful access to the token.
     *
     * @param int $tokenId
     */
    public static function recordAccess(int $tokenId): void
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('mod_guestinvoice_tokens')
            ->where('id', $tokenId)
            ->increment('access_count', 1, [
                'last_access_at' => $now,
                'updated_at' => $now,
            ]);
    }

    /**
     * Revoke a token by its ID.
     *
     * @param int $tokenId
     * @param int|null $adminId
     * @return bool
     */
    public static function revokeToken(int $tokenId, ?int $adminId = null): bool
    {
        $record = Capsule::table('mod_guestinvoice_tokens')->where('id', $tokenId)->first();
        if (!$record) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        Capsule::table('mod_guestinvoice_tokens')
            ->where('id', $tokenId)
            ->update([
                'status' => 'Revoked',
                'revoked_at' => $now,
                'updated_at' => $now,
            ]);

        AuditLogger::log([
            'token_id' => $tokenId,
            'invoice_id' => $record->invoice_id,
            'event_type' => 'token_revoked',
            'result' => 'success',
            'actor_type' => $adminId ? 'admin' : 'system',
            'actor_id' => $adminId,
            'details' => json_encode(['revoked_at' => $now]),
        ]);

        return true;
    }

    /**
     * Bulk generate tokens for an array of invoice IDs.
     *
     * @param array $invoiceIds
     * @param int $expiryDays
     * @param int|null $adminId
     * @param string $systemUrl
     * @return array Per-item result reporting
     */
    public static function bulkCreateTokens(array $invoiceIds, int $expiryDays = 30, ?int $adminId = null, string $systemUrl = ''): array
    {
        $results = [];
        $successCount = 0;
        $failCount = 0;

        foreach ($invoiceIds as $invId) {
            $invId = (int)$invId;
            if ($invId <= 0) {
                continue;
            }

            $res = self::createToken($invId, $expiryDays, $adminId);
            if ($res['success']) {
                $successCount++;
                $results[] = [
                    'invoice_id' => $invId,
                    'success' => true,
                    'raw_token' => $res['raw_token'],
                    'url' => self::buildGuestUrl($res['raw_token'], $systemUrl),
                    'expires_at' => $res['expires_at'],
                    'error' => null,
                ];
            } else {
                $failCount++;
                $results[] = [
                    'invoice_id' => $invId,
                    'success' => false,
                    'raw_token' => null,
                    'url' => null,
                    'expires_at' => null,
                    'error' => $res['error'],
                ];
            }
        }

        return [
            'summary' => [
                'total' => count($results),
                'success' => $successCount,
                'failed' => $failCount,
            ],
            'items' => $results,
        ];
    }

    /**
     * Construct the full guest invoice access URL.
     *
     * @param string $rawToken
     * @param string $systemUrl Optional explicit WHMCS system URL
     * @return string
     */
    public static function buildGuestUrl(string $rawToken, string $systemUrl = ''): string
    {
        if (empty($systemUrl)) {
            $systemUrl = self::getWhmcsSystemUrl();
        }

        $systemUrl = rtrim($systemUrl, '/') . '/';
        return $systemUrl . 'index.php?m=guestinvoice&token=' . urlencode($rawToken);
    }

    /**
     * Retrieve WHMCS system URL from tblconfiguration.
     *
     * @return string
     */
    public static function getWhmcsSystemUrl(): string
    {
        try {
            $row = Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->first();
            if ($row && !empty($row->value)) {
                return $row->value;
            }
        } catch (\Throwable $e) {
            // Fallback
        }

        // Detect from current server request if available
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . $host . '/';
    }

    /**
     * Get active token record for a given invoice ID.
     *
     * @param int $invoiceId
     * @return object|null
     */
    public static function getActiveTokenForInvoice(int $invoiceId): ?object
    {
        $now = date('Y-m-d H:i:s');
        return Capsule::table('mod_guestinvoice_tokens')
            ->where('invoice_id', $invoiceId)
            ->where('status', 'Active')
            ->where('expires_at', '>', $now)
            ->first();
    }
}
