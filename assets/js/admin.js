// Admin guest invoice interactive script
function copyGuestLink(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;

    input.select();
    input.setSelectionRange(0, 99999);

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(input.value).then(function () {
            triggerCopySuccess(event.target);
        });
    } else {
        document.execCommand("copy");
        triggerCopySuccess(event.target);
    }
}

function triggerCopySuccess(button) {
    if (!button) return;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fa fa-check"></i> Tersalin!';
    button.classList.add('btn-success');
    setTimeout(function () {
        button.innerHTML = originalText;
        button.classList.remove('btn-success');
    }, 2000);
}
