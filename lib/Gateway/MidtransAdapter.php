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
        // 1. First, check if native gateway module (veritrans.php / midtrans.php) exists and produces a direct payment link
        $nativeAdapter = new WhmcsNativeAdapter($this->gatewayName);
        $nativeResult = $nativeAdapter->initiatePayment($invoice, $returnUrl);
        
        // If native module returns a valid redirect or HTML form, prioritize it
        if ($nativeResult['success'] && ($nativeResult['type'] === 'redirect' || !empty($nativeResult['html']))) {
            // If the redirect URL is not a local viewinvoice fallback (i.e. is actual gateway / payment URL), use it immediately!
            if (!empty($nativeResult['redirect_url']) && strpos($nativeResult['redirect_url'], 'viewinvoice.php') === false) {
                return $nativeResult;
            }
            if (!empty($nativeResult['html'])) {
                return $nativeResult;
            }
        }

        // 2. Direct Midtrans Snap API fallback
        $serverKey = $this->getServerKey();
        if (!empty($serverKey)) {
            $apiUrl = $this->isProduction()
                ? 'https://app.midtrans.com/snap/v1/transactions'
                : 'https://app.sandbox.midtrans.com/snap/v1/transactions';

            $orderId = 'INV-' . $invoice['id'] . '-' . time();
            $grossAmount = (int)round($invoice['balance']);

            $rawClient = $invoice['raw_client'] ?? [];
            $client = $invoice['client'] ?? [];
            $nameParts = explode(' ', $client['fullname'] ?? 'Customer', 2);
            $firstName = !empty($rawClient['firstname']) ? $rawClient['firstname'] : ($nameParts[0] ?? 'Customer');
            $lastName = !empty($rawClient['lastname']) ? $rawClient['lastname'] : ($nameParts[1] ?? '');
            $email = !empty($rawClient['email']) ? $rawClient['email'] : ($client['email'] ?? 'billing@domain.com');
            $phone = !empty($rawClient['phonenumber']) ? $rawClient['phonenumber'] : ($client['phonenumber'] ?? '');

            $payload = [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => $grossAmount,
                ],
                'customer_details' => [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => $phone,
                ],
                'callbacks' => [
                    'finish' => $returnUrl,
                ],
            ];

            $authHeader = 'Basic ' . base64_encode($serverKey . ':');
            $res = $this->postJson($apiUrl, $payload, ['Authorization: ' . $authHeader]);

            if ($res['success'] && !empty($res['data']['redirect_url'])) {
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
        }

        // 3. Fallback to native module result if API call failed or serverKey is invalid
        if ($nativeResult['success']) {
            return $nativeResult;
        }

        return [
            'success' => false,
            'error' => 'Koneksi ke Midtrans gagal (HTTP 401 - Kredensial Server Key salah atau environment Sandbox/Production tidak sesuai). Pastikan Server Key pada menu Payment Gateway WHMCS sudah benar.',
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
