<?php

namespace GuestInvoice\Lib;

use WHMCS\Database\Capsule;

class AccessPolicy
{
    /**
     * Evaluate if a guest can view the invoice.
     *
     * @param object $tokenRecord
     * @param object $invoiceRecord
     * @param array $settings
     * @return array ['allowed' => bool, 'reason' => string]
     */
    public static function canViewInvoice(object $tokenRecord, object $invoiceRecord, array $settings = []): array
    {
        // 1. Verify token belongs to this invoice
        if ((int)$tokenRecord->invoice_id !== (int)$invoiceRecord->id) {
            return ['allowed' => false, 'reason' => 'Token invoice mismatch.'];
        }

        // 2. Verify token is active
        if ($tokenRecord->status !== 'Active') {
            return ['allowed' => false, 'reason' => 'Token is not active.'];
        }

        // 3. Verify token not expired
        if ($tokenRecord->expires_at < date('Y-m-d H:i:s')) {
            return ['allowed' => false, 'reason' => 'Token has expired.'];
        }

        // 4. Verify token not revoked
        if (!empty($tokenRecord->revoked_at)) {
            return ['allowed' => false, 'reason' => 'Token has been revoked.'];
        }

        // 5. Evaluate invoice status policy
        $status = strtolower($invoiceRecord->status);
        if ($status === 'draft') {
            return ['allowed' => false, 'reason' => 'Draft invoices cannot be viewed publicly.'];
        }

        return ['allowed' => true, 'reason' => 'Access granted.'];
    }

    /**
     * Evaluate if an invoice is currently payable via guest flow.
     *
     * @param object $tokenRecord
     * @param object $invoiceRecord
     * @param array $settings
     * @return array ['payable' => bool, 'reason' => string]
     */
    public static function canPayInvoice(object $tokenRecord, object $invoiceRecord, array $settings = []): array
    {
        // First must be viewable
        $viewCheck = self::canViewInvoice($tokenRecord, $invoiceRecord, $settings);
        if (!$viewCheck['allowed']) {
            return ['payable' => false, 'reason' => $viewCheck['reason']];
        }

        // Module-wide guest payment switch
        $enableGuestPayment = $settings['enable_guest_payment'] ?? '1';
        if ($enableGuestPayment !== '1' && $enableGuestPayment !== true) {
            return ['payable' => false, 'reason' => 'Guest payments are currently disabled by administrator.'];
        }

        $status = strtolower($invoiceRecord->status);

        // Invoices that are already Paid CANNOT be paid again!
        if ($status === 'paid') {
            return ['payable' => false, 'reason' => 'Invoice has already been marked as Paid.'];
        }

        // Cancelled or refunded invoices cannot be paid
        if (in_array($status, ['cancelled', 'refunded', 'collections', 'draft'], true)) {
            return ['payable' => false, 'reason' => "Invoices with status '{$invoiceRecord->status}' cannot be paid."];
        }

        // Verify balance due is strictly positive
        $balance = (float)($invoiceRecord->balance ?? 0);
        if ($balance <= 0) {
            // Also check subtotal/total calculation if balance is zero or null
            $total = (float)($invoiceRecord->total ?? 0);
            if ($total <= 0) {
                return ['payable' => false, 'reason' => 'Invoice has zero balance due.'];
            }
        }

        return ['payable' => true, 'reason' => 'Invoice is payable.'];
    }

    /**
     * Evaluate if downloading PDF invoice is permitted.
     *
     * @param object $tokenRecord
     * @param object $invoiceRecord
     * @param array $settings
     * @return bool
     */
    public static function canDownloadPdf(object $tokenRecord, object $invoiceRecord, array $settings = []): bool
    {
        $allowDownload = $settings['allow_pdf_download'] ?? '0';
        if ($allowDownload !== '1' && $allowDownload !== true) {
            return false;
        }

        $viewCheck = self::canViewInvoice($tokenRecord, $invoiceRecord, $settings);
        return $viewCheck['allowed'];
    }
}
