<?php

namespace GuestInvoice\Lib\Gateway;

use WHMCS\Database\Capsule;
use GuestInvoice\Lib\AuditLogger;

abstract class AbstractGatewayAdapter implements GatewayInterface
{
    protected string $gatewayName;
    protected array $config = [];

    public function __construct(string $gatewayName)
    {
        $this->gatewayName = $gatewayName;
        $this->loadConfig();
    }

    public function getIdentifier(): string
    {
        return $this->gatewayName;
    }

    public function getName(): string
    {
        return $this->config['name'] ?? ucfirst($this->gatewayName);
    }

    public function isGuestSupported(): bool
    {
        return true;
    }

    /**
     * Ensure WHMCS official gateway functions are loaded.
     */
    protected static function ensureGatewayFunctionsLoaded(): void
    {
        if (!function_exists('getGatewayVariables') || !function_exists('loadGatewayFunctions')) {
            $possiblePaths = [
                defined('ROOTDIR') ? ROOTDIR . '/includes/gatewayfunctions.php' : '',
                dirname(__DIR__, 3) . '/includes/gatewayfunctions.php',
                dirname(__DIR__, 4) . '/includes/gatewayfunctions.php',
            ];
            foreach ($possiblePaths as $path) {
                if (!empty($path) && file_exists($path)) {
                    require_once $path;
                    break;
                }
            }
        }
    }

    /**
     * Load gateway configuration from WHMCS tblpaymentgateways.
     */
    protected function loadConfig(int $invoiceId = 0): void
    {
        try {
            self::ensureGatewayFunctionsLoaded();

            // WHMCS official helper function decrypts and loads gateway settings and module metadata
            if (function_exists('getGatewayVariables')) {
                $vars = getGatewayVariables($this->gatewayName, $invoiceId);
                if (is_array($vars) && !empty($vars)) {
                    $this->config = $vars;
                    return;
                }
            }

            $rows = Capsule::table('tblpaymentgateways')
                ->where('gateway', $this->gatewayName)
                ->get();

            foreach ($rows as $row) {
                $val = $row->value;
                if (function_exists('decrypt')) {
                    $dec = decrypt($val);
                    if ($dec !== false && $dec !== '') {
                        $val = $dec;
                    }
                }
                $this->config[$row->setting] = $val;
            }
        } catch (\Throwable $e) {
            $this->config = [];
        }
    }

    /**
     * Helper to make HTTP POST requests with JSON payload.
     */
    protected function postJson(string $url, array $payload, array $headers = []): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([
            'Content-Type: application/json',
            'Accept: application/json',
        ], $headers));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [
                'success' => false,
                'http_code' => $httpCode,
                'error' => 'cURL Error: ' . $error,
                'data' => null,
            ];
        }

        $decoded = json_decode($response, true);
        return [
            'success' => ($httpCode >= 200 && $httpCode < 300),
            'http_code' => $httpCode,
            'data' => $decoded,
            'raw' => $response,
            'error' => ($httpCode >= 400) ? ($decoded['message'] ?? 'HTTP ' . $httpCode) : null,
        ];
    }
}
