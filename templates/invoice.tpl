{* WHMCS Smarty Template: Clean & Professional Guest Invoice *}
<link rel="stylesheet" href="modules/addons/guestinvoice/assets/css/guest.css">

<div class="gi-container">
    {* 1. Top Action Navigation Bar (No Print) *}
    <div class="gi-top-bar no-print">
        <div class="gi-top-status-indicator">
            <span class="gi-pill-status gi-pill-{$invoice.status|lower}">{$invoice.status|upper}</span>
            <span class="gi-top-inv-title">Invoice #{$invoice.invoicenum}</span>
        </div>
        <div class="gi-actions">
            <button type="button" class="gi-btn gi-btn-secondary" onclick="window.print()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Cetak Tagihan
            </button>
            {if $allow_pdf_download}
                <a href="{$download_url}" class="gi-btn gi-btn-secondary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Unduh PDF
                </a>
            {/if}
            {if $is_payable}
                <a href="#payment-section" class="gi-btn gi-btn-primary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                    Bayar Sekarang ({$invoice.balance_formatted})
                </a>
            {/if}
        </div>
    </div>

    {* 2. Official Invoice Sheet (Clean Paper Style) *}
    <div class="gi-invoice-card wh-invoice-sheet">
        {* Header Section: Company Logo & Status Details *}
        <div class="wh-header-row">
            <div class="wh-logo-col">
                {if $invoice.company.logo}
                    <img src="{$invoice.company.logo|escape}" alt="{$invoice.company.name|escape}" class="wh-company-logo">
                {else}
                    <div class="wh-company-text-brand">{$invoice.company.name|escape}</div>
                {/if}
                <h1 class="wh-invoice-heading">Invoice #{$invoice.invoicenum}</h1>
            </div>
            <div class="wh-status-col text-right">
                <div class="wh-status-text status-{$invoice.status|lower}">
                    {$invoice.status|upper}
                </div>
                <div class="wh-due-date-text">
                    Due Date: {if $invoice.duedate_friendly}{$invoice.duedate_friendly}{else}{$invoice.duedate}{/if}
                </div>
                {if $invoice.payment_details}
                    <div class="wh-bank-instruction-text">
                        {$invoice.payment_details|nl2br}
                    </div>
                {/if}
            </div>
        </div>

        {* Address Section: Invoiced To & Pay To *}
        <div class="wh-addresses-row">
            <div class="wh-invoiced-to-col">
                <div class="wh-section-label">Invoiced To</div>
                <div class="wh-address-box">
                    {if $invoice.client.companyname}
                        <div class="wh-client-company">{$invoice.client.companyname|escape}</div>
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
                <div class="wh-section-label">Pay To</div>
                <div class="wh-address-box">
                    {$invoice.company.payto|nl2br}
                </div>
            </div>
        </div>

        {* Metadata Section: Invoice Date & Payment Method *}
        <div class="wh-dates-row">
            <div class="wh-date-col">
                <div class="wh-section-label">Invoice Date</div>
                <div class="wh-meta-value">
                    {if $invoice.date_friendly}{$invoice.date_friendly}{else}{$invoice.date}{/if}
                </div>
            </div>
            <div class="wh-method-col text-right">
                <div class="wh-section-label">Payment Method</div>
                <div class="wh-meta-value">
                    <span class="wh-dropdown-badge">
                        {if $invoice.paymentmethod eq 'banktransfer'}Offline Payment{else}{$invoice.paymentmethod|ucfirst}{/if}
                        <span class="wh-dropdown-arrow">▼</span>
                    </span>
                </div>
            </div>
        </div>

        {* Line Items Section: Panel & Table *}
        <div class="wh-items-panel">
            <div class="wh-items-panel-title">Invoice Items</div>
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
                                <div class="wh-item-main-desc">{$item.description|escape|nl2br}{if $item.taxed} <span class="wh-taxed-marker">*</span>{/if}</div>
                            </td>
                            <td class="wh-td-amt text-right">
                                {$item.amount_formatted}
                            </td>
                        </tr>
                    {foreachelse}
                        <tr>
                            <td colspan="2" class="text-center wh-empty-cell">Tidak ada rincian tagihan.</td>
                        </tr>
                    {/foreach}
                </tbody>
                <tfoot>
                    <tr class="wh-tfoot-subtotal">
                        <td class="text-right"><strong>Sub Total</strong></td>
                        <td class="text-right">{$invoice.subtotal_formatted}</td>
                    </tr>
                    {if $invoice.tax gt 0}
                        <tr class="wh-tfoot-tax">
                            <td class="text-right"><strong>{$invoice.tax_label}</strong></td>
                            <td class="text-right">{$invoice.tax_formatted}</td>
                        </tr>
                    {/if}
                    {if $invoice.tax2 gt 0}
                        <tr class="wh-tfoot-tax">
                            <td class="text-right"><strong>{$invoice.tax2_label}</strong></td>
                            <td class="text-right">{$invoice.tax2_formatted}</td>
                        </tr>
                    {/if}
                    {if $invoice.credit gt 0}
                        <tr class="wh-tfoot-credit">
                            <td class="text-right"><strong>Credit</strong></td>
                            <td class="text-right">{$invoice.credit_formatted}</td>
                        </tr>
                    {/if}
                    <tr class="wh-tfoot-total">
                        <td class="text-right"><strong>Total</strong></td>
                        <td class="text-right"><strong>{$invoice.total_formatted}</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        {if $invoice.has_taxed_items}
            <div class="wh-tax-footnote">* Indicates a taxed item.</div>
        {/if}

        {* Transactions Section: Table & Balance Due *}
        <div class="wh-transactions-box">
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
                            <td><code>{$tx.transid|escape}</code></td>
                            <td class="text-right">{$tx.amount_formatted}</td>
                        </tr>
                    {foreachelse}
                        <tr>
                            <td colspan="4" class="text-center wh-no-trans-cell">No Related Transactions Found</td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
            <div class="wh-balance-summary text-right">
                <span class="wh-balance-title">Balance</span>
                <span class="wh-balance-amount">{$invoice.balance_formatted}</span>
            </div>
        </div>
    </div>

    {* 3. Guest Payment Checkout Card (Separated & Professional, No Print) *}
    {if $is_payable}
        <div id="payment-section" class="gi-checkout-card no-print">
            <div class="gi-checkout-head">
                <div class="gi-checkout-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                </div>
                <div>
                    <h2 class="gi-checkout-title">Pembayaran Online Tanpa Login</h2>
                    <p class="gi-checkout-subtitle">Pilih metode pembayaran yang Anda inginkan untuk melunasi tagihan ini:</p>
                </div>
            </div>

            <form method="post" action="{$pay_url}">
                <input type="hidden" name="token" value="{$token|escape}">
                
                <div class="gi-gateway-selection-grid">
                    {foreach from=$gateways item=gw key=k}
                        <label class="gi-gw-card {if $gw.is_default || $k eq 0}selected{/if}">
                            <input type="radio" name="gateway" value="{$gw.module|escape}" {if $gw.is_default || $k eq 0}checked{/if}>
                            <div class="gi-gw-body">
                                <span class="gi-gw-name">{$gw.name|escape}</span>
                                <span class="gi-gw-channel-info">
                                    {if $gw.module eq 'banktransfer'}
                                        Transfer Manual / ATM / BCA / Mandiri / BNI / BRI
                                    {elseif $gw.module eq 'midtrans' || $gw.module eq 'veritrans'}
                                        QRIS, GoPay, OVO, ShopeePay, Virtual Account
                                    {elseif $gw.module eq 'xendit'}
                                        Virtual Account Semua Bank, QRIS, E-Wallet
                                    {elseif $gw.module eq 'tripay'}
                                        QRIS Realtime, VA Bank, Alfamart/Indomaret
                                    {elseif $gw.module eq 'doku'}
                                        DOKU Checkout, Kartu Kredit, Virtual Account
                                    {elseif $gw.module eq 'duitku'}
                                        Virtual Account, QRIS, E-Wallet, Gerai Retail
                                    {else}
                                        Instruksi Resmi Pembayaran Online
                                    {/if}
                                </span>
                            </div>
                        </label>
                    {foreachelse}
                        <div class="gi-alert gi-alert-warning" style="grid-column: 1 / -1;">
                            Tidak ada metode pembayaran guest yang aktif saat ini. Silakan hubungi bagian penagihan.
                        </div>
                    {/foreach}
                </div>

                {if $gateways}
                    <div class="gi-checkout-submit-bar">
                        <div class="gi-checkout-balance-display">
                            <span>Total yang harus dibayar:</span>
                            <strong>{$invoice.balance_formatted}</strong>
                        </div>
                        <button type="submit" class="gi-btn gi-btn-primary gi-btn-pay">
                            Lanjutkan Pembayaran
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        </button>
                    </div>
                {/if}
            </form>
        </div>
    {elseif $invoice.status eq 'Paid'}
        <div class="gi-paid-card no-print">
            <div class="gi-paid-badge-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            </div>
            <div>
                <h3 class="gi-paid-title">Tagihan Ini Telah Lunas</h3>
                <p class="gi-paid-desc">Terima kasih atas pembayaran Anda. Tagihan telah diverifikasi dan dicatat lunas pada sistem kami {if $invoice.datepaid}(Tanggal: {$invoice.datepaid}){/if}.</p>
            </div>
        </div>
    {/if}

    {* Footer *}
    <div class="gi-footer no-print">
        <p>&copy; {$invoice.company.name|escape}. Layanan Akses Tagihan Langsung Terenkripsi.</p>
    </div>
</div>

<script src="modules/addons/guestinvoice/assets/js/guest.js"></script>
