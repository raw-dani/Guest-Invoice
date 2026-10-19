<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;
use GuestInvoice\Lib\TokenService;
use GuestInvoice\Lib\MailService;
use GuestInvoice\Lib\AuditLogger;

/**
 * Hook: Automatically generate/send guest invoice link when an invoice is created.
 */
add_hook('InvoiceCreated', 1, function ($vars) {
    try {
        $invoiceId = (int)($vars['invoiceid'] ?? 0);
        if ($invoiceId <= 0) {
            return;
        }

        // Check if auto-generation setting is enabled
        $autoGen = Capsule::table('mod_guestinvoice_settings')
            ->where('setting_key', 'auto_generate_on_invoice_created')
            ->value('setting_value');

        if ($autoGen === '1' || $autoGen === 'yes') {
            $expiryDays = (int)(Capsule::table('mod_guestinvoice_settings')
                ->where('setting_key', 'default_expiry_days')
                ->value('setting_value') ?? 30);

            $res = TokenService::createToken($invoiceId, $expiryDays, null);

            // Check if auto email is also enabled
            $autoEmail = Capsule::table('mod_guestinvoice_settings')
                ->where('setting_key', 'auto_email_on_invoice_created')
                ->value('setting_value');

            if ($res['success'] && ($autoEmail === '1' || $autoEmail === 'yes')) {
                MailService::sendGuestInvoiceLink($invoiceId, $res['raw_token'], $res['expires_at'], null);
            }
        }
    } catch (\Throwable $e) {
        // Silently prevent hook from interrupting WHMCS invoice creation
    }
});

/**
 * Hook: Invalidate token when invoice is cancelled.
 */
add_hook('InvoiceCancelled', 1, function ($vars) {
    try {
        $invoiceId = (int)($vars['invoiceid'] ?? 0);
        if ($invoiceId <= 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        Capsule::table('mod_guestinvoice_tokens')
            ->where('invoice_id', $invoiceId)
            ->where('status', 'Active')
            ->update([
                'status' => 'Revoked',
                'revoked_at' => $now,
                'updated_at' => $now,
            ]);

        AuditLogger::log([
            'invoice_id' => $invoiceId,
            'event_type' => 'invoice_cancelled_revocation',
            'result' => 'success',
            'actor_type' => 'system',
        ]);
    } catch (\Throwable $e) {
        // Ignore
    }
});

/**
 * Hook: Invalidate token when invoice is deleted.
 */
add_hook('InvoiceDeleted', 1, function ($vars) {
    try {
        $invoiceId = (int)($vars['invoiceid'] ?? 0);
        if ($invoiceId <= 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        Capsule::table('mod_guestinvoice_tokens')
            ->where('invoice_id', $invoiceId)
            ->update([
                'status' => 'Revoked',
                'revoked_at' => $now,
                'updated_at' => $now,
            ]);
    } catch (\Throwable $e) {
        // Ignore
    }
});

/**
 * Hook: Add Guest Invoice action widget/button to Admin Invoice Details screen.
 */
add_hook('AdminInvoicesControlsOutput', 1, function ($vars) {
    try {
        $invoiceId = (int)($vars['invoiceid'] ?? 0);
        if ($invoiceId <= 0) {
            return '';
        }

        $tokenRow = TokenService::getActiveTokenForInvoice($invoiceId);
        $moduleUrl = 'addonmodules.php?module=guestinvoice';
        $csrfToken = function_exists('generate_token') ? generate_token('plain') : '';

        ob_start();
        ?>
        <div class="panel panel-info" style="margin-top: 15px; border-radius: 8px; border-color: #3b82f6;">
            <div class="panel-heading" style="background-color: #eff6ff; color: #1e40af; font-weight: bold; border-color: #bfdbfe;">
                <i class="fa fa-key"></i> Guest Invoice & Payment Access
            </div>
            <div class="panel-body" style="background: #ffffff; padding: 12px 16px;">
                <?php if ($tokenRow): ?>
                    <p style="margin-bottom: 8px;">
                        <span class="label label-success">Tautan Aktif Tersedia</span>
                        Berlaku hingga: <strong><?= $tokenRow->expires_at ?></strong> (Diakses: <?= $tokenRow->access_count ?>x)
                    </p>
                    <?php if (!empty($tokenRow->raw_token)): 
                        $quickUrl = TokenService::buildGuestUrl($tokenRow->raw_token);
                    ?>
                        <div class="input-group input-group-sm" style="margin-bottom: 10px; max-width: 600px;">
                            <input type="text" readonly class="form-control" id="widget-gi-url-<?= $invoiceId ?>" value="<?= htmlspecialchars($quickUrl) ?>" style="font-family: monospace; font-size: 11px; background: #f8fafc;" onclick="this.select()">
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-default" onclick="copyGuestLink('widget-gi-url-<?= $invoiceId ?>', this)">
                                    <i class="fa fa-copy"></i> Salin Link
                                </button>
                                <a href="<?= htmlspecialchars($quickUrl) ?>" target="_blank" class="btn btn-info">
                                    <i class="fa fa-external-link"></i> Buka
                                </a>
                            </span>
                        </div>
                    <?php endif; ?>
                    <div class="btn-group">
                        <form method="post" action="<?= $moduleUrl ?>&tab=tokens" style="display:inline-block;">
                            <input type="hidden" name="token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="send_email">
                            <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>">
                            <button type="submit" class="btn btn-info btn-sm"><i class="fa fa-envelope"></i> Kirim Ulang Link ke Klien</button>
                        </form>
                        <form method="post" action="<?= $moduleUrl ?>&tab=tokens" style="display:inline-block; margin-left: 6px;" onsubmit="return confirm('Cabut tautan guest invoice ini?');">
                            <input type="hidden" name="token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="revoke_token">
                            <input type="hidden" name="token_id" value="<?= $tokenRow->id ?>">
                            <button type="submit" class="btn btn-danger btn-sm"><i class="fa fa-ban"></i> Cabut Tautan</button>
                        </form>
                    </div>
                <?php else: ?>
                    <p style="margin-bottom: 8px; color: #64748b;">Belum ada tautan aktif untuk invoice ini.</p>
                    <form method="post" action="<?= $moduleUrl ?>&tab=generate" style="display:inline-block;">
                        <input type="hidden" name="token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="create_single">
                        <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>">
                        <input type="hidden" name="expiry_days" value="30">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-plus-circle"></i> Buat Tautan Guest Invoice</button>
                    </form>
                <?php endif; ?>
                <a href="<?= $moduleUrl ?>&tab=tokens&search=<?= $invoiceId ?>" class="btn btn-default btn-sm" style="margin-left: 6px;">
                    <i class="fa fa-external-link"></i> Buka di Dashboard Modul
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    } catch (\Throwable $e) {
        return '';
    }
});

/**
 * Hook: Daily Cron Job maintenance (clean expired rate limits & old logs).
 */
add_hook('DailyCronJob', 1, function ($vars) {
    try {
        $retentionDays = (int)(Capsule::table('mod_guestinvoice_settings')
            ->where('setting_key', 'log_retention_days')
            ->value('setting_value') ?? 90);

        AuditLogger::pruneOldLogs($retentionDays);

        // Prune lapsed rate limits
        $cutoff = date('Y-m-d H:i:s', strtotime('-1 day'));
        Capsule::table('mod_guestinvoice_rate_limits')
            ->where('last_attempt_at', '<', $cutoff)
            ->delete();
    } catch (\Throwable $e) {
        // Ignore
    }
});
