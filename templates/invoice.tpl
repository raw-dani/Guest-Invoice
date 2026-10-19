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

    {* Main Invoice Card *}
    <div class="gi-invoice-card">
        {* Status Banner *}
        <div class="gi-invoice-header">
            <div>
                <h1 class="gi-invoice-title">Tagihan #{$invoice.invoicenum}</h1>
                <div class="gi-meta-row">
                    <span>Tanggal Terbit: <strong>{$invoice.date}</strong></span>
                    <span class="gi-dot">•</span>
                    <span>Jatuh Tempo: <strong>{$invoice.duedate}</strong></span>
                </div>
            </div>
            <div>
                {if $invoice.status eq 'Paid'}
                    <span class="gi-status-badge gi-status-paid">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        Lunas
                    </span>
                {elseif $invoice.status eq 'Unpaid'}
                    <span class="gi-status-badge gi-status-unpaid">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        Belum Dibayar
                    </span>
                {elseif $invoice.status eq 'Overdue'}
                    <span class="gi-status-badge gi-status-overdue">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"></polygon><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        Jatuh Tempo
                    </span>
                {else}
                    <span class="gi-status-badge gi-status-other">{$invoice.status}</span>
                {/if}
            </div>
        </div>

        {* Billing Details: Pay To & Invoiced To *}
        <div class="gi-billing-grid">
            <div class="gi-billing-col">
                <span class="gi-section-subtitle">Diterbitkan Oleh:</span>
                <div class="gi-entity-name">{$invoice.company.name|escape}</div>
                {if $invoice.company.payto}
                    <div class="gi-entity-address">{$invoice.company.payto|nl2br}</div>
                {/if}
            </div>
            <div class="gi-billing-col">
                <span class="gi-section-subtitle">Ditagihkan Kepada:</span>
                <div class="gi-entity-name">{$invoice.client.fullname|escape}</div>
                {if $invoice.client.companyname}
                    <div class="gi-entity-sub">{$invoice.client.companyname|escape}</div>
                {/if}
                <div class="gi-entity-address">
                    {if $invoice.client.address1}{$invoice.client.address1|escape}<br>{/if}
                    {if $invoice.client.city}{$invoice.client.city|escape}, {/if}
                    {if $invoice.client.state}{$invoice.client.state|escape} {/if}
                    {if $invoice.client.postcode}{$invoice.client.postcode|escape}<br>{/if}
                    {if $invoice.client.country}{$invoice.client.country|escape}{/if}
                </div>
                <div class="gi-entity-email">{$invoice.client.email|escape}</div>
            </div>
        </div>

        {* Line Items Table *}
        <div class="gi-table-wrapper">
            <table class="gi-table">
                <thead>
                    <tr>
                        <th style="width: 70%;">Deskripsi Layanan / Produk</th>
                        <th style="width: 30%; text-align: right;">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$invoice.items item=item}
                        <tr>
                            <td>
                                <div class="gi-item-desc">{$item.description|escape|nl2br}</div>
                            </td>
                            <td style="text-align: right; font-weight: 500;">
                                {$item.amount_formatted}
                            </td>
                        </tr>
                    {foreachelse}
                        <tr>
                            <td colspan="2" style="text-align: center; color: #94a3b8;">Tidak ada rincian item.</td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>

        {* Financial Breakdown / Totals *}
        <div class="gi-summary-section">
            <div class="gi-summary-box">
                <div class="gi-summary-row">
                    <span>Subtotal</span>
                    <span>{$invoice.subtotal_formatted}</span>
                </div>
                {if $invoice.tax gt 0}
                    <div class="gi-summary-row">
                        <span>Pajak (Tax)</span>
                        <span>{$invoice.tax_formatted}</span>
                    </div>
                {/if}
                {if $invoice.credit gt 0}
                    <div class="gi-summary-row">
                        <span>Kredit Diterapkan</span>
                        <span>-{$invoice.credit_formatted}</span>
                    </div>
                {/if}
                <div class="gi-summary-row gi-summary-total">
                    <span>Total Tagihan</span>
                    <span>{$invoice.total_formatted}</span>
                </div>
                <div class="gi-summary-row gi-summary-balance">
                    <span>Sisa Tagihan (Balance Due)</span>
                    <span class="gi-highlight-balance">{$invoice.balance_formatted}</span>
                </div>
            </div>
        </div>

        {* Payments Received History *}
        {if $invoice.transactions}
            <div class="gi-transactions-box">
                <h3 class="gi-subheading">Riwayat Pembayaran Diterima</h3>
                <table class="gi-table-sm">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Metode</th>
                            <th>ID Transaksi</th>
                            <th style="text-align: right;">Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$invoice.transactions item=tx}
                            <tr>
                                <td>{$tx.date}</td>
                                <td>{$tx.gateway|escape}</td>
                                <td><code>{$tx.transid|escape}</code></td>
                                <td style="text-align: right; color: #059669; font-weight: bold;">{$tx.amount_formatted}</td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        {/if}

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
