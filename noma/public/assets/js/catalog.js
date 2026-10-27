'use strict';

// Progressive enhancement: PHP remains responsible for filtering and validation.
const filterForm = document.getElementById('catalog-filters');
const picker = document.getElementById('category-picker');
const categories = [...document.querySelectorAll('input[name="categories[]"]')];
if (picker) {
    picker.disabled = false;
    picker.addEventListener('change', () => {
        categories.forEach(input => { input.checked = input.value === picker.value; });
    });
    categories.forEach(input => input.addEventListener('change', () => {
        const selected = categories.filter(item => item.checked);
        picker.value = selected.length === 1 ? selected[0].value : (selected.length ? 'multiple' : '');
    }));
}

const slider = document.getElementById('price-slider');
const maxPrice = document.getElementById('max_price');
const minPrice = document.getElementById('min_price');
const caption = document.getElementById('price-caption');
const money = new Intl.NumberFormat('lv-LV', { style: 'currency', currency: 'EUR', maximumFractionDigits: 2 });
if (slider && maxPrice) {
    slider.disabled = false;
    slider.addEventListener('input', () => {
        maxPrice.value = slider.value;
        caption.textContent = money.format(Number(slider.value));
        validatePrices();
    });
    maxPrice.addEventListener('input', () => {
        const value = maxPrice.valueAsNumber;
        if (Number.isFinite(value) && value >= 0 && value <= 999999.99) {
            slider.max = String(Math.max(50, Math.ceil(value)));
            slider.value = String(value);
            caption.textContent = money.format(value);
        } else if (maxPrice.value === '') {
            slider.value = slider.max;
            caption.textContent = 'Bez ierobežojuma';
        }
        validatePrices();
    });
    minPrice.addEventListener('input', validatePrices);
}
function validatePrices() {
    const wrongOrder = minPrice.value !== '' && maxPrice.value !== '' && minPrice.valueAsNumber > maxPrice.valueAsNumber;
    maxPrice.setCustomValidity(wrongOrder ? 'Maksimālajai cenai jābūt vismaz minimālās cenas apmērā.' : '');
}

const startDate = document.getElementById('start_date');
const endDate = document.getElementById('end_date');
function validateDates() {
    const oneDate = Boolean(startDate.value) !== Boolean(endDate.value);
    startDate.setCustomValidity(oneDate && !startDate.value ? 'Norādiet nomas sākuma datumu.' : '');
    endDate.setCustomValidity(oneDate && !endDate.value
        ? 'Norādiet nomas beigu datumu.'
        : (startDate.value && endDate.value && endDate.value <= startDate.value
            ? 'Nomas beigām jābūt pēc sākuma datuma.' : ''));
}
startDate?.addEventListener('input', validateDates);
endDate?.addEventListener('input', validateDates);
document.getElementById('sort')?.addEventListener('change', () => filterForm.requestSubmit());

// A regular GET link opens the quick view, so it also works without JavaScript.
const quickView = document.getElementById('quick-view');
const closeLink = document.getElementById('quick-view-close');
if (quickView && typeof quickView.showModal === 'function') {
    quickView.removeAttribute('open');
    quickView.showModal();
    quickView.addEventListener('cancel', event => {
        event.preventDefault();
        window.location.assign(closeLink.href);
    });
    quickView.addEventListener('click', event => {
        if (event.target !== quickView) return;
        const box = quickView.getBoundingClientRect();
        if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) {
            window.location.assign(closeLink.href);
        }
    });
}
