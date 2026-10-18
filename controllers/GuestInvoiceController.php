<?php

namespace GuestInvoice\Controllers;

use WHMCS\Database\Capsule;
use GuestInvoice\Lib\TokenService;
use GuestInvoice\Lib\InvoiceService;
use GuestInvoice\Lib\AccessPolicy;
use GuestInvoice\Lib\RateLimiter;
use GuestInvoice\Lib\AuditLogger;

class GuestInvoiceController
{
    /**
     * Send HTTP security headers for all guest responses.
     */
    public static function applySecurityHeaders(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Referrer-Policy: no-referrer');
            header('X-Robots-Tag: noindex, nofollow, noarchive');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
        }
    }

    /**
     * Dispatch guest invoice view request.
     *
     * @param array $vars WHMCS module clientarea parameters
     * @return array WHMCS template array
     */
    public static function handle(array $vars): array
    {
        self::applySecurityHeaders();

        $rawToken = $_GET['token'] ?? '';
        $action = $_GET['action'] ?? 'view';

        // 1. Rate limiting check
        if (!RateLimiter::isAllowed('token_lookup', 15, 60)) {
            $lockoutRemaining = RateLimiter::getRemainingLockoutSeconds('token_lookup');
            return [
                'pagetitle' => 'Akses Dibatasi Sementara',
                'breadcrumb' => ['index.php?m=guestinvoice' => 'Keamanan'],
                'templatefile' => 'errors',
                'requirelogin' => false,
                'vars' => [
                    'error_title' => 'Terlalu Banyak Percobaan',
                    'error_message' => 'Anda telah melebihi batas percobaan akses. Silakan coba kembali dalam ' . ceil($lockoutRemaining / 60) . ' menit demi keamanan.',
                    'error_code' => 429,
                ],
            ];
        }

        // 2. Validate token
        $tokenRecord = TokenService::validateToken($rawToken);
        if (!$tokenRecord) {
            RateLimiter::recordAttempt('token_lookup', 15, 60, 15);

            AuditLogger::log([
                'event_type' => 'access_denied',
                'result' => 'denied',
                'actor_type' => 'guest',
                'details' => json_encode(['reason' => 'Invalid, expired, or revoked token attempt']),
            ]);

            return [
                'pagetitle' => 'Tautan Tagihan Tidak Valid',
                'breadcrumb' => ['index.php?m=guestinvoice' => 'Invoice'],
                'templatefile' => 'errors',
                'requirelogin' => false,
                'vars' => [
                    'error_title' => 'Tautan Tagihan Tidak Valid atau Kedaluwarsa',
                    'error_message' => 'Tautan yang Anda gunakan tidak ditemukan, telah kedaluwarsa, atau telah dicabut. Silakan hubungi bagian penagihan untuk mendapatkan tautan baru.',
                    'error_code' => 404,
                ],
            ];
        }

        // 3. Retrieve invoice data
        $invoiceData = InvoiceService::getInvoiceData($tokenRecord->invoice_id);
        if (!$invoiceData) {
            return [
                'pagetitle' => 'Tagihan Tidak Ditemukan',
                'breadcrumb' => ['index.php?m=guestinvoice' => 'Invoice'],
                'templatefile' => 'errors',
                'requirelogin' => false,
                'vars' => [
                    'error_title' => 'Tagihan Tidak Ditemukan',
                    'error_message' => 'Informasi tagihan tidak dapat dimuat. Silakan hubungi bagian penagihan.',
                    'error_code' => 404,
                ],
            ];
        }

        // 4. Policy check
        $invObj = (object)[
            'id' => $invoiceData['id'],
            'status' => $invoiceData['status'],
            'balance' => $invoiceData['balance'],
            'total' => $invoiceData['total'],
        ];

        $viewCheck = AccessPolicy::canViewInvoice($tokenRecord, $invObj);
        if (!$viewCheck['allowed']) {
            return [
                'pagetitle' => 'Akses Ditolak',
                'breadcrumb' => ['index.php?m=guestinvoice' => 'Invoice'],
                'templatefile' => 'errors',
                'requirelogin' => false,
                'vars' => [
                    'error_title' => 'Akses Tagihan Tidak Diizinkan',
                    'error_message' => $viewCheck['reason'],
                    'error_code' => 403,
                ],
            ];
        }

        // 5. Handle PDF download action if requested
        if ($action === 'download') {
            return self::handleDownload($tokenRecord, $invoiceData, $rawToken);
        }

        // 6. Record successful access hit & log
        TokenService::recordAccess($tokenRecord->id);
        AuditLogger::log([
            'token_id' => $tokenRecord->id,
            'invoice_id' => $tokenRecord->invoice_id,
            'event_type' => 'invoice_viewed',
            'result' => 'success',
            'actor_type' => 'guest',
        ]);

        $payCheck = AccessPolicy::canPayInvoice($tokenRecord, $invObj);
        $gateways = $payCheck['payable'] ? InvoiceService::getAvailableGateways($invoiceData['id']) : [];

        // Check PDF download setting
        $allowPdf = AccessPolicy::canDownloadPdf($tokenRecord, $invObj);

        return [
            'pagetitle' => 'Invoice #' . $invoiceData['invoicenum'] . ' — ' . $invoiceData['company']['name'],
            'breadcrumb' => ['index.php?m=guestinvoice' => 'Invoice #' . $invoiceData['invoicenum']],
            'templatefile' => 'invoice',
            'requirelogin' => false,
            'vars' => [
                'invoice' => $invoiceData,
                'token' => $rawToken,
                'is_payable' => $payCheck['payable'],
                'gateways' => $gateways,
                'allow_pdf_download' => $allowPdf,
                'pay_url' => 'index.php?m=guestinvoice&token=' . urlencode($rawToken) . '&action=pay',
                'download_url' => 'index.php?m=guestinvoice&token=' . urlencode($rawToken) . '&action=download',
            ],
        ];
    }

    /**
     * Stream native PDF invoice download if enabled.
     */
    private static function handleDownload(object $tokenRecord, array $invoiceData, string $rawToken): array
    {
        $invObj = (object)[
            'id' => $invoiceData['id'],
            'status' => $invoiceData['status'],
        ];

        if (!AccessPolicy::canDownloadPdf($tokenRecord, $invObj)) {
            return [
                'pagetitle' => 'Unduhan Dinonaktifkan',
                'breadcrumb' => ['index.php?m=guestinvoice' => 'Invoice'],
                'templatefile' => 'errors',
                'requirelogin' => false,
                'vars' => [
                    'error_title' => 'Fitur Unduh Nonaktif',
                    'error_message' => 'Pengunduhan berkas PDF dinonaktifkan oleh administrator.',
                    'error_code' => 403,
                ],
            ];
        }

        // Generate PDF using WHMCS localAPI or native helper
        if (function_exists('localAPI')) {
            $pdfData = localAPI('GetInvoice', ['invoiceid' => $invoiceData['id']]);
            // If base64 PDF is returned, stream to browser
            if (!empty($pdfData['pdf'])) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="Invoice-' . $invoiceData['invoicenum'] . '.pdf"');
                echo base64_decode($pdfData['pdf']);
                exit;
            }
        }

        // Fallback: If direct PDF stream not available in current environment, return friendly message
        return [
            'pagetitle' => 'Unduh PDF',
            'breadcrumb' => ['index.php?m=guestinvoice' => 'Invoice'],
            'templatefile' => 'errors',
            'requirelogin' => false,
            'vars' => [
                'error_title' => 'Cetak Tagihan',
                'error_message' => 'Silakan gunakan tombol cetak peramban (Ctrl+P / Command+P) untuk menyimpan invoice dalam format PDF.',
                'error_code' => 200,
            ],
        ];
    }
}
