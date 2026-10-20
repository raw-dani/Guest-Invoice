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
 * Helper to render the Guest Invoice widget for an invoice ID
 */
function guestinvoice_render_admin_widget(int $invoiceId): string {
    $tokenRow = TokenService::getActiveTokenForInvoice($invoiceId);
    $moduleUrl = 'addonmodules.php?module=guestinvoice';
    $csrfToken = function_exists('generate_token') ? generate_token('plain') : '';
    $defaultExpiry = (int)(Capsule::table('mod_guestinvoice_settings')
        ->where('setting_key', 'default_expiry_days')
        ->value('setting_value') ?? 30);
    if ($defaultExpiry <= 0) {
        $defaultExpiry = 30;
    }

    ob_start();
    ?>
    <div id="guestinvoice-admin-widget-<?= $invoiceId ?>" class="panel panel-default" style="margin-top: 15px; margin-bottom: 20px; border-radius: 6px; border: 1px solid #3b82f6; box-shadow: 0 1px 3px rgba(0,0,0,0.08); clear: both;">
        <div class="panel-heading" style="background-color: #eff6ff; color: #1e40af; font-weight: bold; border-color: #bfdbfe; display: flex; justify-content: space-between; align-items: center; padding: 10px 15px;">
            <span><i class="fa fa-key"></i> Guest Invoice &amp; Payment Access</span>
            <span id="gi-badge-<?= $invoiceId ?>">
                <?php if ($tokenRow): ?>
                    <span class="label label-success"><i class="fa fa-check-circle"></i> Tautan Aktif</span>
                <?php else: ?>
                    <span class="label label-default">Belum Ada Tautan</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="panel-body" style="background: #ffffff; padding: 14px 16px;">
            <div id="gi-alert-<?= $invoiceId ?>" style="display:none; margin-bottom: 12px;" class="alert"></div>

            <!-- STATE: ACTIVE TOKEN -->
            <div id="gi-active-state-<?= $invoiceId ?>" style="<?= $tokenRow ? '' : 'display: none;' ?>">
                <p style="margin-bottom: 8px; color: #334155; font-size: 13px;">
                    Berlaku hingga: <strong id="gi-expires-text-<?= $invoiceId ?>"><?= $tokenRow ? $tokenRow->expires_at : '' ?></strong> 
                    &bull; Diakses: <span id="gi-count-text-<?= $invoiceId ?>" class="badge badge-info"><?= $tokenRow ? (int)$tokenRow->access_count : 0 ?>x</span>
                </p>

                <?php
                    $quickUrl = ($tokenRow && !empty($tokenRow->raw_token)) ? TokenService::buildGuestUrl($tokenRow->raw_token) : '';
                ?>
                <div class="input-group input-group-sm" style="margin-bottom: 12px; max-width: 650px;">
                    <input type="text" readonly class="form-control" id="widget-gi-url-<?= $invoiceId ?>" value="<?= htmlspecialchars($quickUrl) ?>" style="font-family: Menlo, Monaco, Consolas, monospace; font-size: 11px; background: #f8fafc; font-weight: 600;" onclick="this.select()">
                    <span class="input-group-btn">
                        <button type="button" class="btn btn-primary" id="btn-copy-gi-<?= $invoiceId ?>" onclick="giCopyLink('<?= $invoiceId ?>')">
                            <i class="fa fa-copy"></i> Salin Link
                        </button>
                        <a href="<?= htmlspecialchars($quickUrl) ?>" target="_blank" class="btn btn-default" id="gi-open-btn-<?= $invoiceId ?>" title="Buka tautan di tab baru">
                            <i class="fa fa-external-link"></i> Buka
                        </a>
                    </span>
                </div>

                <div class="btn-toolbar" style="margin: 0;">
                    <button type="button" class="btn btn-info btn-sm" id="btn-gi-email-<?= $invoiceId ?>" onclick="giSendEmail('<?= $invoiceId ?>')">
                        <i class="fa fa-envelope"></i> Kirim Ulang Link ke Klien
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" id="btn-gi-revoke-<?= $invoiceId ?>" onclick="giRevokeToken('<?= $invoiceId ?>', <?= $tokenRow ? (int)$tokenRow->id : 0 ?>)">
                        <i class="fa fa-ban"></i> Cabut Tautan
                    </button>
                    <button type="button" class="btn btn-warning btn-sm" onclick="giGenerateToken('<?= $invoiceId ?>', true)" title="Buat link baru dan ganti link lama">
                        <i class="fa fa-refresh"></i> Generate Ulang
                    </button>
                    <a href="<?= $moduleUrl ?>&tab=tokens&search=<?= $invoiceId ?>" class="btn btn-default btn-sm">
                        <i class="fa fa-sliders"></i> Detail Modul
                    </a>
                </div>
            </div>

            <!-- STATE: NO TOKEN -->
            <div id="gi-empty-state-<?= $invoiceId ?>" style="<?= !$tokenRow ? '' : 'display: none;' ?>">
                <p style="margin-bottom: 10px; color: #64748b; font-size: 13px;">
                    Belum ada tautan aktif untuk invoice ini. Klien tidak dapat melihat atau membayar tanpa login kecuali link dibuat.
                </p>
                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                    <button type="button" class="btn btn-primary btn-sm" id="btn-gi-generate-<?= $invoiceId ?>" onclick="giGenerateToken('<?= $invoiceId ?>', false)">
                        <i class="fa fa-bolt"></i> Generate Link Sekarang (Instan)
                    </button>
                    <a href="<?= $moduleUrl ?>&tab=tokens&search=<?= $invoiceId ?>" class="btn btn-default btn-sm">
                        <i class="fa fa-sliders"></i> Detail Modul
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
    if (typeof window.giLoadedScripts === 'undefined') {
        window.giLoadedScripts = true;

            function giShowNotice(invId, type, msg) {
                var alertEl = document.getElementById('gi-alert-' + invId);
                if (!alertEl) return;
                alertEl.className = 'alert alert-' + type;
                alertEl.innerHTML = (type === 'success' ? '<i class="fa fa-check-circle"></i> ' : '<i class="fa fa-exclamation-triangle"></i> ') + msg;
                alertEl.style.display = 'block';
                setTimeout(function() {
                    alertEl.style.display = 'none';
                }, 5000);
            }

            function giCopyLink(invId) {
                var input = document.getElementById('widget-gi-url-' + invId);
                var btn = document.getElementById('btn-copy-gi-' + invId);
                if (!input) return;

                input.select();
                input.setSelectionRange(0, 99999);

                var onSuccess = function() {
                    if (btn) {
                        var orig = btn.innerHTML;
                        btn.innerHTML = '<i class="fa fa-check"></i> Tersalin!';
                        btn.className = 'btn btn-success';
                        setTimeout(function() {
                            btn.innerHTML = orig;
                            btn.className = 'btn btn-primary';
                        }, 2000);
                    }
                    giShowNotice(invId, 'success', 'Tautan guest invoice berhasil disalin ke clipboard.');
                };

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(input.value).then(onSuccess).catch(function() {
                        document.execCommand('copy');
                        onSuccess();
                    });
                } else {
                    document.execCommand('copy');
                    onSuccess();
                }
            }

            function giGenerateToken(invId, isRegen) {
                if (isRegen && !confirm('Buat tautan baru? Tautan aktif sebelumnya akan langsung dicabut/tidak berlaku.')) {
                    return;
                }

                var genBtn = document.getElementById('btn-gi-generate-' + invId);
                if (genBtn) {
                    genBtn.disabled = true;
                    genBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sedang Membuat...';
                }

                var formData = new FormData();
                formData.append('token', '<?= $csrfToken ?>');
                formData.append('action', 'ajax_generate');
                formData.append('invoice_id', invId);
                formData.append('expiry_days', '<?= $defaultExpiry ?>');

                fetch('addonmodules.php?module=guestinvoice', {
                    method: 'POST',
                    body: formData
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (genBtn) {
                        genBtn.disabled = false;
                        genBtn.innerHTML = '<i class="fa fa-bolt"></i> Generate Link Sekarang (Instan)';
                    }

                    if (data.status === 'success') {
                        var urlInput = document.getElementById('widget-gi-url-' + invId);
                        if (urlInput) urlInput.value = data.url;

                        var openBtn = document.getElementById('gi-open-btn-' + invId);
                        if (openBtn) openBtn.href = data.url;

                        var expText = document.getElementById('gi-expires-text-' + invId);
                        if (expText) expText.innerText = data.expires_at;

                        var countText = document.getElementById('gi-count-text-' + invId);
                        if (countText) countText.innerText = '0x';

                        var revokeBtn = document.getElementById('btn-gi-revoke-' + invId);
                        if (revokeBtn) {
                            revokeBtn.setAttribute('onclick', "giRevokeToken('" + invId + "', " + data.token_id + ")");
                        }

                        var badge = document.getElementById('gi-badge-' + invId);
                        if (badge) badge.innerHTML = '<span class="label label-success"><i class="fa fa-check-circle"></i> Tautan Aktif</span>';

                        document.getElementById('gi-empty-state-' + invId).style.display = 'none';
                        document.getElementById('gi-active-state-' + invId).style.display = 'block';

                        giCopyLink(invId);
                    } else {
                        giShowNotice(invId, 'danger', data.message || 'Gagal membuat tautan.');
                    }
                })
                .catch(function(err) {
                    if (genBtn) {
                        genBtn.disabled = false;
                        genBtn.innerHTML = '<i class="fa fa-bolt"></i> Generate Link Sekarang (Instan)';
                    }
                    giShowNotice(invId, 'danger', 'Terjadi kesalahan sistem saat membuat token.');
                });
            }

            function giRevokeToken(invId, tokenId) {
                if (!confirm('Cabut tautan guest invoice ini? Klien tidak akan dapat lagi mengakses invoice tanpa login.')) {
                    return;
                }

                var revokeBtn = document.getElementById('btn-gi-revoke-' + invId);
                if (revokeBtn) {
                    revokeBtn.disabled = true;
                    revokeBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Mencabut...';
                }

                var formData = new FormData();
                formData.append('token', '<?= $csrfToken ?>');
                formData.append('action', 'ajax_revoke');
                formData.append('token_id', tokenId);

                fetch('addonmodules.php?module=guestinvoice', {
                    method: 'POST',
                    body: formData
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (revokeBtn) {
                        revokeBtn.disabled = false;
                        revokeBtn.innerHTML = '<i class="fa fa-ban"></i> Cabut Tautan';
                    }

                    if (data.status === 'success') {
                        var badge = document.getElementById('gi-badge-' + invId);
                        if (badge) badge.innerHTML = '<span class="label label-default">Belum Ada Tautan</span>';

                        document.getElementById('gi-active-state-' + invId).style.display = 'none';
                        document.getElementById('gi-empty-state-' + invId).style.display = 'block';

                        giShowNotice(invId, 'warning', data.message);
                    } else {
                        giShowNotice(invId, 'danger', data.message || 'Gagal mencabut tautan.');
                    }
                })
                .catch(function() {
                    if (revokeBtn) {
                        revokeBtn.disabled = false;
                        revokeBtn.innerHTML = '<i class="fa fa-ban"></i> Cabut Tautan';
                    }
                    giShowNotice(invId, 'danger', 'Terjadi kesalahan sistem.');
                });
            }

            function giSendEmail(invId) {
                var btn = document.getElementById('btn-gi-email-' + invId);
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Mengirim...';
                }

                var formData = new FormData();
                formData.append('token', '<?= $csrfToken ?>');
                formData.append('action', 'ajax_send_email');
                formData.append('invoice_id', invId);

                fetch('addonmodules.php?module=guestinvoice', {
                    method: 'POST',
                    body: formData
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa fa-envelope"></i> Kirim Ulang Link ke Klien';
                    }
                    if (data.status === 'success') {
                        giShowNotice(invId, 'success', data.message);
                    } else {
                        giShowNotice(invId, 'danger', data.message || 'Gagal mengirim email.');
                    }
                })
                .catch(function() {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa fa-envelope"></i> Kirim Ulang Link ke Klien';
                    }
                    giShowNotice(invId, 'danger', 'Terjadi kesalahan sistem.');
                });
            }
        }
        </script>
    <?php
    return ob_get_clean();
}

/**
 * Hook 1: Standard WHMCS Hook to inject into invoice controls area
 */
add_hook('AdminInvoicesControlsOutput', 1, function ($vars) {
    try {
        $invoiceId = (int)($vars['invoiceid'] ?? 0);
        if ($invoiceId <= 0) {
            return '';
        }
        return guestinvoice_render_admin_widget($invoiceId);
    } catch (\Throwable $e) {
        return '';
    }
});

/**
 * Hook 2: Fallback Injection via AdminAreaFooterOutput
 * Ensures the widget is ALWAYS rendered on invoices.php (under invoice summary / client details)
 * even if WHMCS template does not render AdminInvoicesControlsOutput.
 */
add_hook('AdminAreaFooterOutput', 1, function ($vars) {
    try {
        $filename = $vars['filename'] ?? '';
        $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $phpSelf = basename($_SERVER['PHP_SELF'] ?? '');

        $isInvoicePage = ($filename === 'invoices' || $filename === 'invoices.php' || $scriptName === 'invoices.php' || $phpSelf === 'invoices.php');
        if (!$isInvoicePage) {
            return '';
        }

        $action = $_GET['action'] ?? '';
        $invoiceId = (int)($_GET['id'] ?? 0);
        if ($invoiceId <= 0 && isset($_GET['invoiceid'])) {
            $invoiceId = (int)$_GET['invoiceid'];
        }

        // Only inject on invoice edit/summary screen
        if ($invoiceId <= 0) {
            return '';
        }

        $widgetHtml = guestinvoice_render_admin_widget($invoiceId);
        $encodedHtml = json_encode($widgetHtml);

        return <<<HTML
        <script type="text/javascript">
        (function() {
            function placeGuestInvoiceWidget() {
                var widgetId = 'guestinvoice-admin-widget-{$invoiceId}';
                var existingWidget = document.getElementById(widgetId);

                var html = {$encodedHtml};
                var wrapper = document.createElement('div');
                wrapper.innerHTML = html;
                var widgetNode = wrapper.firstElementChild;
                var scriptNodes = wrapper.querySelectorAll('script');

                if (!existingWidget) {
                    // Try targeting Tab 1 container or invoice summary table
                    var placed = false;
                    var tab1 = document.getElementById('tab1box') || document.querySelector('[data-tab="1"]') || document.querySelector('#tab1');
                    if (tab1) {
                        tab1.appendChild(widgetNode);
                        placed = true;
                    }

                    if (!placed) {
                        var formTables = document.querySelectorAll('table.form');
                        if (formTables.length > 0) {
                            var targetTable = formTables[0];
                            targetTable.parentNode.insertBefore(widgetNode, targetTable.nextSibling);
                            placed = true;
                        }
                    }

                    if (!placed) {
                        var summaryBox = document.querySelector('.invoice-summary, .contentbox, .context-btn-container');
                        if (summaryBox) {
                            summaryBox.parentNode.insertBefore(widgetNode, summaryBox.nextSibling);
                            placed = true;
                        }
                    }

                    if (!placed) {
                        var contentArea = document.querySelector('.contentarea, #contentarea, form[name="invoices"]');
                        if (contentArea) {
                            contentArea.appendChild(widgetNode);
                            placed = true;
                        }
                    }

                    // Execute any inner scripts from the injected widget
                    scriptNodes.forEach(function(s) {
                        var newScript = document.createElement('script');
                        newScript.text = s.innerHTML;
                        document.body.appendChild(newScript);
                    });
                }

                // Add convenient Quick Button to top action buttons bar (next to "View as Client", "Print", "Download")
                var topBtnBar = document.querySelector('.context-btn-container, .btn-container, .btn-group');
                if (topBtnBar && !document.getElementById('top-gi-btn-{$invoiceId}')) {
                    var quickBtn = document.createElement('button');
                    quickBtn.type = 'button';
                    quickBtn.id = 'top-gi-btn-{$invoiceId}';
                    quickBtn.className = 'btn btn-primary btn-sm';
                    quickBtn.style.marginLeft = '6px';
                    quickBtn.innerHTML = '<i class="fa fa-key"></i> Guest Link';
                    quickBtn.onclick = function() {
                        var widget = document.getElementById(widgetId);
                        if (widget) {
                            widget.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            widget.style.boxShadow = '0 0 10px #3b82f6';
                            setTimeout(function() { widget.style.boxShadow = ''; }, 2000);
                        } else if (typeof giGenerateToken === 'function') {
                            giGenerateToken('{$invoiceId}', false);
                        }
                    };
                    topBtnBar.appendChild(quickBtn);
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', placeGuestInvoiceWidget);
            } else {
                placeGuestInvoiceWidget();
            }
            setTimeout(placeGuestInvoiceWidget, 500);
        })();
        </script>
HTML;
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
