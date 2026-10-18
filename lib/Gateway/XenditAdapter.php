<?php

namespace GuestInvoice\Lib\Gateway;

use WHMCS\Database\Capsule;

class XenditAdapter extends AbstractGatewayAdapter
{
    public function __construct(string $gatewayName = 'xendit')
    {
        parent::__construct($gatewayName);
    }

    private function getSecretKey(): string
    {
        return trim($this->config['secret_api_key'] ?? $this->config['secret_key'] ?? $this->config['apiKey'] ?? '');
    }

    private function getCallbackToken(): string
    {
        return trim($this->config['callback_token'] ?? $this->config['webhook_token'] ?? '');
    }

    public function initiatePayment(array $invoice, string $returnUrl): array
    {
        $secretKey = $this->getSecretKey();
        if (empty($secretKey)) {
            return [
                'success' => false,
                'error' => 'Xendit Secret API Key is not configured in WHMCS Payment Gateway settings.',
            ];
        }

        $apiUrl = 'https://api.xendit.co/v2/invoices';
        $externalId = 'INV-' . $invoice['id'] . '-' . time();
        $amount = (float)$invoice['balance'];
        $client = $invoice['client'] ?? [];

        $payload = [
            'external_id' => $externalId,
            'amount' => $amount,
            'payer_email' => $client['email'] ?? 'customer@domain.com',
            'description' => 'Payment for Invoice #' . $invoice['invoicenum'],
            'success_redirect_url' => $returnUrl,
            'failure_redirect_url' => $returnUrl,
            'currency' => $invoice['currency']['code'] ?? 'IDR',
        ];

        $authHeader = 'Basic ' . base64_encode($secretKey . ':');
        $res = $this->postJson($apiUrl, $payload, ['Authorization: ' . $authHeader]);

        if (!$res['success'] || empty($res['data']['invoice_url'])) {
            return [
                'success' => false,
                'error' => 'Xendit Invoice creation failed: ' . ($res['error'] ?? 'Unknown gateway error'),
            ];
        }

        return [
            'success' => true,
            'type' => 'redirect',
            'redirect_url' => $res['data']['invoice_url'],
            'token' => $res['data']['id'] ?? null,
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

        $configuredToken = $this->getCallbackToken();
        if (!empty($configuredToken)) {
            $incomingToken = $headers['x-callback-token'] ?? $headers['X-CALLBACK-TOKEN'] ?? '';
            if (!hash_equals($configuredToken, $incomingToken)) {
                return [
                    'valid' => false,
                    'error' => 'Invalid Xendit callback verification token.',
                ];
            }
        }

        $externalId = $payload['external_id'] ?? '';
        $invoiceId = null;
        if (preg_match('/^INV-(\d+)/', $externalId, $matches)) {
            $invoiceId = (int)$matches[1];
        }

        $xenditStatus = strtoupper($payload['status'] ?? '');
        $status = 'pending';
        if ($xenditStatus === 'PAID' || $xenditStatus === 'SETTLED') {
            $status = 'paid';
        } elseif ($xenditStatus === 'EXPIRED') {
            $status = 'expired';
        }

        return [
            'valid' => true,
            'invoice_id' => $invoiceId,
            'transaction_id' => $payload['id'] ?? $payload['payment_id'] ?? $externalId,
            'amount' => (float)($payload['paid_amount'] ?? $payload['amount'] ?? 0),
            'fee' => 0.0,
            'currency' => $payload['currency'] ?? 'IDR',
            'status' => $status,
            'error' => null,
        ];
    }
}
