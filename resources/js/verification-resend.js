const RESEND_WAIT_MS = 60_000;

document.querySelectorAll('[data-verification-resend]').forEach((button) => {
    const label = button.querySelector('[data-resend-label]');
    if (!label) return;

    const sentAt = Number(button.dataset.sentAt || 0) * 1000;
    const waitUntil = sentAt + RESEND_WAIT_MS;

    const update = () => {
        const seconds = Math.max(0, Math.ceil((waitUntil - Date.now()) / 1000));
        button.disabled = seconds > 0;
        button.setAttribute('aria-disabled', seconds > 0 ? 'true' : 'false');
        label.textContent = seconds > 0 ? `Gửi lại sau ${seconds} giây` : 'Gửi lại liên kết';

        if (seconds > 0) window.setTimeout(update, 1000);
    };

    update();
});

