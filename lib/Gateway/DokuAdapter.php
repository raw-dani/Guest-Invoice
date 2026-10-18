<?php

namespace GuestInvoice\Lib\Gateway;

use WHMCS\Database\Capsule;

class DokuAdapter extends AbstractGatewayAdapter
{
    public function __construct(string $gatewayName = 'doku')
    {
        parent::__construct($gatewayName);
    }

    private function getClientId(): string
    {
        return trim($this->config['client_id'] ?? $this->config['clientId'] ?? '');
    }

    private function getSecretKey(): string
    {
        return trim($this->config['secret_key'] ?? $this->config['shared_key'] ?? '');
    }

    private function isProduction(): bool
    {
        $env = strtolower($this->config['environment'] ?? 'sandbox');
        return ($env === 'live' || $env === 'production');
    }

    public function initiatePayment(array $invoice, string $returnUrl): array
    {
        $clientId = $this->getClientId();
        $secretKey = $this->getSecretKey();

        if (empty($clientId) || empty($secretKey)) {
            return [
                'success' => false,
                'error' => 'DOKU Client ID or Secret Key is not configured in Payment Gateways.',
            ];
        }

        $apiUrl = $this->isProduction()
            ? 'https://api.doku.com/checkout/v1/payment'
            : 'https://api-sandbox.doku.com/checkout/v1/payment';

        $invoiceNumber = 'INV-' . $invoice['id'] . '-' . time();
        $amount = (int)round($invoice['balance']);
        $client = $invoice['client'] ?? [];

        $payload = [
            'order' => [
                'invoice_number' => $invoiceNumber,
                'amount' => $amount,
                'callback_url' => $returnUrl,
            ],
            'payment' => [
                'payment_due_date' => 60, // minutes
            ],
            'customer' => [
                'name' => $client['fullname'] ?? 'Customer',
                'email' => $client['email'] ?? 'customer@domain.com',
                'phone' => $client['phonenumber'] ?? '',
            ],
        ];

        $requestId = bin2hex(random_bytes(16));
        $requestTimestamp = gmdate("Y-m-d\TH:i:s\Z");
        $requestTarget = '/checkout/v1/payment';
        $digest = base64_encode(hash('sha256', json_encode($payload), true));

        $signatureComponent = "Client-Id:" . $clientId . "\n"
            . "Request-Id:" . $requestId . "\n"
            . "Request-Timestamp:" . $requestTimestamp . "\n"
            . "Request-Target:" . $requestTarget . "\n"
            . "Digest:" . $digest;

        $signature = "HMACSHA256=" . base64_encode(hash_hmac('sha256', $signatureComponent, $secretKey, true));

        $headers = [
            'Client-Id: ' . $clientId,
            'Request-Id: ' . $requestId,
            'Request-Timestamp: ' . $requestTimestamp,
            'Signature: ' . $signature,
        ];

        $res = $this->postJson($apiUrl, $payload, $headers);

        if (!$res['success'] || empty($res['data']['response']['payment']['url'])) {
            return [
                'success' => false,
                'error' => 'DOKU Checkout initialization error: ' . ($res['data']['error']['message'] ?? $res['error'] ?? 'Unknown error'),
            ];
        }

        return [
            'success' => true,
            'type' => 'redirect',
            'redirect_url' => $res['data']['response']['payment']['url'],
            'token' => $res['data']['response']['payment']['token_id'] ?? null,
            'instructions' => null,
            'html' => null,
            'error' => null,
        ];
    }

    public function verifyWebhook(array $headers, $payload): array
    {
        $data = is_array($payload) ? $payload : json_decode($payload, true);
        $order = $data['order'] ?? [];
        $invoiceNumber = $order['invoice_number'] ?? '';

        $invoiceId = null;
        if (preg_match('/^INV-(\d+)/', $invoiceNumber, $matches)) {
            $invoiceId = (int)$matches[1];
        }

        $txStatus = strtoupper($data['transaction']['status'] ?? '');
        $status = ($txStatus === 'SUCCESS') ? 'paid' : 'pending';

        return [
            'valid' => true,
            'invoice_id' => $invoiceId,
            'transaction_id' => $data['transaction']['original_request_id'] ?? $invoiceNumber,
            'amount' => (float)($order['amount'] ?? 0),
            'fee' => 0.0,
            'currency' => 'IDR',
            'status' => $status,
            'error' => null,
        ];
    }
}
