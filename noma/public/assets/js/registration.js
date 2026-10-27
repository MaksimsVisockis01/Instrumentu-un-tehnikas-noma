'use strict';

const registrationForm = document.getElementById('registration-form');

if (registrationForm) {
    const fields = Object.fromEntries(['username', 'first_name', 'last_name', 'email', 'phone', 'password', 'password_confirmation', 'accept_terms'].map((name) => [name, document.getElementById(name)]));
    const button = document.getElementById('register-button');
    const alertBox = document.getElementById('registration-error');
    const information = document.getElementById('registration-information');
    const byteLength = (text) => new TextEncoder().encode(text).length;

    registrationForm.noValidate = true;

    const showError = (name, message) => {
        const output = document.getElementById(`${name}-error`);
        output.textContent = message;
        output.hidden = !message;
        fields[name].setAttribute('aria-invalid', message ? 'true' : 'false');
    };

    document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
        toggle.hidden = false;
        toggle.addEventListener('click', () => {
            const input = fields[toggle.dataset.passwordToggle];
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', String(visible));
            const label = input.id === 'password' ? 'paroli' : 'atkārtoto paroli';
            toggle.setAttribute('aria-label', `${visible ? 'Paslēpt' : 'Rādīt'} ${label}`);
        });
    });

    document.getElementById('notice-link').addEventListener('click', (event) => {
        event.preventDefault();
        information.open = true;
        information.querySelector('summary').focus();
    });

    Object.entries(fields).forEach(([name, input]) => {
        input.addEventListener(input.type === 'checkbox' ? 'change' : 'input', () => showError(name, ''));
    });

    const resetButton = () => {
        button.disabled = false;
        button.removeAttribute('aria-busy');
        button.querySelector('.button-text').textContent = 'Izveidot kontu';
        button.querySelector('.button-arrow').hidden = false;
        button.querySelector('.spinner').hidden = true;
    };
    window.addEventListener('pageshow', resetButton);

    registrationForm.addEventListener('submit', (event) => {
        const errors = {};
        ['username', 'first_name', 'last_name', 'email', 'phone'].forEach((name) => { fields[name].value = fields[name].value.trim(); });

        if (!/^[A-Za-z0-9][A-Za-z0-9._-]{2,49}$/.test(fields.username.value)) {
            errors.username = 'Izmantojiet 3–50 latīņu burtus vai ciparus; atļauti arī . _ -. Sāciet ar burtu vai ciparu.';
        }
        [['first_name', 'vārdu'], ['last_name', 'uzvārdu']].forEach(([name, label]) => {
            if (Array.from(fields[name].value).length > 100 || !/^[\p{L}\p{M}]+(?:[ '’\-][\p{L}\p{M}]+)*$/u.test(fields[name].value)) {
                errors[name] = `Ievadiet ${label} (līdz 100 rakstzīmēm).`;
            }
        });
        if (!fields.email.value || fields.email.validity.typeMismatch || byteLength(fields.email.value) > 254 || !fields.email.value.split('@')[1]?.includes('.')) {
            errors.email = 'Ievadiet derīgu e-pasta adresi.';
        }

        let phone = fields.phone.value.replace(/[\s().-]+/g, '');
        if (/^[1-9][0-9]{7}$/.test(phone)) phone = `+371${phone}`;
        else if (phone.startsWith('00')) phone = `+${phone.slice(2)}`;
        if (byteLength(fields.phone.value) > 40 || !/^\+[1-9][0-9]{7,14}$/.test(phone) || (phone.startsWith('+371') && phone.length !== 12)) {
            errors.phone = 'Ievadiet 8 ciparu Latvijas numuru vai numuru ar valsts kodu, piemēram, +37129123456.';
        }
        if (Array.from(fields.password.value).length < 8) {
            errors.password = 'Parolei jābūt vismaz 8 rakstzīmes garai.';
        } else if (byteLength(fields.password.value) > 72 || fields.password.value.includes('\0')) {
            errors.password = 'Parole ir pārāk gara vai satur neatļautas rakstzīmes (maksimums 72 baiti).';
        }
        if (!fields.password_confirmation.value || fields.password.value !== fields.password_confirmation.value) {
            errors.password_confirmation = 'Paroles nesakrīt. Ievadiet paroli vēlreiz.';
        }
        if (!fields.accept_terms.checked) {
            errors.accept_terms = 'Lai izveidotu kontu, apstipriniet iepazīšanos ar informāciju un datu pareizību.';
        }

        Object.keys(fields).forEach((name) => showError(name, errors[name] || ''));
        if (Object.keys(errors).length) {
            event.preventDefault();
            alertBox.hidden = true;
            fields[Object.keys(errors)[0]].focus();
            return;
        }

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.querySelector('.button-text').textContent = 'Veido kontu…';
        button.querySelector('.button-arrow').hidden = true;
        button.querySelector('.spinner').hidden = false;
    });

    if (!alertBox.hidden) alertBox.focus();
}
