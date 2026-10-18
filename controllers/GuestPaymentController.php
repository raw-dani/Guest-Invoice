<?php

namespace GuestInvoice\Controllers;

use WHMCS\Database\Capsule;
use GuestInvoice\Lib\TokenService;
use GuestInvoice\Lib\InvoiceService;
use GuestInvoice\Lib\AccessPolicy;
use GuestInvoice\Lib\PaymentService;
use GuestInvoice\Lib\RateLimiter;

class GuestPaymentController
{
    /**
     * Handle payment initialization for guest invoice.
     *
     * @param array $vars
     * @return array WHMCS template array
     */
    public static function handle(array $vars): array
    {
        GuestInvoiceController::applySecurityHeaders();

        $rawToken = $_GET['token'] ?? $_POST['token'] ?? '';
        $selectedGateway = $_POST['gateway'] ?? $_GET['gateway'] ?? '';

        if (!RateLimiter::isAllowed('pay_attempt', 10, 60)) {
            return [
                'pagetitle' => 'Batas Pembayaran Tercapai',
                'breadcrumb' => ['index.php?m=guestinvoice' => 'Pembayaran'],
                'templatefile' => 'errors',
                'requirelogin' => false,
                'vars' => [
                    'error_title' => 'Terlalu Banyak Permintaan Pembayaran',
                    'error_message' => 'Silakan tunggu beberapa saat sebelum mencoba memproses pembayaran kembali.',
                    'error_code' => 429,
                ],
            ];
        }

        $tokenRecord = TokenService::validateToken($rawToken);
        if (!$tokenRecord) {
            return [
                'pagetitle' => 'Tautan Tidak Valid',
                'breadcrumb' => ['index.php?m=guestinvoice' => 'Pembayaran'],
                'templatefile' => 'errors',
                'requirelogin' => false,
                'vars' => [
                    'error_title' => 'Tautan Pembayaran Tidak Valid',
                    'error_message' => 'Tautan tagihan Anda tidak valid atau telah kedaluwarsa.',
                    'error_code' => 404,
                ],
            ];
        }

        $invoiceData = InvoiceService::getInvoiceData($tokenRecord->invoice_id);
        if (!$invoiceData) {
            return [
                'pagetitle' => 'Tagihan Tidak Ditemukan',
                'breadcrumb' => ['index.php?m=guestinvoice' => 'Pembayaran'],
                'templatefile' => 'errors',
                'requirelogin' => false,
                'vars' => [
                    'error_title' => 'Tagihan Tidak Ditemukan',
                    'error_message' => 'Data tagihan tidak dapat ditemukan di database.',
                    'error_code' => 404,
                ],
            ];
        }

        $availableGateways = InvoiceService::getAvailableGateways($invoiceData['id']);

        // Default to invoice's payment method or first available gateway if none selected
        if (empty($selectedGateway)) {
            $selectedGateway = $invoiceData['paymentmethod'];
            if (empty($selectedGateway) && !empty($availableGateways)) {
                $selectedGateway = $availableGateways[0]['module'];
            }
        }

        // Process guest payment via PaymentService
        $result = PaymentService::processGuestPayment($tokenRecord, $selectedGateway, $rawToken);

        if (!$result['success']) {
            return [
                'pagetitle' => 'Pembayaran Gagal Dimulai',
                'breadcrumb' => ['index.php?m=guestinvoice' => 'Pembayaran'],
                'templatefile' => 'errors',
                'requirelogin' => false,
                'vars' => [
                    'error_title' => 'Gagal Memulai Pembayaran',
                    'error_message' => $result['error'] ?? 'Terjadi kendala saat menghubungkan ke penyedia pembayaran.',
                    'error_code' => 500,
                    'back_url' => 'index.php?m=guestinvoice&token=' . urlencode($rawToken),
                ],
            ];
        }

        // Handle Redirect
        if ($result['type'] === 'redirect' && !empty($result['redirect_url'])) {
            header('Location: ' . $result['redirect_url']);
            exit;
        }

        // Handle Instructions (e.g. Bank Transfer Manual)
        return [
            'pagetitle' => 'Instruksi Pembayaran — Invoice #' . $invoiceData['invoicenum'],
            'breadcrumb' => ['index.php?m=guestinvoice' => 'Instruksi Pembayaran'],
            'templatefile' => 'payment',
            'requirelogin' => false,
            'vars' => [
                'invoice' => $invoiceData,
                'token' => $rawToken,
                'gateway' => $selectedGateway,
                'gateways' => $availableGateways,
                'payment_type' => $result['type'],
                'instructions' => $result['instructions'] ?? null,
                'html_form' => $result['html'] ?? null,
                'redirect_url' => $result['redirect_url'] ?? null,
                'invoice_url' => 'index.php?m=guestinvoice&token=' . urlencode($rawToken),
            ],
        ];
    }
}
