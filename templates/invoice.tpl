{* WHMCS Smarty Template: Guest Invoice View *}
<link rel="stylesheet" href="modules/addons/guestinvoice/assets/css/guest.css">

<div class="gi-container">
    {* Header & Action Bar *}
    <div class="gi-top-bar no-print">
        <div class="gi-brand">
            {if $invoice.company.logo}
                <img src="{$invoice.company.logo|escape}" alt="{$invoice.company.name|escape}" style="max-height: 40px; width: auto; object-fit: contain;">
            {else}
                <span class="gi-brand-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                </span>
                <span class="gi-brand-title">{$invoice.company.name|escape}</span>
            {/if}
        </div>
        <div class="gi-actions">
            <button type="button" class="gi-btn gi-btn-secondary" onclick="window.print()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Cetak Tagihan
            </button>
            {if $allow_pdf_download}
                <a href="{$download_url}" class="gi-btn gi-btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Unduh PDF
                </a>
            {/if}
            {if $is_payable}
                <a href="#payment-section" class="gi-btn gi-btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                    Bayar Sekarang ({$invoice.balance_formatted})
                </a>
            {/if}
        </div>
    </div>

    {* Main Invoice Card Styled to match WHMCS Official Invoice Template *}
    <div class="gi-invoice-card wh-invoice-card">
        {* 1. Top Header: Logo on left, Big Status & Bank Details on right *}
        <div class="wh-header-row">
            <div class="wh-logo-col">
                {if $invoice.company.logo}
                    <img src="{$invoice.company.logo|escape}" alt="{$invoice.company.name|escape}" class="wh-company-logo">
                {else}
                    <div class="wh-company-name-fallback">{$invoice.company.name|escape}</div>
                {/if}
                <h1 class="wh-invoice-number">Invoice #{$invoice.invoicenum}</h1>
            </div>
            <div class="wh-status-col text-right">
                <div class="wh-status-title status-{$invoice.status|lower}">
                    {$invoice.status|upper}
                </div>
                <div class="wh-due-date">
                    Due Date: {if $invoice.duedate_friendly}{$invoice.duedate_friendly}{else}{$invoice.duedate}{/if}
                </div>
                {if $invoice.payment_details}
                    <div class="wh-payment-details-box">
                        {$invoice.payment_details|nl2br}
                    </div>
                {/if}
            </div>
        </div>

        {* 2. Row: Invoiced To & Pay To *}
        <div class="wh-addresses-row">
            <div class="wh-invoiced-to-col">
                <div class="wh-addr-heading">Invoiced To</div>
                <div class="wh-addr-body">
                    {if $invoice.client.companyname}
                        <div class="wh-company-client">{$invoice.client.companyname|escape}</div>
                    {/if}
                    <div class="wh-client-name">{$invoice.client.fullname|escape}</div>
                    {if $invoice.client.address1}<div>{$invoice.client.address1|escape}</div>{/if}
                    {if $invoice.client.address2}<div>{$invoice.client.address2|escape}</div>{/if}
                    <div>
                        {if $invoice.client.city}{$invoice.client.city|escape}, {/if}
                        {if $invoice.client.state}{$invoice.client.state|escape}, {/if}
                        {$invoice.client.postcode|escape}
                    </div>
                    {if $invoice.client.country}<div>{$invoice.client.country|escape}</div>{/if}
                </div>
            </div>
            <div class="wh-pay-to-col text-right">
                <div class="wh-addr-heading">Pay To</div>
                <div class="wh-addr-body">
                    {$invoice.company.payto|nl2br}
                </div>
            </div>
        </div>

        {* 3. Row: Invoice Date & Payment Method *}
        <div class="wh-dates-row">
            <div class="wh-date-col">
                <div class="wh-addr-heading">Invoice Date</div>
                <div class="wh-date-val">
                    {if $invoice.date_friendly}{$invoice.date_friendly}{else}{$invoice.date}{/if}
                </div>
            </div>
            <div class="wh-method-col text-right">
                <div class="wh-addr-heading">Payment Method</div>
                <div class="wh-method-val">
                    <span class="wh-payment-method-badge">
                        {if $invoice.paymentmethod eq 'banktransfer'}Offline Payment{else}{$invoice.paymentmethod|ucfirst}{/if}
                    </span>
                </div>
            </div>
        </div>

        {* 4. Panel: Invoice Items *}
        <div class="wh-items-panel">
            <div class="wh-items-panel-header">Invoice Items</div>
            <div class="wh-table-responsive">
                <table class="wh-items-table">
                    <thead>
                        <tr>
                            <th class="wh-th-desc">Description</th>
                            <th class="wh-th-amt text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$invoice.items item=item}
                            <tr>
                                <td class="wh-td-desc">
                                    {$item.description|escape|nl2br}
                                    {if $item.taxed}<span class="wh-tax-star"> *</span>{/if}
                                </td>
                                <td class="wh-td-amt text-right">
                                    {$item.amount_formatted}
                                </td>
                            </tr>
                        {foreachelse}
                            <tr>
                                <td colspan="2" class="text-center" style="padding: 16px; color: #888;">No line items found.</td>
                            </tr>
                        {/foreach}
                    </tbody>
                    <tfoot>
                        <tr class="wh-row-subtotal">
                            <td class="text-right"><strong>Sub Total</strong></td>
                            <td class="text-right">{$invoice.subtotal_formatted}</td>
                        </tr>
                        {if $invoice.tax gt 0}
                            <tr class="wh-row-tax">
                                <td class="text-right"><strong>{$invoice.tax_label}</strong></td>
                                <td class="text-right">{$invoice.tax_formatted}</td>
                            </tr>
                        {/if}
                        {if $invoice.tax2 gt 0}
                            <tr class="wh-row-tax">
                                <td class="text-right"><strong>{$invoice.tax2_label}</strong></td>
                                <td class="text-right">{$invoice.tax2_formatted}</td>
                            </tr>
                        {/if}
                        {if $invoice.credit gt 0}
                            <tr class="wh-row-credit">
                                <td class="text-right"><strong>Credit</strong></td>
                                <td class="text-right">{$invoice.credit_formatted}</td>
                            </tr>
                        {/if}
                        <tr class="wh-row-total">
                            <td class="text-right"><strong>Total</strong></td>
                            <td class="text-right"><strong>{$invoice.total_formatted}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        {if $invoice.has_taxed_items}
            <div class="wh-tax-note">* Indicates a taxed item.</div>
        {/if}

        {* 5. Transactions Table *}
        <div class="wh-transactions-wrapper">
            <table class="wh-trans-table">
                <thead>
                    <tr>
                        <th class="text-left" style="width: 25%;">Transaction Date</th>
                        <th class="text-left" style="width: 25%;">Gateway</th>
                        <th class="text-left" style="width: 25%;">Transaction ID</th>
                        <th class="text-right" style="width: 25%;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$invoice.transactions item=tx}
                        <tr>
                            <td>{$tx.date}</td>
                            <td>{$tx.gateway|escape}</td>
                            <td>{$tx.transid|escape}</td>
                            <td class="text-right">{$tx.amount_formatted}</td>
                        </tr>
                    {foreachelse}
                        <tr>
                            <td colspan="4" class="text-center wh-no-trans">No Related Transactions Found</td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
            <div class="wh-balance-row text-right">
                <span class="wh-balance-label">Balance</span>
                <span class="wh-balance-val">{$invoice.balance_formatted}</span>
            </div>
        </div>

        {* Payment Checkout Section (if payable) *}
        {if $is_payable}
            <div id="payment-section" class="gi-checkout-box no-print">
                <div class="gi-checkout-header">
                    <h3>Pilih Metode Pembayaran</h3>
                    <p>Lakukan pembayaran instan tanpa perlu login. Pilih metode yang Anda inginkan:</p>
                </div>
                <form method="post" action="{$pay_url}">
                    <input type="hidden" name="token" value="{$token|escape}">
                    
                    <div class="gi-gateway-grid">
                        {foreach from=$gateways item=gw key=k}
                            <label class="gi-gateway-card {if $gw.is_default || $k eq 0}active{/if}">
                                <input type="radio" name="gateway" value="{$gw.module|escape}" {if $gw.is_default || $k eq 0}checked{/if}>
                                <div class="gi-gw-info">
                                    <span class="gi-gw-title">{$gw.name|escape}</span>
                                    <span class="gi-gw-desc">
                                        {if $gw.module eq 'banktransfer'}
                                            Transfer Bank Manual / ATM / Mobile Banking
                                        {elseif $gw.module eq 'midtrans' || $gw.module eq 'veritrans'}
                                            QRIS, GoPay, OVO, ShopeePay, Virtual Account
                                        {elseif $gw.module eq 'xendit'}
                                            Virtual Account, QRIS, E-Wallet, Retail
                                        {elseif $gw.module eq 'tripay'}
                                            QRIS Realtime, VA Semua Bank, Alfamart/Indomaret
                                        {elseif $gw.module eq 'doku'}
                                            DOKU Checkout, Kartu Kredit, VA
                                        {elseif $gw.module eq 'duitku'}
                                            Duitku (VA Semua Bank, QRIS, E-Wallet, Gerai Retail)
                                        {else}
                                            Instruksi Resmi Pembayaran Online
                                        {/if}
                                    </span>
                                </div>
                            </label>
                        {foreachelse}
                            <div class="gi-alert gi-alert-warning">
                                Tidak ada metode pembayaran guest yang aktif saat ini. Silakan hubungi bagian penagihan.
                            </div>
                        {/foreach}
                    </div>

                    {if $gateways}
                        <div style="margin-top: 24px; text-align: right;">
                            <button type="submit" class="gi-btn gi-btn-primary gi-btn-lg">
                                Lanjutkan Pembayaran {$invoice.balance_formatted}
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                            </button>
                        </div>
                    {/if}
                </form>
            </div>
        {elseif $invoice.status eq 'Paid'}
            <div class="gi-paid-notice no-print">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <div>
                    <h4>Tagihan Ini Telah Lunas</h4>
                    <p>Terima kasih! Pembayaran untuk tagihan ini telah diterima dan diverifikasi oleh sistem pada {$invoice.datepaid}.</p>
                </div>
            </div>
        {/if}
    </div>

    {* Footer *}
    <div class="gi-footer no-print">
        <p>&copy; {$invoice.company.name|escape}. Layanan Portal Tagihan Akses Langsung Terenkripsi.</p>
    </div>
</div>

<script src="modules/addons/guestinvoice/assets/js/guest.js"></script>
