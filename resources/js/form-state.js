const submitSelector = 'button[type="submit"], button:not([type]), input[type="submit"]';

function restoreForm(form) {
    delete form.dataset.submitting;
    form.removeAttribute('aria-busy');

    form.querySelectorAll('[data-submit-state]').forEach((control) => {
        if (control instanceof HTMLButtonElement && control.dataset.originalHtml !== undefined) {
            control.innerHTML = control.dataset.originalHtml;
            delete control.dataset.originalHtml;
        }
        if (control instanceof HTMLInputElement && control.dataset.originalValue !== undefined) {
            control.value = control.dataset.originalValue;
            delete control.dataset.originalValue;
        }
        control.disabled = control.dataset.wasDisabled === 'true';
        control.classList.remove('is-submitting');
        control.removeAttribute('aria-busy');
        control.removeAttribute('aria-disabled');
        delete control.dataset.submitState;
        delete control.dataset.wasDisabled;
    });
}

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
    if (form.method.toLowerCase() === 'get' || form.dataset.allowMultipleSubmit === 'true') return;

    if (form.dataset.submitting === 'true') {
        event.preventDefault();
        return;
    }

    form.dataset.submitting = 'true';
    form.setAttribute('aria-busy', 'true');

    const submitter = event.submitter instanceof HTMLElement
        ? event.submitter
        : form.querySelector(submitSelector);

    // Let the browser serialize the clicked button's name/value before disabling it.
    window.setTimeout(() => {
        form.querySelectorAll(submitSelector).forEach((control) => {
            control.dataset.submitState = 'locked';
            control.dataset.wasDisabled = String(control.disabled);
            control.disabled = true;
            control.setAttribute('aria-disabled', 'true');
        });

        if (submitter) {
            submitter.classList.add('is-submitting');
            submitter.setAttribute('aria-busy', 'true');

            const loadingLabel = submitter.dataset.loadingLabel || 'Đang xử lý…';
            if (submitter instanceof HTMLButtonElement) {
                submitter.dataset.originalHtml = submitter.innerHTML;
                const spinner = document.createElement('span');
                spinner.className = 'spinner-border spinner-border-sm';
                spinner.setAttribute('aria-hidden', 'true');
                submitter.replaceChildren(spinner, document.createTextNode(` ${loadingLabel}`));
            } else if (submitter instanceof HTMLInputElement) {
                submitter.dataset.originalValue = submitter.value;
                submitter.value = loadingLabel;
            }
        }
    }, 0);
});

window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting="true"]').forEach(restoreForm);
});
