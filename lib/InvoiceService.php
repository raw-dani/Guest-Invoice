<?php

namespace GuestInvoice\Lib;

use WHMCS\Database\Capsule;

class InvoiceService
{
    /**
     * Retrieve complete and sanitized invoice data for display.
     *
     * @param int $invoiceId
     * @return array|null
     */
    public static function getInvoiceData(int $invoiceId): ?array
    {
        try {
            $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
            if (!$invoice) {
                return null;
            }

            // Retrieve client profile
            $client = Capsule::table('tblclients')->where('id', $invoice->userid)->first();
            if (!$client) {
                return null;
            }

            // Retrieve currency details
            $currency = Capsule::table('tblcurrencies')->where('id', $client->currency)->first();
            if (!$currency) {
                $currency = Capsule::table('tblcurrencies')->where('default', 1)->first();
            }

            $prefix = $currency->prefix ?? '';
            $suffix = $currency->suffix ?? '';
            $currencyCode = $currency->code ?? 'USD';

            // Retrieve invoice line items
            $items = Capsule::table('tblinvoiceitems')
                ->where('invoiceid', $invoiceId)
                ->orderBy('id', 'asc')
                ->get();

            $lineItems = [];
            foreach ($items as $item) {
                $lineItems[] = [
                    'id' => $item->id,
                    'type' => $item->type,
                    'relid' => $item->relid,
                    'description' => $item->description,
                    'amount' => (float)$item->amount,
                    'amount_formatted' => $prefix . number_format((float)$item->amount, 2) . $suffix,
                    'taxed' => (bool)$item->taxed,
                ];
            }

            // Retrieve transaction records (payments credited)
            $transactions = Capsule::table('tblaccounts')
                ->where('invoiceid', $invoiceId)
                ->orderBy('date', 'asc')
                ->get();

            $payments = [];
            $totalPaid = 0.0;
            foreach ($transactions as $tx) {
                $amtIn = (float)$tx->amountin;
                $amtOut = (float)$tx->amountout;
                $netPaid = $amtIn - $amtOut;
                $totalPaid += $netPaid;

                $payments[] = [
                    'id' => $tx->id,
                    'date' => $tx->date,
                    'gateway' => $tx->gateway,
                    'transid' => $tx->transid,
                    'amount' => $netPaid,
                    'amount_formatted' => $prefix . number_format($netPaid, 2) . $suffix,
                ];
            }

            $total = (float)$invoice->total;
            $subtotal = (float)$invoice->subtotal;
            $tax = (float)$invoice->tax;
            $tax2 = (float)$invoice->tax2;
            $credit = (float)$invoice->credit;
            $balanceDue = max(0.0, round($total - $totalPaid, 2));

            // Format client details (privacy-safe, no internal notes or sensitive data)
            $clientDetails = [
                'fullname' => trim($client->firstname . ' ' . $client->lastname),
                'companyname' => $client->companyname ?: '',
                'email' => self::maskEmail($client->email),
                'address1' => $client->address1 ?: '',
                'address2' => $client->address2 ?: '',
                'city' => $client->city ?: '',
                'state' => $client->state ?: '',
                'postcode' => $client->postcode ?: '',
                'country' => $client->country ?: '',
                'phonenumber' => !empty($client->phonenumber) ? self::maskPhoneNumber($client->phonenumber) : '',
            ];

            // Company billing info and logo from WHMCS configuration
            $companyName = self::getWhmcsConfig('CompanyName', 'Billing Department');
            $payToText = self::getWhmcsConfig('InvoicePayTo', '');
            $logoUrl = self::getWhmcsConfig('LogoURL', '');

            return [
                'id' => (int)$invoice->id,
                'invoicenum' => !empty($invoice->invoicenum) ? $invoice->invoicenum : (string)$invoice->id,
                'date' => $invoice->date,
                'duedate' => $invoice->duedate,
                'datepaid' => $invoice->datepaid,
                'subtotal' => $subtotal,
                'subtotal_formatted' => $prefix . number_format($subtotal, 2) . $suffix,
                'credit' => $credit,
                'credit_formatted' => $prefix . number_format($credit, 2) . $suffix,
                'tax' => $tax,
                'tax_formatted' => $prefix . number_format($tax, 2) . $suffix,
                'tax2' => $tax2,
                'tax2_formatted' => $prefix . number_format($tax2, 2) . $suffix,
                'total' => $total,
                'total_formatted' => $prefix . number_format($total, 2) . $suffix,
                'balance' => $balanceDue,
                'balance_formatted' => $prefix . number_format($balanceDue, 2) . $suffix,
                'status' => ucfirst(strtolower($invoice->status)),
                'paymentmethod' => $invoice->paymentmethod,
                'currency' => [
                    'code' => $currencyCode,
                    'prefix' => $prefix,
                    'suffix' => $suffix,
                ],
                'client' => $clientDetails,
                'raw_client' => [
                    'firstname' => $client->firstname ?: 'Customer',
                    'lastname' => $client->lastname ?: '',
                    'email' => $client->email ?: '',
                    'phonenumber' => $client->phonenumber ?: '',
                    'address1' => $client->address1 ?: '',
                    'address2' => $client->address2 ?: '',
                    'city' => $client->city ?: '',
                    'state' => $client->state ?: '',
                    'postcode' => $client->postcode ?: '',
                    'country' => $client->country ?: '',
                ],
                'company' => [
                    'name' => $companyName,
                    'payto' => $payToText,
                    'logo' => $logoUrl,
                ],
                'items' => $lineItems,
                'transactions' => $payments,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Retrieve active payment gateways available for guest payments.
     *
     * @param int $invoiceId
     * @return array List of gateway descriptors
     */
    public static function getAvailableGateways(int $invoiceId): array
    {
        try {
            $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
            $defaultGateway = $invoice ? $invoice->paymentmethod : '';

            $rows = Capsule::table('tblpaymentgateways')
                ->where('setting', 'visible')
                ->where('value', 'on')
                ->get();

            $gateways = [];
            foreach ($rows as $row) {
                $gwName = $row->gateway;
                $displayNameRow = Capsule::table('tblpaymentgateways')
                    ->where('gateway', $gwName)
                    ->where('setting', 'name')
                    ->first();

                $displayName = $displayNameRow ? $displayNameRow->value : ucfirst($gwName);

                $gateways[] = [
                    'module' => $gwName,
                    'name' => $displayName,
                    'is_default' => ($gwName === $defaultGateway),
                ];
            }

            return $gateways;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Mask email address for customer privacy on public screens.
     */
    public static function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }

        $name = $parts[0];
        $domain = $parts[1];

        $len = strlen($name);
        if ($len <= 2) {
            $maskedName = substr($name, 0, 1) . '*';
        } else {
            $maskedName = substr($name, 0, 2) . str_repeat('*', max(1, $len - 3)) . substr($name, -1);
        }

        return $maskedName . '@' . $domain;
    }

    /**
     * Mask phone number for customer privacy.
     */
    public static function maskPhoneNumber(string $phone): string
    {
        $clean = preg_replace('/[^\d+]/', '', $phone);
        $len = strlen($clean);
        if ($len <= 4) {
            return $clean;
        }
        return substr($clean, 0, 3) . str_repeat('*', $len - 6) . substr($clean, -3);
    }

    /**
     * Retrieve configuration value from tblconfiguration.
     */
    public static function getWhmcsConfig(string $setting, string $default = ''): string
    {
        try {
            $row = Capsule::table('tblconfiguration')->where('setting', $setting)->first();
            return ($row && !empty($row->value)) ? $row->value : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
