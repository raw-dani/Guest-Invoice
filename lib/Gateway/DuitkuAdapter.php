<?php

namespace GuestInvoice\Lib\Gateway;

use WHMCS\Database\Capsule;

class DuitkuAdapter extends AbstractGatewayAdapter
{
    public function __construct(string $gatewayName = 'duitku')
    {
        parent::__construct($gatewayName);
    }

    private function getMerchantCode(): string
    {
        return trim($this->config['merchant_code'] ?? $this->config['merchantCode'] ?? $this->config['merchant_id'] ?? '');
    }

    private function getApiKey(): string
    {
        return trim($this->config['api_key'] ?? $this->config['apiKey'] ?? $this->config['merchant_key'] ?? '');
    }

    private function isProduction(): bool
    {
        $mode = strtolower($this->config['environment'] ?? $this->config['mode'] ?? 'sandbox');
        return ($mode === 'live' || $mode === 'production' || $mode === 'on' || $mode === '1');
    }

    public function initiatePayment(array $invoice, string $returnUrl): array
    {
        $merchantCode = $this->getMerchantCode();
        $apiKey = $this->getApiKey();

        if (empty($merchantCode) || empty($apiKey)) {
            return [
                'success' => false,
                'error' => 'Duitku Merchant Code or API Key is not configured in Payment Gateways.',
            ];
        }

        $apiUrl = $this->isProduction()
            ? 'https://passport.duitku.com/webapi/api/merchant/v2/inquiry'
            : 'https://sandbox.duitku.com/webapi/api/merchant/v2/inquiry';

        $merchantOrderId = 'INV-' . $invoice['id'] . '-' . time();
        $paymentAmount = (int)round($invoice['balance']);
        $client = $invoice['client'] ?? [];

        // Duitku signature formula: MD5(merchantCode + merchantOrderId + paymentAmount + apiKey)
        $signature = md5($merchantCode . $merchantOrderId . $paymentAmount . $apiKey);

        $payload = [
            'paymentAmount' => $paymentAmount,
            'merchantOrderId' => $merchantOrderId,
            'productDetails' => 'Payment for Invoice #' . $invoice['invoicenum'],
            'additionalParam' => '',
            'merchantUserInfo' => $client['fullname'] ?? 'Customer',
            'customerVaName' => $client['fullname'] ?? 'Customer',
            'email' => $client['email'] ?? 'billing@domain.com',
            'phoneNumber' => $client['phonenumber'] ?? '081234567890',
            'itemDetails' => [
                [
                    'name' => 'Invoice #' . $invoice['invoicenum'],
                    'price' => $paymentAmount,
                    'quantity' => 1,
                ]
            ],
            'callbackUrl' => $returnUrl . '&gateway=duitku&type=callback',
            'returnUrl' => $returnUrl,
            'signature' => $signature,
            'expiryPeriod' => 1440, // 24 hours
        ];

        $res = $this->postJson($apiUrl, $payload);

        if (!$res['success'] || empty($res['data']['paymentUrl'])) {
            $msg = $res['data']['statusMessage'] ?? $res['error'] ?? 'Unknown Duitku error';
            return [
                'success' => false,
                'error' => 'Duitku transaction creation failed: ' . $msg,
            ];
        }

        return [
            'success' => true,
            'type' => 'redirect',
            'redirect_url' => $res['data']['paymentUrl'],
            'token' => $res['data']['reference'] ?? null,
            'instructions' => null,
            'html' => null,
            'error' => null,
        ];
    }

    public function verifyWebhook(array $headers, $payload): array
    {
        // Handle both JSON payload or form-urlencoded POST data
        if (is_string($payload)) {
            $json = json_decode($payload, true);
            $data = is_array($json) ? $json : [];
        } else {
            $data = is_array($payload) ? $payload : [];
        }

        // Support $_POST fallback if payload array was empty
        if (empty($data) && !empty($_POST)) {
            $data = $_POST;
        }

        $merchantCode = $data['merchantCode'] ?? $this->getMerchantCode();
        $amount = (string)($data['amount'] ?? '');
        $merchantOrderId = $data['merchantOrderId'] ?? '';
        $incomingSignature = $data['signature'] ?? '';
        $resultCode = $data['resultCode'] ?? '';
        $apiKey = $this->getApiKey();

        if (empty($merchantCode) || empty($apiKey) || empty($merchantOrderId) || empty($incomingSignature)) {
            return [
                'valid' => false,
                'error' => 'Missing required Duitku callback parameters.',
            ];
        }

        // Duitku callback signature formula: MD5(merchantCode + amount + merchantOrderId + apiKey)
        $calculatedSignature = md5($merchantCode . $amount . $merchantOrderId . $apiKey);

        if (!hash_equals(strtolower($calculatedSignature), strtolower($incomingSignature))) {
            return [
                'valid' => false,
                'error' => 'Invalid Duitku callback signature.',
            ];
        }

        // Extract invoice ID from merchantOrderId (e.g. INV-101-1729000000)
        $invoiceId = null;
        if (preg_match('/^INV-(\d+)/', $merchantOrderId, $matches)) {
            $invoiceId = (int)$matches[1];
        }

        // ResultCode '00' indicates successful payment
        $status = ($resultCode === '00') ? 'paid' : 'failed';

        return [
            'valid' => true,
            'invoice_id' => $invoiceId,
            'transaction_id' => $data['reference'] ?? $merchantOrderId,
            'amount' => (float)$amount,
            'fee' => 0.0,
            'currency' => 'IDR',
            'status' => $status,
            'error' => null,
        ];
    }
}
