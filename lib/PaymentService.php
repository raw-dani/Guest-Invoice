<?php

namespace GuestInvoice\Lib;

use WHMCS\Database\Capsule;
use GuestInvoice\Lib\Gateway\GatewayInterface;
use GuestInvoice\Lib\Gateway\BankTransferAdapter;
use GuestInvoice\Lib\Gateway\MidtransAdapter;
use GuestInvoice\Lib\Gateway\XenditAdapter;
use GuestInvoice\Lib\Gateway\TripayAdapter;
use GuestInvoice\Lib\Gateway\DokuAdapter;
use GuestInvoice\Lib\Gateway\DuitkuAdapter;
use GuestInvoice\Lib\Gateway\WhmcsNativeAdapter;

class PaymentService
{
    /**
     * Factory method to obtain appropriate gateway adapter.
     */
    public static function getAdapter(string $gatewayName): GatewayInterface
    {
        $gw = strtolower(trim($gatewayName));

        if ($gw === 'banktransfer' || strpos($gw, 'bank') !== false || strpos($gw, 'transfer') !== false) {
            return new BankTransferAdapter($gatewayName);
        }

        if (strpos($gw, 'midtrans') !== false || strpos($gw, 'veritrans') !== false) {
            return new MidtransAdapter($gatewayName);
        }

        if (strpos($gw, 'xendit') !== false) {
            return new XenditAdapter($gatewayName);
        }

        if (strpos($gw, 'tripay') !== false) {
            return new TripayAdapter($gatewayName);
        }

        if (strpos($gw, 'doku') !== false) {
            return new DokuAdapter($gatewayName);
        }

        if (strpos($gw, 'duitku') !== false) {
            return new DuitkuAdapter($gatewayName);
        }

        return new WhmcsNativeAdapter($gatewayName);
    }

    /**
     * Initiate payment for a guest invoice.
     *
     * @param object $tokenRecord
     * @param string $gatewayName
     * @param string $rawToken
     * @return array
     */
    public static function processGuestPayment(object $tokenRecord, string $gatewayName, string $rawToken): array
    {
        $invoiceId = (int)$tokenRecord->invoice_id;
        $invoiceData = InvoiceService::getInvoiceData($invoiceId);

        if (!$invoiceData) {
            return ['success' => false, 'error' => 'Invoice data could not be retrieved.'];
        }

        $invObj = (object)[
            'id' => $invoiceData['id'],
            'status' => $invoiceData['status'],
            'balance' => $invoiceData['balance'],
            'total' => $invoiceData['total'],
        ];

        $policyCheck = AccessPolicy::canPayInvoice($tokenRecord, $invObj);
        if (!$policyCheck['payable']) {
            return ['success' => false, 'error' => $policyCheck['reason']];
        }

        $adapter = self::getAdapter($gatewayName);
        $systemUrl = TokenService::getWhmcsSystemUrl();
        $returnUrl = rtrim($systemUrl, '/') . '/index.php?m=guestinvoice&token=' . urlencode($rawToken) . '&action=result';

        AuditLogger::log([
            'token_id' => $tokenRecord->id,
            'invoice_id' => $invoiceId,
            'event_type' => 'payment_initiated',
            'result' => 'success',
            'actor_type' => 'guest',
            'details' => json_encode(['gateway' => $gatewayName, 'amount' => $invoiceData['balance']]),
        ]);

        return $adapter->initiatePayment($invoiceData, $returnUrl);
    }

    /**
     * Process and verify an incoming webhook from payment gateway.
     *
     * @param string $gatewayName
     * @param array $headers
     * @param mixed $payload
     * @return array
     */
    public static function processWebhook(string $gatewayName, array $headers, $payload): array
    {
        $adapter = self::getAdapter($gatewayName);
        $verified = $adapter->verifyWebhook($headers, $payload);

        if (!$verified['valid']) {
            AuditLogger::log([
                'event_type' => 'webhook_rejected',
                'result' => 'denied',
                'actor_type' => 'system',
                'details' => json_encode([
                    'gateway' => $gatewayName,
                    'error' => $verified['error'] ?? 'Signature validation failed',
                ]),
            ]);

            return [
                'success' => false,
                'http_status' => 400,
                'message' => $verified['error'] ?? 'Webhook verification failed',
            ];
        }

        $invoiceId = (int)$verified['invoice_id'];
        $transId = $verified['transaction_id'];
        $amount = (float)$verified['amount'];
        $fee = (float)($verified['fee'] ?? 0.0);
        $status = $verified['status'];

        if ($invoiceId <= 0) {
            return [
                'success' => false,
                'http_status' => 400,
                'message' => 'Missing valid invoice ID in callback payload.',
            ];
        }

        // Retrieve current invoice state from WHMCS
        $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
        if (!$invoice) {
            return [
                'success' => false,
                'http_status' => 404,
                'message' => 'Referenced invoice does not exist.',
            ];
        }

        // Idempotency check: Has this transaction already been recorded?
        $existingTx = Capsule::table('tblaccounts')
            ->where('transid', $transId)
            ->where('invoiceid', $invoiceId)
            ->first();

        if ($existingTx) {
            return [
                'success' => true,
                'http_status' => 200,
                'message' => 'Transaction already recorded (idempotent callback).',
            ];
        }

        // Handle Paid status
        if ($status === 'paid') {
            self::recordWhmcsPayment($invoiceId, $transId, $amount, $fee, $gatewayName);

            AuditLogger::log([
                'invoice_id' => $invoiceId,
                'event_type' => 'payment_success',
                'result' => 'success',
                'actor_type' => 'system',
                'details' => json_encode([
                    'gateway' => $gatewayName,
                    'trans_id' => $transId,
                    'amount' => $amount,
                ]),
            ]);

            return [
                'success' => true,
                'http_status' => 200,
                'message' => 'Payment recorded and invoice marked Paid successfully.',
            ];
        }

        // Handle Pending or Failed
        AuditLogger::log([
            'invoice_id' => $invoiceId,
            'event_type' => 'payment_status_update',
            'result' => $status,
            'actor_type' => 'system',
            'details' => json_encode([
                'gateway' => $gatewayName,
                'trans_id' => $transId,
                'status' => $status,
            ]),
        ]);

        return [
            'success' => true,
            'http_status' => 200,
            'message' => 'Status acknowledged: ' . $status,
        ];
    }

    /**
     * Record payment into WHMCS using official API or database recording.
     */
    public static function recordWhmcsPayment(int $invoiceId, string $transId, float $amount, float $fee, string $gateway): void
    {
        // Try WHMCS localAPI if available
        if (function_exists('localAPI')) {
            $results = localAPI('AddInvoicePayment', [
                'invoiceid' => $invoiceId,
                'transid' => $transId,
                'gateway' => $gateway,
                'date' => date('Y-m-d H:i:s'),
                'amount' => $amount,
                'fees' => $fee,
            ]);

            if (($results['result'] ?? '') === 'success') {
                return;
            }
        }

        // Database fallback
        $now = date('Y-m-d H:i:s');
        $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
        if (!$invoice) {
            return;
        }

        // Insert into tblaccounts
        Capsule::table('tblaccounts')->insert([
            'userid' => $invoice->userid,
            'currency' => 0,
            'gateway' => $gateway,
            'date' => $now,
            'description' => 'Invoice Payment #' . $invoiceId . ' (' . $transId . ')',
            'amountin' => $amount,
            'fees' => $fee,
            'amountout' => 0,
            'rate' => 1,
            'transid' => $transId,
            'invoiceid' => $invoiceId,
        ]);

        // Calculate remaining balance
        $totalPaid = Capsule::table('tblaccounts')->where('invoiceid', $invoiceId)->sum('amountin');
        if ($totalPaid >= (float)$invoice->total) {
            Capsule::table('tblinvoices')->where('id', $invoiceId)->update([
                'status' => 'Paid',
                'datepaid' => $now,
            ]);
        }
    }
}
