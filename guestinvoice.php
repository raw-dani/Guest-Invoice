<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Module Autoloader for GuestInvoice namespace
spl_autoload_register(function ($class) {
    $prefix = 'GuestInvoice\\';
    $baseDir = __DIR__ . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);

    // Try standard casing
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require_once $file;
        return;
    }

    // Try lowercase directory segments (lib, controllers, migrations)
    $parts = explode('\\', $relativeClass);
    if (count($parts) > 1) {
        $className = array_pop($parts);
        $dirs = array_map('strtolower', $parts);
        $fileLower = $baseDir . implode('/', $dirs) . '/' . $className . '.php';
        if (file_exists($fileLower)) {
            require_once $fileLower;
            return;
        }
    }
});

use GuestInvoice\Migrations\MigrationManager;
use GuestInvoice\Controllers\AdminController;
use GuestInvoice\Controllers\GuestInvoiceController;
use GuestInvoice\Controllers\GuestPaymentController;
use GuestInvoice\Controllers\PaymentResultController;

/**
 * Addon Module Configuration
 */
function guestinvoice_config(): array
{
    return [
        'name' => 'Guest Invoice & Payment',
        'description' => 'Secure public invoice viewing and online payment portal without login using cryptographic tokens.',
        'version' => '1.0.0',
        'author' => 'Antigravity / WHMCS Engineering',
        'language' => 'english',
        'fields' => [
            'default_expiry_days' => [
                'FriendlyName' => 'Default Expiry (Days)',
                'Type' => 'dropdown',
                'Options' => '7,14,30,60,90',
                'Default' => '30',
                'Description' => 'Default token validity lifetime upon generation.',
            ],
            'allow_pdf_download' => [
                'FriendlyName' => 'Allow PDF Download',
                'Type' => 'yesno',
                'Default' => '',
                'Description' => 'Enable guests to download official PDF invoices.',
            ],
            'auto_generate_on_invoice_created' => [
                'FriendlyName' => 'Auto Generate Link',
                'Type' => 'yesno',
                'Default' => 'yes',
                'Description' => 'Automatically create a guest access token when an invoice is created.',
            ],
            'auto_email_on_invoice_created' => [
                'FriendlyName' => 'Auto Email Link',
                'Type' => 'yesno',
                'Default' => '',
                'Description' => 'Automatically send email containing guest link upon invoice creation.',
            ],
            'enable_guest_payment' => [
                'FriendlyName' => 'Enable Guest Payment',
                'Type' => 'yesno',
                'Default' => 'yes',
                'Description' => 'Allow customers to proceed with checkout directly from the guest invoice view.',
            ],
            'rate_limit_per_minute' => [
                'FriendlyName' => 'Rate Limit Attempts / Min',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '15',
                'Description' => 'Maximum token lookup attempts per IP per minute before temporary lockout.',
            ],
            'custom_branding_title' => [
                'FriendlyName' => 'Portal Branding Title',
                'Type' => 'text',
                'Size' => '40',
                'Default' => 'Secure Guest Invoice & Payment Portal',
                'Description' => 'Header branding title displayed on public guest invoice pages.',
            ],
        ],
    ];
}

/**
 * Addon Module Activation: run database migrations and seed default configuration.
 */
function guestinvoice_activate(): array
{
    $result = MigrationManager::run();
    if ($result['success']) {
        return [
            'status' => 'success',
            'description' => 'Guest Invoice & Payment module activated successfully. Tables created and verified.',
        ];
    }

    return [
        'status' => 'error',
        'description' => 'Activation failed: ' . $result['message'],
    ];
}

/**
 * Addon Module Deactivation: preserve data tables and settings.
 */
function guestinvoice_deactivate(): array
{
    return [
        'status' => 'success',
        'description' => 'Guest Invoice & Payment module deactivated. All invoice tokens and audit logs were preserved.',
    ];
}

/**
 * Addon Module Upgrade Handler
 */
function guestinvoice_upgrade($vars): void
{
    MigrationManager::run();
}

/**
 * Admin Area Module Page Dispatcher
 */
function guestinvoice_output($vars)
{
    echo AdminController::handle($vars);
}

/**
 * Client Area Public Route Dispatcher
 * URL: index.php?m=guestinvoice&token=...
 */
function guestinvoice_clientarea($vars): array
{
    $action = $_GET['action'] ?? 'view';

    switch ($action) {
        case 'pay':
            return GuestPaymentController::handle($vars);

        case 'result':
            return PaymentResultController::handle($vars);

        case 'download':
        case 'view':
        default:
            return GuestInvoiceController::handle($vars);
    }
}
