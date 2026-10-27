'use strict';

const rentalForm = document.getElementById('rental-form');
const startInput = document.getElementById('rental-start');
const endInput = document.getElementById('rental-end');
const reserveButton = document.getElementById('reserve-button');
const messageBox = document.getElementById('availability-message');
const messageText = document.getElementById('availability-text');
const calendarInstruction = document.getElementById('calendar-instruction');
const calendarDays = [...document.querySelectorAll('.calendar-day')];
const monthLinks = [...document.querySelectorAll('.month-link')];
const currency = new Intl.NumberFormat('lv-LV', {style:'currency', currency:'EUR'});
let selectingEnd = rentalForm?.dataset.selectingEnd === '1';

function updateMonthLinks() {
    monthLinks.forEach(link => {
        const url = new URL(link.href);
        url.searchParams.set('start_date', startInput.value);
        url.searchParams.set('end_date', endInput.value);
        if (selectingEnd && startInput.value && !endInput.value) url.searchParams.set('selection', 'end');
        else url.searchParams.delete('selection');
        link.href = url.pathname + url.search;
    });
}

function validDate(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
    const date = new Date(value + 'T00:00:00Z');
    return !Number.isNaN(date.getTime()) && date.toISOString().slice(0,10) === value;
}

function showDate(value) {
    return validDate(value) ? value.split('-').reverse().join('.') : '—';
}

function paintCalendar() {
    calendarDays.forEach(button => {
        const day = button.dataset.date;
        const selected = Boolean(startInput.value && endInput.value && day >= startInput.value && day < endInput.value);
        const startOnly = Boolean(startInput.value && !endInput.value && day === startInput.value);
        const returnDay = Boolean(endInput.value && day === endInput.value);
        button.classList.toggle('day-selected', selected || startOnly);
        button.classList.toggle('day-return', returnDay);
        button.setAttribute('aria-pressed', String(selected || startOnly || returnDay));
    });
}

function updateQuote() {
    const start = startInput.value;
    const end = endInput.value;
    const valid = validDate(start) && validDate(end) && start >= rentalForm.dataset.today && end > start;
    startInput.setCustomValidity(start && start < rentalForm.dataset.today ? 'Sākuma datums nevar būt pagātnē.' : '');
    endInput.setCustomValidity(start && end && end <= start ? 'Atgriešanas datumam jābūt pēc sākuma.' : '');
    messageBox.classList.remove('available', 'unavailable');
    messageBox.classList.add('pending');
    messageText.textContent = valid ? 'Datumi mainīti. Pārbaudiet pieejamību vai rezervējiet demo.' : 'Izvēlieties derīgu sākuma un atgriešanas datumu.';
    reserveButton.disabled = !valid || rentalForm.dataset.selfOwned === '1';

    const days = valid ? Math.round((Date.parse(end + 'T00:00:00Z') - Date.parse(start + 'T00:00:00Z')) / 86400000) : 0;
    const rent = Number(rentalForm.dataset.priceCents) * days;
    const total = rent + Number(rentalForm.dataset.depositCents);
    const precise = valid && Number.isSafeInteger(total);
    document.getElementById('quote-days').textContent = valid ? String(days) : '—';
    document.getElementById('quote-rent').textContent = precise ? currency.format(rent / 100) : '—';
    document.getElementById('quote-total').textContent = precise ? currency.format(total / 100) : '—';
    document.getElementById('period-caption').textContent = showDate(start) + ' — ' + showDate(end);
    if (valid && !precise) messageText.textContent = 'Šim periodam precīzu summu parādīs serveris pēc pieejamības pārbaudes.';

    if (validDate(start)) rentalForm.elements.month.value = start.slice(0,7);
    updateMonthLinks();
    paintCalendar();
}

if (rentalForm) {
    if (selectingEnd) {
        calendarInstruction.textContent = 'Sākums: ' + showDate(startInput.value) + '. Izvēlieties atgriešanas datumu.';
        updateMonthLinks();
        paintCalendar();
    }
    [startInput, endInput].forEach(input => input.addEventListener('input', () => {
        selectingEnd = false;
        calendarInstruction.textContent = 'Izvēlieties sākumu un pēc tam atgriešanas datumu.';
        updateQuote();
    }));

    calendarDays.forEach(button => {
        if (button.dataset.past === '1') return;
        button.disabled = false;
        button.addEventListener('click', () => {
            const date = button.dataset.date;
            if (!selectingEnd || !startInput.value || date <= startInput.value) {
                startInput.value = date;
                endInput.value = '';
                selectingEnd = true;
                calendarInstruction.textContent = 'Sākums: ' + showDate(date) + '. Tagad izvēlieties atgriešanas datumu.';
            } else {
                endInput.value = date;
                selectingEnd = false;
                calendarInstruction.textContent = 'Izvēlēts ' + showDate(startInput.value) + ' — ' + showDate(date) + '. Pārbaudiet pieejamību.';
            }
            updateQuote();
        });
    });
}

const imageLink = document.getElementById('product-photo-link');
const imageDialog = document.getElementById('image-dialog');
const imageClose = document.getElementById('image-dialog-close');
if (imageLink && imageDialog && typeof imageDialog.showModal === 'function') {
    imageLink.addEventListener('click', event => {
        event.preventDefault();
        imageDialog.showModal();
    });
    imageClose.addEventListener('click', () => imageDialog.close());
    imageDialog.addEventListener('click', event => {
        if (event.target !== imageDialog) return;
        const box = imageDialog.getBoundingClientRect();
        if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) imageDialog.close();
    });
}
