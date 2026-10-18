<?php

namespace GuestInvoice\Lib\Gateway;

use WHMCS\Database\Capsule;

class MidtransAdapter extends AbstractGatewayAdapter
{
    public function __construct(string $gatewayName = 'midtrans')
    {
        parent::__construct($gatewayName);
    }

    private function isProduction(): bool
    {
        $val = strtolower($this->config['environment'] ?? $this->config['is_production'] ?? 'sandbox');
        return ($val === 'production' || $val === 'on' || $val === '1' || $val === 'live');
    }

    private function getServerKey(): string
    {
        return trim($this->config['server_key'] ?? $this->config['serverKey'] ?? $this->config['merchant_server_key'] ?? '');
    }

    public function initiatePayment(array $invoice, string $returnUrl): array
    {
        $serverKey = $this->getServerKey();
        if (empty($serverKey)) {
            return [
                'success' => false,
                'error' => 'Midtrans Server Key is not configured in WHMCS Payment Gateway settings.',
            ];
        }

        $apiUrl = $this->isProduction()
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';

        $orderId = 'INV-' . $invoice['id'] . '-' . time();
        $grossAmount = (int)round($invoice['balance']);

        $client = $invoice['client'] ?? [];
        $nameParts = explode(' ', $client['fullname'] ?? 'Customer', 2);
        $firstName = $nameParts[0] ?? 'Customer';
        $lastName = $nameParts[1] ?? '';

        $payload = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $client['email'] ?? 'billing@domain.com',
                'phone' => $client['phonenumber'] ?? '',
            ],
            'callbacks' => [
                'finish' => $returnUrl,
            ],
        ];

        $authHeader = 'Basic ' . base64_encode($serverKey . ':');
        $res = $this->postJson($apiUrl, $payload, ['Authorization: ' . $authHeader]);

        if (!$res['success'] || empty($res['data']['redirect_url'])) {
            return [
                'success' => false,
                'error' => 'Midtrans Snap creation error: ' . ($res['error'] ?? 'Unknown gateway error'),
            ];
        }

        return [
            'success' => true,
            'type' => 'redirect',
            'redirect_url' => $res['data']['redirect_url'],
            'token' => $res['data']['token'] ?? null,
            'instructions' => null,
            'html' => null,
            'error' => null,
        ];
    }

    public function verifyWebhook(array $headers, $payload): array
    {
        if (is_string($payload)) {
            $payload = json_decode($payload, true) ?: [];
        }

        $orderId = $payload['order_id'] ?? '';
        $statusCode = $payload['status_code'] ?? '';
        $grossAmount = $payload['gross_amount'] ?? '';
        $signature = $payload['signature_key'] ?? '';
        $serverKey = $this->getServerKey();

        if (empty($serverKey) || empty($orderId) || empty($signature)) {
            return [
                'valid' => false,
                'error' => 'Incomplete Midtrans callback signature payload.',
            ];
        }

        // Verify SHA-512 signature
        $computedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
        if (!hash_equals($computedSignature, $signature)) {
            return [
                'valid' => false,
                'error' => 'Invalid Midtrans signature key.',
            ];
        }

        // Extract invoice ID from order_id format (e.g. INV-123-1729000000)
        $invoiceId = null;
        if (preg_match('/^INV-(\d+)/', $orderId, $matches)) {
            $invoiceId = (int)$matches[1];
        }

        $transactionStatus = $payload['transaction_status'] ?? '';
        $fraudStatus = $payload['fraud_status'] ?? 'accept';

        $status = 'pending';
        if ($transactionStatus === 'capture') {
            $status = ($fraudStatus === 'challenge') ? 'pending' : 'paid';
        } elseif ($transactionStatus === 'settlement') {
            $status = 'paid';
        } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'], true)) {
            $status = 'failed';
        }

        return [
            'valid' => true,
            'invoice_id' => $invoiceId,
            'transaction_id' => $payload['transaction_id'] ?? $orderId,
            'amount' => (float)$grossAmount,
            'fee' => 0.0,
            'currency' => $payload['currency'] ?? 'IDR',
            'status' => $status,
            'error' => null,
        ];
    }
}
