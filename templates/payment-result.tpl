{* WHMCS Smarty Template: Payment Result (Verified Status) *}
<link rel="stylesheet" href="modules/addons/guestinvoice/assets/css/guest.css">

<div class="gi-container" style="max-width: 680px; margin: 40px auto;">
    <div class="gi-invoice-card" style="text-align: center; padding: 48px 32px;">
        {if $is_paid}
            <div class="gi-result-icon gi-icon-success">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            </div>
            <h1 style="color: #065f46; margin: 16px 0 8px 0; font-size: 26px;">Pembayaran Berhasil!</h1>
            <p style="color: #4b5563; font-size: 16px; margin-bottom: 24px;">
                Tagihan <strong>#{$invoice.invoicenum}</strong> telah berhasil dilunasi dan diverifikasi oleh sistem.
            </p>

            <div class="gi-result-summary" style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 20px; margin-bottom: 32px; text-align: left;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="color: #6b7280;">Nomor Tagihan:</span>
                    <strong>#{$invoice.invoicenum}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="color: #6b7280;">Total Nominal:</span>
                    <strong style="color: #059669;">{$invoice.total_formatted}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="color: #6b7280;">Waktu Pelunasan:</span>
                    <span>{$invoice.datepaid}</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #6b7280;">Status Saat Ini:</span>
                    <span class="gi-status-badge gi-status-paid">LUNAS</span>
                </div>
            </div>

            <a href="{$invoice_url}" class="gi-btn gi-btn-primary gi-btn-lg" style="display: inline-block;">
                Lihat Tagihan Resmi
            </a>

        {elseif $is_pending}
            <div class="gi-result-icon gi-icon-pending">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            </div>
            <h1 style="color: #92400e; margin: 16px 0 8px 0; font-size: 26px;">Menunggu Konfirmasi Pembayaran</h1>
            <p style="color: #4b5563; font-size: 16px; margin-bottom: 24px;">
                Kami sedang menunggu laporan status final dari penyedia pembayaran atau bank. Proses verifikasi biasanya memakan waktu beberapa detik hingga beberapa menit.
            </p>

            <div class="gi-result-summary" style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 20px; margin-bottom: 32px; text-align: left;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="color: #6b7280;">Nomor Tagihan:</span>
                    <strong>#{$invoice.invoicenum}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="color: #6b7280;">Sisa Pembayaran:</span>
                    <strong style="color: #d97706;">{$invoice.balance_formatted}</strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #6b7280;">Status Saat Ini:</span>
                    <span class="gi-status-badge gi-status-unpaid">{$status_label}</span>
                </div>
            </div>

            <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
                <a href="{$refresh_url}" class="gi-btn gi-btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                    Periksa Status Sekarang
                </a>
                <a href="{$invoice_url}" class="gi-btn gi-btn-secondary">
                    Kembali ke Halaman Tagihan
                </a>
            </div>

        {else}
            <div class="gi-result-icon gi-icon-failed">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
            </div>
            <h1 style="color: #991b1b; margin: 16px 0 8px 0; font-size: 26px;">Transaksi Dibatalkan / Gagal</h1>
            <p style="color: #4b5563; font-size: 16px; margin-bottom: 24px;">
                Transaksi pembayaran belum diselesaikan. Anda dapat mencoba kembali dengan metode pembayaran lain.
            </p>

            <a href="{$invoice_url}" class="gi-btn gi-btn-primary">
                Coba Bayar Lagi
            </a>
        {/if}
    </div>
</div>
