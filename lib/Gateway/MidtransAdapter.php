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

    private function getServerKey(int $invoiceId = 0): string
    {
        self::ensureGatewayFunctionsLoaded();

        $keys = [
            'server_key', 'serverKey', 'merchant_server_key', 'serverkey',
            'apiKey', 'api_key', 'apikey', 'veritrans_server_key',
            'snap_server_key', 'production_server_key', 'sandbox_server_key'
        ];

        // 1. Check loaded config
        foreach ($keys as $k) {
            if (!empty($this->config[$k])) {
                $val = trim($this->config[$k]);
                // If it starts with typical Midtrans prefixes, return directly
                if (strpos($val, 'SB-') === 0 || strpos($val, 'Mid-') === 0) {
                    return $val;
                }
            }
        }

        // 2. Try official getGatewayVariables with invoiceId if available
        if (function_exists('getGatewayVariables')) {
            foreach ([$this->gatewayName, 'veritrans', 'midtrans'] as $gw) {
                $vars = getGatewayVariables($gw, $invoiceId);
                if (is_array($vars)) {
                    foreach ($keys as $k) {
                        if (!empty($vars[$k])) {
                            return trim($vars[$k]);
                        }
                    }
                }
            }
        }

        // 3. Fallback to tblpaymentgateways with decrypt
        $altGateways = array_unique([$this->gatewayName, 'veritrans', 'midtrans']);
        try {
            $rows = Capsule::table('tblpaymentgateways')
                ->whereIn('gateway', $altGateways)
                ->get();
            foreach ($rows as $row) {
                if (in_array(strtolower($row->setting), array_map('strtolower', $keys), true) && !empty($row->value)) {
                    $val = trim($row->value);
                    if (function_exists('decrypt')) {
                        $dec = decrypt($val);
                        if (!empty($dec) && $dec !== false) {
                            return trim($dec);
                        }
                    }
                    return $val;
                }
            }
        } catch (\Throwable $e) {}

        return '';
    }

    public function initiatePayment(array $invoice, string $returnUrl): array
    {
        $invoiceId = (int)($invoice['id'] ?? 0);
        $this->loadConfig($invoiceId);

        // 1. Direct Midtrans Snap API call (Recommended for clean guest checkout)
        $serverKey = $this->getServerKey($invoiceId);
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

            // If Sandbox returned 401 with this server key, try testing Production URL if key looks like Production key (or vice-versa)
            if (isset($res['http_code']) && $res['http_code'] === 401) {
                $isSandboxKey = (strpos($serverKey, 'SB-') === 0);
                $altApiUrl = $isSandboxKey
                    ? 'https://app.sandbox.midtrans.com/snap/v1/transactions'
                    : 'https://app.midtrans.com/snap/v1/transactions';

                if ($altApiUrl !== $apiUrl) {
                    $altRes = $this->postJson($altApiUrl, $payload, ['Authorization: ' . $authHeader]);
                    if ($altRes['success'] && !empty($altRes['data']['redirect_url'])) {
                        return [
                            'success' => true,
                            'type' => 'redirect',
                            'redirect_url' => $altRes['data']['redirect_url'],
                            'token' => $altRes['data']['token'] ?? null,
                            'instructions' => null,
                            'html' => null,
                            'error' => null,
                        ];
                    }
                }
            }
        }

        // 2. Native gateway module fallback (veritrans.php / midtrans.php)
        $nativeAdapter = new WhmcsNativeAdapter($this->gatewayName);
        $nativeResult = $nativeAdapter->initiatePayment($invoice, $returnUrl);

        if ($nativeResult['success']) {
            return $nativeResult;
        }

        return [
            'success' => false,
            'error' => 'Koneksi ke Midtrans Snap gagal (' . ($res['error'] ?? ($nativeResult['error'] ?? 'HTTP 401 Unauthorized')) . '). Pastikan Server Key pada menu Payment Gateway WHMCS sudah sesuai.',
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
