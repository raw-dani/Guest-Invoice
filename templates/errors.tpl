{* WHMCS Smarty Template: Safe Error Screen *}
<link rel="stylesheet" href="modules/addons/guestinvoice/assets/css/guest.css">

<div class="gi-container" style="max-width: 600px; margin: 60px auto;">
    <div class="gi-invoice-card" style="text-align: center; padding: 48px 32px;">
        <div class="gi-result-icon gi-icon-failed" style="margin-bottom: 20px;">
            <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        </div>

        <h1 style="color: #1f2937; font-size: 24px; margin-bottom: 12px;">{$error_title|escape}</h1>
        <p style="color: #6b7280; font-size: 15px; line-height: 1.6; margin-bottom: 28px;">
            {$error_message|escape}
        </p>

        {if $back_url}
            <a href="{$back_url}" class="gi-btn gi-btn-secondary">
                Kembali
            </a>
        {/if}
    </div>
</div>
