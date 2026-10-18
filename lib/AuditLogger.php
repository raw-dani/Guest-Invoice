<?php

namespace GuestInvoice\Lib;

use WHMCS\Database\Capsule;

class AuditLogger
{
    private static ?string $salt = null;

    /**
     * Get or generate static system salt for hashing IP and user agents.
     */
    private static function getSalt(): string
    {
        if (self::$salt !== null) {
            return self::$salt;
        }

        try {
            $saltSetting = Capsule::table('mod_guestinvoice_settings')
                ->where('setting_key', 'log_salt')
                ->first();

            if ($saltSetting && !empty($saltSetting->setting_value)) {
                self::$salt = $saltSetting->setting_value;
            } else {
                $newSalt = bin2hex(random_bytes(16));
                Capsule::table('mod_guestinvoice_settings')->insert([
                    'setting_key' => 'log_salt',
                    'setting_value' => $newSalt,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                self::$salt = $newSalt;
            }
        } catch (\Throwable $e) {
            self::$salt = 'fallback_guest_invoice_salt_2026';
        }

        return self::$salt;
    }

    /**
     * Record an audit log entry.
     *
     * @param array $params [
     *     'token_id' => int|null,
     *     'invoice_id' => int|null,
     *     'event_type' => string,
     *     'result' => string,
     *     'actor_type' => string ('admin'|'guest'|'system'),
     *     'actor_id' => int|null,
     *     'details' => string|array|null,
     * ]
     * @return bool
     */
    public static function log(array $params): bool
    {
        try {
            $ip = self::getClientIp();
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $salt = self::getSalt();
            $ipHash = !empty($ip) ? hash('sha256', $salt . $ip) : null;
            $uaHash = !empty($ua) ? hash('sha256', $salt . $ua) : null;

            $details = $params['details'] ?? null;
            if (is_array($details)) {
                // Ensure no sensitive fields are saved
                unset($details['token'], $details['raw_token'], $details['password'], $details['card_number'], $details['cvv']);
                $details = json_encode($details);
            }

            Capsule::table('mod_guestinvoice_logs')->insert([
                'token_id' => $params['token_id'] ?? null,
                'invoice_id' => $params['invoice_id'] ?? null,
                'event_type' => $params['event_type'] ?? 'unknown',
                'result' => $params['result'] ?? 'success',
                'actor_type' => $params['actor_type'] ?? 'guest',
                'actor_id' => $params['actor_id'] ?? null,
                'ip_hash' => $ipHash,
                'user_agent_hash' => $uaHash,
                'details' => $details,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return true;
        } catch (\Throwable $e) {
            // Failsafe: Logging failure must never break core user flow
            return false;
        }
    }

    /**
     * Get real client IP address from server headers.
     */
    public static function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Prune logs older than configured retention days.
     *
     * @param int $retentionDays Default 90 days
     * @return int Number of pruned records
     */
    public static function pruneOldLogs(int $retentionDays = 90): int
    {
        try {
            $cutoff = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
            return Capsule::table('mod_guestinvoice_logs')
                ->where('created_at', '<', $cutoff)
                ->delete();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
