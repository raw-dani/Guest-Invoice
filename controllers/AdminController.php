<?php

namespace GuestInvoice\Controllers;

use WHMCS\Database\Capsule;
use GuestInvoice\Lib\TokenService;
use GuestInvoice\Lib\InvoiceService;
use GuestInvoice\Lib\AuditLogger;
use GuestInvoice\Lib\MailService;
use GuestInvoice\Migrations\MigrationManager;

class AdminController
{
    /**
     * Dispatch Admin Area request.
     *
     * @param array $vars WHMCS module parameters
     * @return string HTML output
     */
    public static function handle(array $vars): string
    {
        // Ensure schema is up to date (e.g. raw_token column)
        MigrationManager::createTokensTable();

        $adminId = $_SESSION['adminid'] ?? null;
        $currentTab = $_GET['tab'] ?? 'dashboard';
        $action = $_POST['action'] ?? $_GET['action'] ?? '';

        $message = null;
        $messageType = 'success';
        $bulkResults = null;
        $generatedLink = null;

        // Verify CSRF on POST actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (function_exists('check_token')) {
                check_token();
            }

            // Handle Actions
            switch ($action) {
                case 'create_single':
                    $invId = (int)($_POST['invoice_id'] ?? 0);
                    $expiry = (int)($_POST['expiry_days'] ?? 30);
                    $sendEmail = !empty($_POST['send_email']);

                    $res = TokenService::createToken($invId, $expiry, $adminId);
                    if ($res['success']) {
                        $url = TokenService::buildGuestUrl($res['raw_token']);
                        $generatedLink = [
                            'invoice_id' => $invId,
                            'token' => $res['raw_token'],
                            'url' => $url,
                            'expires_at' => $res['expires_at'],
                        ];
                        $message = "Guest Invoice link successfully created for Invoice #{$invId}.";

                        if ($sendEmail) {
                            $mailRes = MailService::sendGuestInvoiceLink($invId, $res['raw_token'], $res['expires_at'], $adminId);
                            if ($mailRes['success']) {
                                $message .= " Email notification sent to client.";
                            } else {
                                $message .= " (Email notice failed: {$mailRes['error']})";
                            }
                        }
                    } else {
                        $message = $res['error'];
                        $messageType = 'danger';
                    }
                    break;

                case 'create_bulk':
                    $rawIds = $_POST['bulk_invoice_ids'] ?? '';
                    $expiry = (int)($_POST['expiry_days'] ?? 30);
                    $ids = array_filter(array_map('trim', preg_split('/[\s,]+/', $rawIds)));

                    if (empty($ids)) {
                        $message = 'Please provide at least one valid Invoice ID.';
                        $messageType = 'warning';
                    } else {
                        $bulkResults = TokenService::bulkCreateTokens($ids, $expiry, $adminId);
                        $message = "Bulk generation completed: {$bulkResults['summary']['success']} succeeded, {$bulkResults['summary']['failed']} failed.";
                    }
                    break;

                case 'revoke_token':
                    $tokenId = (int)($_POST['token_id'] ?? 0);
                    if (TokenService::revokeToken($tokenId, $adminId)) {
                        $message = "Guest invoice token #{$tokenId} has been revoked.";
                    } else {
                        $message = "Failed to revoke token.";
                        $messageType = 'danger';
                    }
                    break;

                case 'send_email':
                    $invId = (int)($_POST['invoice_id'] ?? 0);
                    $tokenRow = TokenService::getActiveTokenForInvoice($invId);
                    if ($tokenRow) {
                        // Re-generate or use current
                        $newRes = TokenService::createToken($invId, 30, $adminId);
                        if ($newRes['success']) {
                            $mailRes = MailService::sendGuestInvoiceLink($invId, $newRes['raw_token'], $newRes['expires_at'], $adminId);
                            if ($mailRes['success']) {
                                $message = "Fresh guest invoice link generated and emailed to client for Invoice #{$invId}.";
                            } else {
                                $message = "Email failed: " . $mailRes['error'];
                                $messageType = 'danger';
                            }
                        }
                    } else {
                        $message = "Invoice #{$invId} has no active token. Generating a new one...";
                        $newRes = TokenService::createToken($invId, 30, $adminId);
                        if ($newRes['success']) {
                            MailService::sendGuestInvoiceLink($invId, $newRes['raw_token'], $newRes['expires_at'], $adminId);
                            $message = "New link generated and emailed successfully.";
                        }
                    }
                    break;

                case 'save_settings':
                    $settingsToSave = [
                        'default_expiry_days' => max(1, (int)($_POST['default_expiry_days'] ?? 30)),
                        'allow_pdf_download' => !empty($_POST['allow_pdf_download']) ? '1' : '0',
                        'auto_generate_on_invoice_created' => !empty($_POST['auto_generate_on_invoice_created']) ? '1' : '0',
                        'auto_email_on_invoice_created' => !empty($_POST['auto_email_on_invoice_created']) ? '1' : '0',
                        'enable_guest_payment' => !empty($_POST['enable_guest_payment']) ? '1' : '0',
                        'rate_limit_per_minute' => max(5, (int)($_POST['rate_limit_per_minute'] ?? 15)),
                        'rate_limit_lockout_minutes' => max(1, (int)($_POST['rate_limit_lockout_minutes'] ?? 15)),
                        'log_retention_days' => max(7, (int)($_POST['log_retention_days'] ?? 90)),
                        'custom_branding_title' => trim($_POST['custom_branding_title'] ?? 'Secure Guest Invoice & Payment'),
                    ];

                    $now = date('Y-m-d H:i:s');
                    foreach ($settingsToSave as $k => $v) {
                        Capsule::table('mod_guestinvoice_settings')
                            ->updateOrInsert(['setting_key' => $k], ['setting_value' => (string)$v, 'updated_at' => $now]);
                    }
                    $message = 'Module configuration settings successfully saved.';
                    break;

                case 'prune_logs':
                    $days = (int)($_POST['prune_days'] ?? 90);
                    $pruned = AuditLogger::pruneOldLogs($days);
                    $message = "Audit logs cleaned: {$pruned} historical entries removed.";
                    break;
            }
        }

        // Gather metrics for Dashboard tab
        $now = date('Y-m-d H:i:s');
        $stats = [
            'total_tokens' => Capsule::table('mod_guestinvoice_tokens')->count(),
            'active_tokens' => Capsule::table('mod_guestinvoice_tokens')->where('status', 'Active')->where('expires_at', '>', $now)->count(),
            'expired_tokens' => Capsule::table('mod_guestinvoice_tokens')->where('status', 'Expired')->orWhere('expires_at', '<=', $now)->count(),
            'revoked_tokens' => Capsule::table('mod_guestinvoice_tokens')->where('status', 'Revoked')->count(),
            'total_accesses' => Capsule::table('mod_guestinvoice_tokens')->sum('access_count') ?? 0,
            'recent_logs' => Capsule::table('mod_guestinvoice_logs')->orderBy('id', 'desc')->limit(10)->get(),
        ];

        // Gather tokens list
        $search = trim($_GET['search'] ?? '');
        $tokensQuery = Capsule::table('mod_guestinvoice_tokens')->orderBy('id', 'desc');
        if (!empty($search)) {
            $tokensQuery->where('invoice_id', 'LIKE', "%{$search}%");
        }
        $tokens = $tokensQuery->limit(50)->get();

        // Gather logs list
        $logs = Capsule::table('mod_guestinvoice_logs')->orderBy('id', 'desc')->limit(100)->get();

        // Load settings
        $settingsRaw = Capsule::table('mod_guestinvoice_settings')->pluck('setting_value', 'setting_key')->all();

        // Render view
        return self::renderAdminHtml([
            'tab' => $currentTab,
            'message' => $message,
            'messageType' => $messageType,
            'stats' => $stats,
            'tokens' => $tokens,
            'logs' => $logs,
            'settings' => $settingsRaw,
            'generatedLink' => $generatedLink,
            'bulkResults' => $bulkResults,
            'moduleLink' => $vars['modulelink'],
            'csrf_token' => function_exists('generate_token') ? generate_token('plain') : '',
        ]);
    }

    /**
     * Render administrative HTML dashboard and tabs.
     */
    private static function renderAdminHtml(array $d): string
    {
        ob_start();
        ?>
        <link rel="stylesheet" href="../modules/addons/guestinvoice/assets/css/admin.css">
        <div class="guestinvoice-admin">
            <div class="gi-header">
                <div class="gi-header-title">
                    <span class="gi-badge">WHMCS Addon</span>
                    <h2>Guest Invoice & Payment</h2>
                    <p class="gi-subtitle">Secure, token-based public invoice viewing and online payment for clients without login.</p>
                </div>
            </div>

            <?php if (!empty($d['message'])): ?>
                <div class="gi-alert gi-alert-<?= htmlspecialchars($d['messageType']) ?>">
                    <?= htmlspecialchars($d['message']) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($d['generatedLink'])): ?>
                <div class="gi-callout gi-callout-success">
                    <h4>Tautan Guest Invoice Siap Digunakan!</h4>
                    <p>Nomor Tagihan: <strong>#<?= htmlspecialchars($d['generatedLink']['invoice_id']) ?></strong> | Berlaku Hingga: <strong><?= htmlspecialchars($d['generatedLink']['expires_at']) ?></strong></p>
                    <div class="gi-copy-box">
                        <input type="text" readonly id="gi-copy-input" value="<?= htmlspecialchars($d['generatedLink']['url']) ?>">
                        <button type="button" class="btn btn-primary" onclick="copyGuestLink('gi-copy-input')">Salin Tautan</button>
                    </div>
                    <small class="text-muted">Token rahasia ini hanya ditampilkan sekali. Simpan atau kirimkan kepada pelanggan.</small>
                </div>
            <?php endif; ?>

            <?php if (!empty($d['bulkResults'])): ?>
                <div class="gi-callout gi-callout-info">
                    <h4>Hasil Pembuatan Tautan Bulk</h4>
                    <p>Total: <?= $d['bulkResults']['summary']['total'] ?> | Sukses: <?= $d['bulkResults']['summary']['success'] ?> | Gagal: <?= $d['bulkResults']['summary']['failed'] ?></p>
                    <table class="table table-bordered table-striped" style="margin-top: 12px; background: #fff;">
                        <thead>
                            <tr>
                                <th>Invoice ID</th>
                                <th>Status</th>
                                <th>Tautan / Keterangan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($d['bulkResults']['items'] as $item): ?>
                                <tr>
                                    <td><strong>#<?= htmlspecialchars($item['invoice_id']) ?></strong></td>
                                    <td>
                                        <?php if ($item['success']): ?>
                                            <span class="label label-success">Sukses</span>
                                        <?php else: ?>
                                            <span class="label label-danger">Gagal</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($item['success']): ?>
                                            <input type="text" readonly class="form-control input-sm" id="bulk-link-<?= $item['invoice_id'] ?>" value="<?= htmlspecialchars($item['url']) ?>">
                                        <?php else: ?>
                                            <span class="text-danger"><?= htmlspecialchars($item['error']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($item['success']): ?>
                                            <button type="button" class="btn btn-default btn-xs" onclick="copyGuestLink('bulk-link-<?= $item['invoice_id'] ?>')">Salin</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <ul class="nav nav-tabs gi-nav-tabs">
                <li class="<?= $d['tab'] === 'dashboard' ? 'active' : '' ?>"><a href="<?= $d['moduleLink'] ?>&tab=dashboard"><i class="fa fa-dashboard"></i> Ringkasan</a></li>
                <li class="<?= $d['tab'] === 'generate' ? 'active' : '' ?>"><a href="<?= $d['moduleLink'] ?>&tab=generate"><i class="fa fa-plus-circle"></i> Buat Tautan (Single / Bulk)</a></li>
                <li class="<?= $d['tab'] === 'tokens' ? 'active' : '' ?>"><a href="<?= $d['moduleLink'] ?>&tab=tokens"><i class="fa fa-key"></i> Kelola Tautan Aktif</a></li>
                <li class="<?= $d['tab'] === 'logs' ? 'active' : '' ?>"><a href="<?= $d['moduleLink'] ?>&tab=logs"><i class="fa fa-shield"></i> Audit Log Keamanan</a></li>
                <li class="<?= $d['tab'] === 'settings' ? 'active' : '' ?>"><a href="<?= $d['moduleLink'] ?>&tab=settings"><i class="fa fa-cog"></i> Pengaturan Modul</a></li>
            </ul>

            <div class="gi-content">
                <?php if ($d['tab'] === 'dashboard'): ?>
                    <!-- DASHBOARD -->
                    <div class="gi-stats-grid">
                        <div class="gi-stat-card">
                            <div class="gi-stat-num text-success"><?= $d['stats']['active_tokens'] ?></div>
                            <div class="gi-stat-label">Tautan Aktif</div>
                        </div>
                        <div class="gi-stat-card">
                            <div class="gi-stat-num text-info"><?= $d['stats']['total_accesses'] ?></div>
                            <div class="gi-stat-label">Total Akses Pelanggan</div>
                        </div>
                        <div class="gi-stat-card">
                            <div class="gi-stat-num text-warning"><?= $d['stats']['expired_tokens'] ?></div>
                            <div class="gi-stat-label">Kedaluwarsa</div>
                        </div>
                        <div class="gi-stat-card">
                            <div class="gi-stat-num text-muted"><?= $d['stats']['revoked_tokens'] ?></div>
                            <div class="gi-stat-label">Dicabut (Revoked)</div>
                        </div>
                    </div>

                    <div class="panel panel-default gi-panel">
                        <div class="panel-heading"><h3 class="panel-title"><i class="fa fa-history"></i> Aktivitas Keamanan & Akses Terakhir</h3></div>
                        <div class="panel-body">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Waktu</th>
                                        <th>Tipe Aksi</th>
                                        <th>Hasil</th>
                                        <th>Invoice</th>
                                        <th>Aktor</th>
                                        <th>IP Hash</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($d['stats']['recent_logs'] as $log): ?>
                                        <tr>
                                            <td><?= $log->created_at ?></td>
                                            <td><code><?= htmlspecialchars($log->event_type) ?></code></td>
                                            <td>
                                                <span class="label label-<?= $log->result === 'success' ? 'success' : 'danger' ?>">
                                                    <?= htmlspecialchars($log->result) ?>
                                                </span>
                                            </td>
                                            <td><?= $log->invoice_id ? '#' . $log->invoice_id : '-' ?></td>
                                            <td><?= htmlspecialchars($log->actor_type) ?></td>
                                            <td><small><?= substr($log->ip_hash ?? '', 0, 10) ?>...</small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($d['tab'] === 'generate'): ?>
                    <!-- GENERATE SINGLE / BULK -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="panel panel-default gi-panel">
                                <div class="panel-heading"><h3 class="panel-title"><i class="fa fa-link"></i> Buat Tautan Single Invoice</h3></div>
                                <div class="panel-body">
                                    <form method="post" action="<?= $d['moduleLink'] ?>&tab=generate">
                                        <input type="hidden" name="token" value="<?= $d['csrf_token'] ?>">
                                        <input type="hidden" name="action" value="create_single">
                                        <div class="form-group">
                                            <label>Invoice ID WHMCS:</label>
                                            <input type="number" name="invoice_id" class="form-control" placeholder="Contoh: 104" required>
                                            <small class="text-muted">Masukkan nomor identitas invoice (ID internal WHMCS).</small>
                                        </div>
                                        <div class="form-group">
                                            <label>Masa Berlaku Tautan:</label>
                                            <select name="expiry_days" class="form-control">
                                                <option value="7">7 Hari</option>
                                                <option value="14">14 Hari</option>
                                                <option value="30" selected>30 Hari (Standar)</option>
                                                <option value="60">60 Hari</option>
                                                <option value="90">90 Hari</option>
                                            </select>
                                        </div>
                                        <div class="checkbox">
                                            <label>
                                                <input type="checkbox" name="send_email" value="1">
                                                Langsung kirimkan tautan melalui email resmi ke pelanggan
                                            </label>
                                        </div>
                                        <button type="submit" class="btn btn-primary"><i class="fa fa-key"></i> Generate Tautan Guest Invoice</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="panel panel-default gi-panel">
                                <div class="panel-heading"><h3 class="panel-title"><i class="fa fa-cubes"></i> Buat Tautan Massal (Bulk Generator)</h3></div>
                                <div class="panel-body">
                                    <form method="post" action="<?= $d['moduleLink'] ?>&tab=generate">
                                        <input type="hidden" name="token" value="<?= $d['csrf_token'] ?>">
                                        <input type="hidden" name="action" value="create_bulk">
                                        <div class="form-group">
                                            <label>Daftar Invoice ID (Pisahkan dengan koma atau baris baru):</label>
                                            <textarea name="bulk_invoice_ids" class="form-control" rows="5" placeholder="101, 102, 103&#10;104&#10;105"></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label>Masa Berlaku Tautan:</label>
                                            <select name="expiry_days" class="form-control">
                                                <option value="7">7 Hari</option>
                                                <option value="14">14 Hari</option>
                                                <option value="30" selected>30 Hari</option>
                                                <option value="60">60 Hari</option>
                                                <option value="90">90 Hari</option>
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-success"><i class="fa fa-magic"></i> Proses Bulk Generation</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($d['tab'] === 'tokens'): ?>
                    <!-- TOKEN LIST -->
                    <div class="panel panel-default gi-panel">
                        <div class="panel-heading">
                            <h3 class="panel-title"><i class="fa fa-list"></i> Riwayat & Manajemen Tautan Guest Invoice</h3>
                        </div>
                        <div class="panel-body">
                            <form method="get" action="addonmodules.php" class="form-inline" style="margin-bottom: 16px;">
                                <input type="hidden" name="module" value="guestinvoice">
                                <input type="hidden" name="tab" value="tokens">
                                <input type="text" name="search" class="form-control" placeholder="Cari nomor Invoice ID..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                                <button type="submit" class="btn btn-default"><i class="fa fa-search"></i> Filter</button>
                            </form>

                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Invoice ID</th>
                                        <th>Tautan Guest / Payment URL</th>
                                        <th>Status</th>
                                        <th>Dibuat Pada</th>
                                        <th>Kedaluwarsa</th>
                                        <th>Akses Terakhir</th>
                                        <th>Jumlah Akses</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($d['tokens']->isEmpty()): ?>
                                        <tr><td colspan="9" class="text-center text-muted">Belum ada data token tautan.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($d['tokens'] as $tok): ?>
                                        <?php 
                                            $hasRawToken = !empty($tok->raw_token);
                                            $paymentUrl = $hasRawToken ? TokenService::buildGuestUrl($tok->raw_token) : '';
                                        ?>
                                        <tr>
                                            <td>#<?= $tok->id ?></td>
                                            <td><a href="invoices.php?action=edit&id=<?= $tok->invoice_id ?>" target="_blank"><strong>#<?= $tok->invoice_id ?></strong> <i class="fa fa-external-link"></i></a></td>
                                            <td style="min-width: 260px;">
                                                <?php if ($hasRawToken && $tok->status === 'Active'): ?>
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" readonly class="form-control" id="token-url-<?= $tok->id ?>" value="<?= htmlspecialchars($paymentUrl) ?>" style="font-family: monospace; font-size: 11px; background: #fff;" onclick="this.select()">
                                                        <span class="input-group-btn">
                                                            <button type="button" class="btn btn-default" title="Salin Tautan" onclick="copyGuestLink('token-url-<?= $tok->id ?>', this)">
                                                                <i class="fa fa-copy"></i> Salin
                                                            </button>
                                                            <a href="<?= htmlspecialchars($paymentUrl) ?>" target="_blank" class="btn btn-info" title="Buka Halaman Guest">
                                                                <i class="fa fa-external-link"></i>
                                                            </a>
                                                        </span>
                                                    </div>
                                                <?php elseif ($tok->status !== 'Active'): ?>
                                                    <span class="text-muted" style="font-size: 12px; font-style: italic;"><i class="fa fa-lock"></i> Tautan nonaktif (<?= htmlspecialchars($tok->status) ?>)</span>
                                                <?php else: ?>
                                                    <span class="text-muted" style="font-size: 12px;"><em>Dibuat versi lama (token tersimpan ter-hash)</em></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="label label-<?= $tok->status === 'Active' ? 'success' : ($tok->status === 'Expired' ? 'warning' : 'danger') ?>">
                                                    <?= htmlspecialchars($tok->status) ?>
                                                </span>
                                            </td>
                                            <td><?= $tok->created_at ?></td>
                                            <td><?= $tok->expires_at ?></td>
                                            <td><?= $tok->last_access_at ?: '-' ?></td>
                                            <td><span class="badge"><?= $tok->access_count ?></span></td>
                                            <td>
                                                <?php if ($tok->status === 'Active'): ?>
                                                    <form method="post" action="<?= $d['moduleLink'] ?>&tab=tokens" style="display:inline-block;" onsubmit="return confirm('Apakah Anda yakin ingin mencabut akses tautan ini?');">
                                                        <input type="hidden" name="token" value="<?= $d['csrf_token'] ?>">
                                                        <input type="hidden" name="action" value="revoke_token">
                                                        <input type="hidden" name="token_id" value="<?= $tok->id ?>">
                                                        <button type="submit" class="btn btn-danger btn-xs"><i class="fa fa-ban"></i> Cabut (Revoke)</button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="post" action="<?= $d['moduleLink'] ?>&tab=tokens" style="display:inline-block;">
                                                    <input type="hidden" name="token" value="<?= $d['csrf_token'] ?>">
                                                    <input type="hidden" name="action" value="send_email">
                                                    <input type="hidden" name="invoice_id" value="<?= $tok->invoice_id ?>">
                                                    <button type="submit" class="btn btn-info btn-xs"><i class="fa fa-envelope"></i> Kirim Ulang Email</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($d['tab'] === 'logs'): ?>
                    <!-- AUDIT LOGS -->
                    <div class="panel panel-default gi-panel">
                        <div class="panel-heading"><h3 class="panel-title"><i class="fa fa-shield"></i> Audit Log Forensik & Keamanan</h3></div>
                        <div class="panel-body">
                            <p class="text-muted">Setiap aktivitas pembuatan token, akses publik, kegagalan rate limit, dan callback gateway dicatat secara terlindungi tanpa menyimpan token mentah atau data sensitif kartu.</p>
                            <table class="table table-bordered table-condensed table-striped" style="font-size: 13px;">
                                <thead>
                                    <tr>
                                        <th>Waktu (UTC/Server)</th>
                                        <th>Event Type</th>
                                        <th>Hasil</th>
                                        <th>Invoice</th>
                                        <th>Aktor</th>
                                        <th>IP Salted Hash</th>
                                        <th>Detail Metadata</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($d['logs'] as $log): ?>
                                        <tr>
                                            <td><?= $log->created_at ?></td>
                                            <td><code><?= htmlspecialchars($log->event_type) ?></code></td>
                                            <td><span class="label label-<?= $log->result === 'success' ? 'success' : 'danger' ?>"><?= htmlspecialchars($log->result) ?></span></td>
                                            <td><?= $log->invoice_id ? '#' . $log->invoice_id : '-' ?></td>
                                            <td><?= htmlspecialchars($log->actor_type) ?></td>
                                            <td><small><?= substr($log->ip_hash ?? '', 0, 12) ?>...</small></td>
                                            <td><small class="text-muted"><?= htmlspecialchars($log->details ?? '-') ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <form method="post" action="<?= $d['moduleLink'] ?>&tab=logs" onsubmit="return confirm('Prune log lama?');">
                                <input type="hidden" name="token" value="<?= $d['csrf_token'] ?>">
                                <input type="hidden" name="action" value="prune_logs">
                                <input type="hidden" name="prune_days" value="90">
                                <button type="submit" class="btn btn-default btn-xs text-danger"><i class="fa fa-trash"></i> Bersihkan Log Lebih dari 90 Hari</button>
                            </form>
                        </div>
                    </div>

                <?php elseif ($d['tab'] === 'settings'): ?>
                    <!-- SETTINGS -->
                    <div class="panel panel-default gi-panel">
                        <div class="panel-heading"><h3 class="panel-title"><i class="fa fa-cogs"></i> Konfigurasi Kebijakan Modul</h3></div>
                        <div class="panel-body">
                            <form method="post" action="<?= $d['moduleLink'] ?>&tab=settings">
                                <input type="hidden" name="token" value="<?= $d['csrf_token'] ?>">
                                <input type="hidden" name="action" value="save_settings">

                                <div class="form-group">
                                    <label>Judul Branding Portal Guest:</label>
                                    <input type="text" name="custom_branding_title" class="form-control" value="<?= htmlspecialchars($d['settings']['custom_branding_title'] ?? 'Portal Pembayaran & Tagihan Aman') ?>">
                                </div>

                                <div class="form-group">
                                    <label>Masa Berlaku Default Token (Hari):</label>
                                    <input type="number" name="default_expiry_days" class="form-control" style="max-width: 200px;" value="<?= htmlspecialchars($d['settings']['default_expiry_days'] ?? '30') ?>">
                                </div>

                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="auto_generate_on_invoice_created" value="1" <?= ($d['settings']['auto_generate_on_invoice_created'] ?? '1') === '1' ? 'checked' : '' ?>>
                                        <strong>Otomatis Buat Tautan Guest Saat Invoice Baru Terbit</strong> (via hook <code>InvoiceCreated</code>)
                                    </label>
                                </div>

                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="auto_email_on_invoice_created" value="1" <?= ($d['settings']['auto_email_on_invoice_created'] ?? '0') === '1' ? 'checked' : '' ?>>
                                        <strong>Otomatis Kirimkan Email Tautan Guest Saat Invoice Baru Terbit</strong>
                                    </label>
                                </div>

                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="enable_guest_payment" value="1" <?= ($d['settings']['enable_guest_payment'] ?? '1') === '1' ? 'checked' : '' ?>>
                                        <strong>Izinkan Pembayaran Online Tanpa Login</strong> (Jika nonaktif, pelanggan hanya dapat melihat invoice)
                                    </label>
                                </div>

                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="allow_pdf_download" value="1" <?= ($d['settings']['allow_pdf_download'] ?? '0') === '1' ? 'checked' : '' ?>>
                                        <strong>Izinkan Pengunduhan Berkas PDF Invoice</strong>
                                    </label>
                                </div>

                                <hr>
                                <h4>Pengaturan Keamanan & Rate Limiting</h4>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Batas Percobaan Akses per Menit:</label>
                                            <input type="number" name="rate_limit_per_minute" class="form-control" value="<?= htmlspecialchars($d['settings']['rate_limit_per_minute'] ?? '15') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Durasi Lockout (Menit):</label>
                                            <input type="number" name="rate_limit_lockout_minutes" class="form-control" value="<?= htmlspecialchars($d['settings']['rate_limit_lockout_minutes'] ?? '15') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Retensi Log (Hari):</label>
                                            <input type="number" name="log_retention_days" class="form-control" value="<?= htmlspecialchars($d['settings']['log_retention_days'] ?? '90') ?>">
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Pengaturan</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <script src="../modules/addons/guestinvoice/assets/js/admin.js"></script>
        <?php
        return ob_get_clean();
    }
}
