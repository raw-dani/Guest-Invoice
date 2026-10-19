// Guest Invoice interactive scripts
document.addEventListener('DOMContentLoaded', function () {
    // Interactive Gateway Selector Radio Cards
    const gatewayCards = document.querySelectorAll('.gi-gw-card, .gi-gateway-card');
    gatewayCards.forEach(function (card) {
        card.addEventListener('click', function () {
            gatewayCards.forEach(c => {
                c.classList.remove('selected');
                c.classList.remove('active');
            });
            this.classList.add('selected');
            this.classList.add('active');
            const radio = this.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
            }
        });
    });
});

/**
 * Universal text copying with visual feedback
 */
function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function () {
            showToast('Tersalin ke papan klip!');
        });
    } else {
        // Fallback
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
            showToast('Tersalin ke papan klip!');
        } catch (err) {
            console.error('Copy failed', err);
        }
        document.body.removeChild(textArea);
    }
}

function showToast(message) {
    let toast = document.getElementById('gi-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'gi-toast';
        toast.style.position = 'fixed';
        toast.style.bottom = '24px';
        toast.style.right = '24px';
        toast.style.backgroundColor = '#1e293b';
        toast.style.color = '#ffffff';
        toast.style.padding = '10px 18px';
        toast.style.borderRadius = '8px';
        toast.style.fontSize = '14px';
        toast.style.fontWeight = '600';
        toast.style.zIndex = '9999';
        toast.style.boxShadow = '0 10px 15px -3px rgba(0, 0, 0, 0.2)';
        toast.style.transition = 'opacity 0.3s ease';
        document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.style.opacity = '1';
    setTimeout(function () {
        toast.style.opacity = '0';
    }, 2200);
}
