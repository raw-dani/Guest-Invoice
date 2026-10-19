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

        // Initialize WHMCS official gateway environment if function exists
        if (function_exists('loadGatewayFunctions')) {
            loadGatewayFunctions($gateway);
        }

        // Check if gateway module file exists in WHMCS
        $gatewayFile = dirname(__DIR__, 4) . '/gateways/' . $gateway . '.php';
        if (file_exists($gatewayFile)) {
            require_once $gatewayFile;
        }

        // Re-load variables using WHMCS official getGatewayVariables if available
        if (function_exists('getGatewayVariables')) {
            $vars = getGatewayVariables($gateway);
            if (is_array($vars) && !empty($vars)) {
                $this->config = array_merge($this->config, $vars);
            }
        }

        $linkFunc = $gateway . '_link';
        if (function_exists($linkFunc)) {
            $rawClient = $invoice['raw_client'] ?? [];
            $client = $invoice['client'] ?? [];
            $nameParts = explode(' ', $client['fullname'] ?? 'Customer', 2);
            $firstName = !empty($rawClient['firstname']) ? $rawClient['firstname'] : ($nameParts[0] ?? 'Customer');
            $lastName = !empty($rawClient['lastname']) ? $rawClient['lastname'] : ($nameParts[1] ?? '');
            $email = !empty($rawClient['email']) ? $rawClient['email'] : ($client['email'] ?? '');
            $phone = !empty($rawClient['phonenumber']) ? $rawClient['phonenumber'] : ($client['phonenumber'] ?? '');

            $params = array_merge($this->config, [
                'invoiceid' => $invoice['id'],
                'invoicenum' => $invoice['invoicenum'],
                'amount' => $invoice['balance'],
                'currency' => $invoice['currency']['code'] ?? 'USD',
                'description' => 'Invoice #' . $invoice['invoicenum'],
                'returnurl' => $returnUrl,
                'systemurl' => \GuestInvoice\Lib\TokenService::getWhmcsSystemUrl(),
                'userid' => $invoice['userid'] ?? ($rawClient['id'] ?? null),
                'firstname' => $firstName,
                'lastname' => $lastName,
                'email' => $email,
                'address1' => $rawClient['address1'] ?? ($client['address1'] ?? ''),
                'address2' => $rawClient['address2'] ?? ($client['address2'] ?? ''),
                'city' => $rawClient['city'] ?? ($client['city'] ?? ''),
                'state' => $rawClient['state'] ?? ($client['state'] ?? ''),
                'postcode' => $rawClient['postcode'] ?? ($client['postcode'] ?? ''),
                'country' => $rawClient['country'] ?? ($client['country'] ?? ''),
                'phonenumber' => $phone,
                'clientdetails' => [
                    'id' => $invoice['userid'] ?? ($rawClient['id'] ?? null),
                    'firstname' => $firstName,
                    'lastname' => $lastName,
                    'email' => $email,
                    'address1' => $rawClient['address1'] ?? ($client['address1'] ?? ''),
                    'address2' => $rawClient['address2'] ?? ($client['address2'] ?? ''),
                    'city' => $rawClient['city'] ?? ($client['city'] ?? ''),
                    'state' => $rawClient['state'] ?? ($client['state'] ?? ''),
                    'postcode' => $rawClient['postcode'] ?? ($client['postcode'] ?? ''),
                    'country' => $rawClient['country'] ?? ($client['country'] ?? ''),
                    'phonenumber' => $phone,
                ],
            ]);

            try {
                $output = call_user_func($linkFunc, $params);
                if (!empty($output)) {
                    // 1. If output contains an action URL or window.location to external payment portal, extract and redirect directly
                    if (preg_match('/(?:action=["\'](https?:\/\/[^"\']+)["\'])|(?:(?:location\.href|window\.location)\s*=\s*["\'](https?:\/\/[^"\']+)["\'])/i', $output, $m)) {
                        $targetUrl = !empty($m[1]) ? $m[1] : (!empty($m[2]) ? $m[2] : '');
                        if (!empty($targetUrl) && strpos($targetUrl, 'viewinvoice.php') === false) {
                            // If it's a GET link / direct redirect, redirect directly
                            if (stripos($output, 'method="post"') === false) {
                                return [
                                    'success' => true,
                                    'type' => 'redirect',
                                    'redirect_url' => html_entity_decode($targetUrl),
                                    'html' => null,
                                    'token' => null,
                                    'instructions' => null,
                                    'error' => null,
                                ];
                            }
                        }
                    }

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
