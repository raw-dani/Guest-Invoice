// Admin guest invoice interactive script
function copyGuestLink(inputId, btnEl) {
    const input = document.getElementById(inputId);
    if (!input) return;

    input.select();
    input.setSelectionRange(0, 99999);

    const btn = btnEl || (window.event ? window.event.currentTarget || window.event.target : null);

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(input.value).then(function () {
            triggerCopySuccess(btn);
        }).catch(function () {
            document.execCommand("copy");
            triggerCopySuccess(btn);
        });
    } else {
        document.execCommand("copy");
        triggerCopySuccess(btn);
    }
}

function triggerCopySuccess(button) {
    if (!button) return;
    const originalHtml = button.innerHTML;
    button.innerHTML = '<i class="fa fa-check"></i> Tersalin!';
    button.classList.add('btn-success');
    button.disabled = true;
    setTimeout(function () {
        button.innerHTML = originalHtml;
        button.classList.remove('btn-success');
        button.disabled = false;
    }, 2000);
}
