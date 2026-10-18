<?php

namespace GuestInvoice\Lib\Gateway;

use WHMCS\Database\Capsule;

class TripayAdapter extends AbstractGatewayAdapter
{
    public function __construct(string $gatewayName = 'tripay')
    {
        parent::__construct($gatewayName);
    }

    private function isProduction(): bool
    {
        $mode = strtolower($this->config['environment'] ?? $this->config['mode'] ?? 'sandbox');
        return ($mode === 'live' || $mode === 'production');
    }

    private function getApiKey(): string
    {
        return trim($this->config['api_key'] ?? $this->config['apiKey'] ?? '');
    }

    private function getPrivateKey(): string
    {
        return trim($this->config['private_key'] ?? $this->config['privateKey'] ?? '');
    }

    private function getMerchantCode(): string
    {
        return trim($this->config['merchant_code'] ?? $this->config['merchantCode'] ?? '');
    }

    public function initiatePayment(array $invoice, string $returnUrl): array
    {
        $apiKey = $this->getApiKey();
        $privateKey = $this->getPrivateKey();
        $merchantCode = $this->getMerchantCode();

        if (empty($apiKey) || empty($privateKey) || empty($merchantCode)) {
            return [
                'success' => false,
                'error' => 'Tripay API credentials are not properly configured in Payment Gateways.',
            ];
        }

        $apiUrl = $this->isProduction()
            ? 'https://tripay.co.id/api/transaction/create'
            : 'https://tripay.co.id/api-sandbox/transaction/create';

        $merchantRef = 'INV-' . $invoice['id'] . '-' . time();
        $amount = (int)round($invoice['balance']);
        $client = $invoice['client'] ?? [];

        // Tripay signature: HMAC-SHA256(merchantCode + merchantRef + amount, privateKey)
        $signature = hash_hmac('sha256', $merchantCode . $merchantRef . $amount, $privateKey);

        $payload = [
            'method' => $this->config['payment_method'] ?? 'QRIS',
            'merchant_ref' => $merchantRef,
            'amount' => $amount,
            'customer_name' => $client['fullname'] ?? 'Customer',
            'customer_email' => $client['email'] ?? 'customer@domain.com',
            'customer_phone' => $client['phonenumber'] ?? '081234567890',
            'order_items' => [
                [
                    'sku' => 'INV-' . $invoice['invoicenum'],
                    'name' => 'Invoice #' . $invoice['invoicenum'],
                    'price' => $amount,
                    'quantity' => 1,
                ]
            ],
            'return_url' => $returnUrl,
            'signature' => $signature,
        ];

        $res = $this->postJson($apiUrl, $payload, ['Authorization: Bearer ' . $apiKey]);

        if (!$res['success'] || empty($res['data']['data']['checkout_url'])) {
            return [
                'success' => false,
                'error' => 'Tripay transaction creation failed: ' . ($res['data']['message'] ?? $res['error'] ?? 'Unknown error'),
            ];
        }

        $txData = $res['data']['data'];
        return [
            'success' => true,
            'type' => 'redirect',
            'redirect_url' => $txData['checkout_url'],
            'token' => $txData['reference'] ?? null,
            'instructions' => null,
            'html' => null,
            'error' => null,
        ];
    }

    public function verifyWebhook(array $headers, $payload): array
    {
        $rawBody = is_string($payload) ? $payload : json_encode($payload);
        $data = is_array($payload) ? $payload : json_decode($payload, true);

        $privateKey = $this->getPrivateKey();
        $incomingSig = $headers['x-callback-signature'] ?? $headers['X-CALLBACK-SIGNATURE'] ?? '';

        $calculatedSig = hash_hmac('sha256', $rawBody, $privateKey);
        if (!hash_equals($calculatedSig, $incomingSig)) {
            return [
                'valid' => false,
                'error' => 'Invalid Tripay callback signature.',
            ];
        }

        $merchantRef = $data['merchant_ref'] ?? '';
        $invoiceId = null;
        if (preg_match('/^INV-(\d+)/', $merchantRef, $matches)) {
            $invoiceId = (int)$matches[1];
        }

        $tripayStatus = strtoupper($data['status'] ?? '');
        $status = 'pending';
        if ($tripayStatus === 'PAID') {
            $status = 'paid';
        } elseif (in_array($tripayStatus, ['EXPIRED', 'FAILED'], true)) {
            $status = 'failed';
        }

        return [
            'valid' => true,
            'invoice_id' => $invoiceId,
            'transaction_id' => $data['reference'] ?? $merchantRef,
            'amount' => (float)($data['total_amount'] ?? 0),
            'fee' => (float)($data['total_fee'] ?? 0),
            'currency' => 'IDR',
            'status' => $status,
            'error' => null,
        ];
    }
}
