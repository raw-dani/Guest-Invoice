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
        $keys = [
            'server_key', 'serverKey', 'merchant_server_key', 'serverkey',
            'apiKey', 'api_key', 'apikey', 'veritrans_server_key',
            'snap_server_key', 'production_server_key', 'sandbox_server_key'
        ];

        foreach ($keys as $k) {
            if (!empty($this->config[$k])) {
                return trim($this->config[$k]);
            }
        }

        // Also check if stored under alternative gateway table names (e.g. midtrans vs veritrans)
        $altGateway = ($this->gatewayName === 'veritrans') ? 'midtrans' : 'veritrans';
        try {
            $rows = Capsule::table('tblpaymentgateways')
                ->where('gateway', $altGateway)
                ->get();
            foreach ($rows as $row) {
                if (in_array(strtolower($row->setting), array_map('strtolower', $keys), true) && !empty($row->value)) {
                    return trim($row->value);
                }
            }
        } catch (\Throwable $e) {}

        return '';
    }

    public function initiatePayment(array $invoice, string $returnUrl): array
    {
        $serverKey = $this->getServerKey();

        // If direct Midtrans API Server Key is missing, try official WHMCS native module link
        if (empty($serverKey)) {
            $nativeAdapter = new WhmcsNativeAdapter($this->gatewayName);
            $nativeResult = $nativeAdapter->initiatePayment($invoice, $returnUrl);
            if ($nativeResult['success']) {
                // If native module returns HTML/button/form or redirect, return that directly
                return $nativeResult;
            }

            return [
                'success' => false,
                'error' => 'Konfigurasi Server Key ' . ucfirst($this->gatewayName) . ' tidak ditemukan di WHMCS Payment Gateway settings.',
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
