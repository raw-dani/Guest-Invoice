<?php

namespace GuestInvoice\Controllers;

use WHMCS\Database\Capsule;
use GuestInvoice\Lib\TokenService;
use GuestInvoice\Lib\InvoiceService;

class PaymentResultController
{
    /**
     * Display verified payment status page after gateway return.
     *
     * @param array $vars
     * @return array WHMCS template array
     */
    public static function handle(array $vars): array
    {
        GuestInvoiceController::applySecurityHeaders();

        $rawToken = $_GET['token'] ?? '';
        $tokenRecord = TokenService::validateToken($rawToken);

        if (!$tokenRecord) {
            return [
                'pagetitle' => 'Tautan Tidak Valid',
                'breadcrumb' => ['index.php?m=guestinvoice' => 'Hasil Pembayaran'],
                'templatefile' => 'errors',
                'requirelogin' => false,
                'vars' => [
                    'error_title' => 'Tautan Tidak Dikenali',
                    'error_message' => 'Tautan akses invoice tidak valid atau telah kedaluwarsa.',
                    'error_code' => 404,
                ],
            ];
        }

        // Fetch fresh verified invoice state directly from WHMCS database
        $invoiceData = InvoiceService::getInvoiceData($tokenRecord->invoice_id);
        if (!$invoiceData) {
            return [
                'pagetitle' => 'Tagihan Tidak Ditemukan',
                'breadcrumb' => ['index.php?m=guestinvoice' => 'Hasil Pembayaran'],
                'templatefile' => 'errors',
                'requirelogin' => false,
                'vars' => [
                    'error_title' => 'Tagihan Tidak Ditemukan',
                    'error_message' => 'Data tagihan tidak dapat dimuat.',
                    'error_code' => 404,
                ],
            ];
        }

        $status = strtolower($invoiceData['status']);
        $isPaid = ($status === 'paid');
        $isPending = in_array($status, ['unpaid', 'overdue', 'payment pending'], true);

        return [
            'pagetitle' => 'Status Pembayaran — Invoice #' . $invoiceData['invoicenum'],
            'breadcrumb' => ['index.php?m=guestinvoice' => 'Hasil Pembayaran'],
            'templatefile' => 'payment-result',
            'requirelogin' => false,
            'vars' => [
                'invoice' => $invoiceData,
                'token' => $rawToken,
                'is_paid' => $isPaid,
                'is_pending' => $isPending,
                'status_label' => $invoiceData['status'],
                'invoice_url' => 'index.php?m=guestinvoice&token=' . urlencode($rawToken),
                'refresh_url' => 'index.php?m=guestinvoice&token=' . urlencode($rawToken) . '&action=result',
            ],
        ];
    }
}
