{* WHMCS Smarty Template: Payment Instructions & Gateway Form *}
<link rel="stylesheet" href="modules/addons/guestinvoice/assets/css/guest.css">

<div class="gi-container">
    <div class="gi-top-bar">
        <a href="{$invoice_url}" class="gi-btn gi-btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Kembali ke Tagihan
        </a>
        <div class="gi-brand-title">Invoice #{$invoice.invoicenum} — {$invoice.balance_formatted}</div>
    </div>

    <div class="gi-invoice-card" style="margin-top: 24px;">
        {if $instructions}
            {* Manual Bank Transfer / Instructions View *}
            <div class="gi-payment-instruction-box">
                <div class="gi-instruction-header">
                    <h2>{$instructions.title|escape}</h2>
                    <p>Silakan selesaikan pembayaran sesuai rincian rekening di bawah ini:</p>
                </div>

                <div class="gi-bank-card">
                    <div class="gi-bank-row">
                        <span class="gi-bank-label">Jumlah Yang Harus Dibayar:</span>
                        <div class="gi-bank-value-wrapper">
                            <span class="gi-bank-value gi-amount-highlight">{$instructions.amount_to_pay}</span>
                            <button type="button" class="gi-copy-btn" onclick="copyText('{$invoice.balance}')">Salin Nominal</button>
                        </div>
                    </div>

                    <div class="gi-bank-row">
                        <span class="gi-bank-label">Bank Tujuan:</span>
                        <span class="gi-bank-value">{$instructions.bank_name|escape}</span>
                    </div>

                    <div class="gi-bank-row">
                        <span class="gi-bank-label">Nomor Rekening:</span>
                        <div class="gi-bank-value-wrapper">
                            <span class="gi-bank-value" id="bank-acc-num">{$instructions.account_number|escape}</span>
                            <button type="button" class="gi-copy-btn" onclick="copyText('{$instructions.account_number|escape}')">Salin Rekening</button>
                        </div>
                    </div>

                    <div class="gi-bank-row">
                        <span class="gi-bank-label">Atas Nama Rekening:</span>
                        <span class="gi-bank-value">{$instructions.account_name|escape}</span>
                    </div>

                    <div class="gi-bank-row">
                        <span class="gi-bank-label">Berita Transfer / Referensi:</span>
                        <div class="gi-bank-value-wrapper">
                            <span class="gi-bank-value" id="bank-ref-code">{$instructions.reference_code|escape}</span>
                            <button type="button" class="gi-copy-btn" onclick="copyText('{$instructions.reference_code|escape}')">Salin Referensi</button>
                        </div>
                    </div>
                </div>

                {if $instructions.description}
                    <div class="gi-instruction-text">
                        {$instructions.description|nl2br}
                    </div>
                {/if}

                {if $instructions.notes}
                    <div class="gi-alert gi-alert-info" style="margin-top: 20px;">
                        <strong>Catatan Penting:</strong> {$instructions.notes|escape}
                    </div>
                {/if}

                <div style="margin-top: 32px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <a href="{$invoice_url}" class="gi-btn gi-btn-secondary">Kembali ke Detail Tagihan</a>
                    <a href="{$invoice_url}&action=result" class="gi-btn gi-btn-primary">
                        Saya Sudah Melakukan Transfer
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </a>
                </div>
            </div>
        {elseif $html_form}
            {* Standard WHMCS Gateway Form HTML *}
            <div class="gi-payment-instruction-box">
                <h2>Pembayaran Online</h2>
                <p>Klik tombol pembayaran di bawah untuk memproses transaksi dengan gateway terpilih:</p>
                <div class="gi-form-container" style="padding: 24px; text-align: center;">
                    {$html_form}
                </div>
            </div>
        {/if}
    </div>
</div>

<script src="modules/addons/guestinvoice/assets/js/guest.js"></script>
