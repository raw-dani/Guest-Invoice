<?php

namespace GuestInvoice\Lib\Gateway;

use WHMCS\Database\Capsule;

class BankTransferAdapter extends AbstractGatewayAdapter
{
    public function __construct(string $gatewayName = 'banktransfer')
    {
        parent::__construct($gatewayName);
    }

    public function initiatePayment(array $invoice, string $returnUrl): array
    {
        $instructions = $this->config['instructions'] ?? 'Silakan lakukan transfer ke rekening bank kami sesuai dengan jumlah total tagihan.';
        $bankName = $this->config['bankname'] ?? 'BCA / Mandiri / BNI';
        $accountNum = $this->config['accountnumber'] ?? 'Lihat petunjuk';
        $accountName = $this->config['accountname'] ?? 'Billing Department';

        return [
            'success' => true,
            'type' => 'instructions',
            'redirect_url' => null,
            'html' => null,
            'token' => null,
            'instructions' => [
                'title' => 'Instruksi Transfer Bank Manual',
                'bank_name' => $bankName,
                'account_number' => $accountNum,
                'account_name' => $accountName,
                'reference_code' => 'INV-' . $invoice['invoicenum'],
                'amount_to_pay' => $invoice['balance_formatted'],
                'description' => $instructions,
                'notes' => 'Harap sertakan Nomor Tagihan (#INV-' . $invoice['invoicenum'] . ') pada berita transfer untuk mempercepat verifikasi otomatis/manual.',
            ],
            'error' => null,
        ];
    }

    public function verifyWebhook(array $headers, $payload): array
    {
        // Bank transfer is manual, no automated webhook
        return [
            'valid' => false,
            'invoice_id' => null,
            'transaction_id' => null,
            'amount' => null,
            'fee' => null,
            'currency' => null,
            'status' => 'pending',
            'error' => 'Manual bank transfer does not process automated webhooks.',
        ];
    }
}
