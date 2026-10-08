import '@fontsource/fira-sans/400.css';
import '@fontsource/fira-sans/500.css';
import '@fontsource/fira-sans/600.css';
import '@fontsource/fira-sans/700.css';

import { initKeypad } from './keypad';
import { initSearch } from './search';
import { initShiftSwitch } from './shift';
import { normalize, fmt } from './ui';

initKeypad();
initSearch();
initShiftSwitch();

// Modo claro/oscuro. El tema inicial lo aplica un <script> en el <head> para no parpadear.
document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const dark = document.documentElement.classList.toggle('dark');
        try {
            localStorage.setItem('theme', dark ? 'dark' : 'light');
        } catch (e) {
            // Navegación privada: el cambio dura solo esta página.
        }
    });
});

// Formularios con confirmación: <form data-confirm="¿Seguro?">
document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (e) => {
        if (!confirm(form.dataset.confirm)) e.preventDefault();
    });
});

// Subir Excel de alumnos: al elegir el archivo se envía solo (un tap menos).
document.querySelectorAll('[data-autosubmit]').forEach((input) => {
    input.addEventListener('change', () => {
        if (!input.files.length) return;
        input.closest('form').querySelector('[data-file-name]')?.replaceChildren(`Subiendo ${input.files[0].name}…`);
        input.form.requestSubmit();
    });
});

// Editor de aspectos de la rúbrica (projects/form): agregar/quitar renglones y sumar pesos.
const criteriaEditor = document.querySelector('[data-criteria-editor]');
if (criteriaEditor) {
    const list = criteriaEditor.querySelector('[data-criteria-list]');
    const template = criteriaEditor.querySelector('template');
    const total = criteriaEditor.querySelector('[data-weight-total]');
    const bar = criteriaEditor.querySelector('[data-weight-bar]');
    let index = list.children.length;

    const recalc = () => {
        const sum = [...list.querySelectorAll('[data-weight]')].reduce((acc, el) => acc + (Number(normalize(el.value).replace(',', '.')) || 0), 0);
        const rounded = Math.round(sum * 100) / 100;
        total.textContent = `${fmt(rounded)}%`;
        total.classList.toggle('text-done', rounded === 100);
        total.classList.toggle('text-pending', rounded !== 100);
        bar.style.width = `${Math.min(rounded, 100)}%`;
        bar.classList.toggle('bg-done', rounded === 100);
        bar.classList.toggle('bg-pending', rounded !== 100);
    };

    // Repartir 100% en partes iguales entre los aspectos.
    criteriaEditor.querySelector('[data-split-even]')?.addEventListener('click', () => {
        const inputs = [...list.querySelectorAll('[data-weight]')];
        if (!inputs.length) return;
        const each = Math.floor((100 / inputs.length) * 100) / 100;
        inputs.forEach((el, i) => (el.value = i === inputs.length - 1 ? fmt(100 - each * (inputs.length - 1)) : fmt(each)));
        recalc();
    });

    criteriaEditor.querySelector('[data-add-criterion]').addEventListener('click', () => {
        list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', index++));
        list.lastElementChild.querySelector('input:not([type=hidden])')?.focus();
        recalc();
    });
    list.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-remove-criterion]');
        if (!btn) return;
        btn.closest('[data-criterion-row]').remove();
        recalc();
    });
    list.addEventListener('input', recalc);
    recalc();
}
