<?php

namespace GuestInvoice\Lib\Gateway;

interface GatewayInterface
{
    /**
     * Unique module identifier matching WHMCS gateway name.
     */
    public function getIdentifier(): string;

    /**
     * Human readable display name.
     */
    public function getName(): string;

    /**
     * Check if this gateway supports unauthenticated guest checkout flow.
     */
    public function isGuestSupported(): bool;

    /**
     * Initiate payment transaction for guest checkout.
     *
     * @param array $invoice Complete invoice data from InvoiceService
     * @param string $returnUrl URL to return the guest after payment
     * @return array [
     *     'success' => bool,
     *     'type' => 'redirect'|'html'|'instructions'|'snap_token',
     *     'redirect_url' => string|null,
     *     'html' => string|null,
     *     'token' => string|null,
     *     'instructions' => array|null,
     *     'error' => string|null,
     * ]
     */
    public function initiatePayment(array $invoice, string $returnUrl): array;

    /**
     * Verify incoming webhook/callback payload.
     *
     * @param array $headers
     * @param array|string $payload
     * @return array [
     *     'valid' => bool,
     *     'invoice_id' => int|null,
     *     'transaction_id' => string|null,
     *     'amount' => float|null,
     *     'fee' => float|null,
     *     'currency' => string|null,
     *     'status' => 'paid'|'pending'|'failed'|'expired',
     *     'error' => string|null,
     * ]
     */
    public function verifyWebhook(array $headers, $payload): array;
}
