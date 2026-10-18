<?php

namespace GuestInvoice\Lib\Gateway;

use WHMCS\Database\Capsule;

class WhmcsNativeAdapter extends AbstractGatewayAdapter
{
    public function __construct(string $gatewayName)
    {
        parent::__construct($gatewayName);
    }

    public function initiatePayment(array $invoice, string $returnUrl): array
    {
        $gateway = $this->gatewayName;

        // Check if gateway module file exists in WHMCS
        $gatewayFile = dirname(__DIR__, 4) . '/gateways/' . $gateway . '.php';
        if (file_exists($gatewayFile)) {
            require_once $gatewayFile;
        }

        $linkFunc = $gateway . '_link';
        if (function_exists($linkFunc)) {
            $client = $invoice['client'] ?? [];
            $nameParts = explode(' ', $client['fullname'] ?? 'Customer', 2);

            $params = array_merge($this->config, [
                'invoiceid' => $invoice['id'],
                'invoicenum' => $invoice['invoicenum'],
                'amount' => $invoice['balance'],
                'currency' => $invoice['currency']['code'] ?? 'USD',
                'description' => 'Invoice #' . $invoice['invoicenum'],
                'returnurl' => $returnUrl,
                'clientdetails' => [
                    'firstname' => $nameParts[0] ?? 'Customer',
                    'lastname' => $nameParts[1] ?? '',
                    'email' => $client['email'] ?? '',
                    'address1' => $client['address1'] ?? '',
                    'city' => $client['city'] ?? '',
                    'state' => $client['state'] ?? '',
                    'postcode' => $client['postcode'] ?? '',
                    'country' => $client['country'] ?? '',
                    'phonenumber' => $client['phonenumber'] ?? '',
                ],
                'systemurl' => \GuestInvoice\Lib\TokenService::getWhmcsSystemUrl(),
            ]);

            try {
                $output = call_user_func($linkFunc, $params);
                if (!empty($output)) {
                    return [
                        'success' => true,
                        'type' => 'html',
                        'html' => $output,
                        'redirect_url' => null,
                        'token' => null,
                        'instructions' => null,
                        'error' => null,
                    ];
                }
            } catch (\Throwable $e) {
                // Link call failed
            }
        }

        // Fallback: Provide safe direct redirect to WHMCS viewinvoice standard gateway trigger
        $systemUrl = \GuestInvoice\Lib\TokenService::getWhmcsSystemUrl();
        $invoiceUrl = rtrim($systemUrl, '/') . '/viewinvoice.php?id=' . $invoice['id'];

        return [
            'success' => true,
            'type' => 'redirect',
            'redirect_url' => $invoiceUrl,
            'html' => null,
            'token' => null,
            'instructions' => [
                'title' => 'Standard Gateway Checkout',
                'notes' => 'You are being transferred to the official payment processor page.',
            ],
            'error' => null,
        ];
    }

    public function verifyWebhook(array $headers, $payload): array
    {
        return [
            'valid' => false,
            'invoice_id' => null,
            'transaction_id' => null,
            'amount' => null,
            'fee' => null,
            'currency' => null,
            'status' => 'pending',
            'error' => 'Handled via native WHMCS gateway callback script.',
        ];
    }
}
