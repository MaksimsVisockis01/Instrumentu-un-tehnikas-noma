'use strict';

const form = document.getElementById('login-form');

if (form) {
    const identifier = document.getElementById('identifier');
    const password = document.getElementById('password');
    const toggle = document.getElementById('password-toggle');
    const submit = document.getElementById('login-button');
    const alert = document.getElementById('form-error');
    const capsWarning = document.getElementById('caps-warning');

    // Bez JavaScript formas pārbaudi veic pārlūks un PHP.
    form.noValidate = true;
    toggle.hidden = false;

    const showFieldError = (input, message) => {
        const output = document.getElementById(`${input.id}-error`);
        output.textContent = message;
        output.hidden = message === '';
        input.setAttribute('aria-invalid', message === '' ? 'false' : 'true');
    };

    const resetSubmit = () => {
        submit.disabled = false;
        submit.removeAttribute('aria-busy');
        submit.querySelector('.button-text').textContent = 'Pieslēgties';
        submit.querySelector('.spinner').hidden = true;
        submit.querySelector('.button-arrow').hidden = false;
    };

    toggle.addEventListener('click', () => {
        const visible = password.type === 'password';
        password.type = visible ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', String(visible));
        toggle.setAttribute('aria-label', visible ? 'Paslēpt paroli' : 'Rādīt paroli');
    });

    password.addEventListener('keyup', (event) => {
        capsWarning.hidden = !event.getModifierState('CapsLock');
    });
    password.addEventListener('blur', () => { capsWarning.hidden = true; });

    [identifier, password].forEach((input) => {
        input.addEventListener('input', () => showFieldError(input, ''));
    });

    form.addEventListener('submit', (event) => {
        identifier.value = identifier.value.trim();
        const errors = [];
        if (!identifier.value) {
            errors.push([identifier, 'Ievadiet e-pastu vai lietotājvārdu.']);
        }
        if (!password.value) {
            errors.push([password, 'Ievadiet paroli.']);
        } else if (new TextEncoder().encode(password.value).length > 72) {
            errors.push([password, 'Parole ir pārāk gara (maksimums 72 baiti).']);
        }

        if (errors.length) {
            event.preventDefault();
            alert.hidden = true;
            errors.forEach(([input, message]) => showFieldError(input, message));
            errors[0][0].focus();
            return;
        }

        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
        submit.querySelector('.button-text').textContent = 'Pieslēdzas…';
        submit.querySelector('.spinner').hidden = false;
        submit.querySelector('.button-arrow').hidden = true;
    });

    // Poga paliek lietojama arī pēc pārlūka Back pogas.
    window.addEventListener('pageshow', resetSubmit);
    if (!alert.hidden) alert.focus();
}
