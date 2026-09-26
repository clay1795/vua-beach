function bracketName(field) {
    const [root, ...segments] = field.split('.');
    return root + segments.map((segment) => `[${segment}]`).join('');
}

function appendDescription(control, id) {
    const descriptions = new Set((control.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
    descriptions.add(id);
    control.setAttribute('aria-describedby', [...descriptions].join(' '));
}

function initializeValidationErrors() {
    const summary = document.querySelector('[data-validation-summary]');
    const items = [...document.querySelectorAll('[data-validation-field]')];
    if (!items.length) return;

    const context = summary?.dataset.validationContext;
    const contextScope = context
        ? [...document.querySelectorAll('form[data-validation-context]')].find((element) => element.dataset.validationContext === context)
        : null;
    const controls = [...(contextScope || document).querySelectorAll('[name]')];
    let firstInvalid;

    items.forEach((item, index) => {
        const field = item.dataset.validationField;
        const expectedName = bracketName(field);
        const control = controls.find((candidate) => candidate.name === field || candidate.name === expectedName);
        if (!control) return;

        control.classList.add('is-invalid');
        control.setAttribute('aria-invalid', 'true');

        let feedback = control.id ? document.getElementById(`${control.id}-error`) : null;
        feedback ||= control.parentElement?.querySelector('.invalid-feedback');

        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.dataset.generatedValidation = 'true';
            feedback.textContent = item.textContent.trim();
            const anchor = control.closest('.input-group, .form-check') || control;
            anchor.insertAdjacentElement('afterend', feedback);
        }

        feedback.id ||= `validation-error-${index}`;
        appendDescription(control, feedback.id);

        if (!firstInvalid && !control.disabled && control.type !== 'hidden') firstInvalid = control;
    });

    if (firstInvalid) {
        window.requestAnimationFrame(() => firstInvalid.focus({ preventScroll: true }));
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeValidationErrors, { once: true });
} else {
    initializeValidationErrors();
}
