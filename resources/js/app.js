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

// Habilidad: "Elijo el nivel" / "Mido y se calcula" (campos/_skill-row). Delegado: sirve para renglones nuevos.
// En modo nivel los campos de medición se deshabilitan para que no se envíen (sin unidad = por nivel).
const syncSkillRow = (row) => {
    const measured = row.querySelector('[data-skill-mode]:checked')?.value === 'measure';
    const box = row.querySelector('[data-skill-measure]');
    box.hidden = !measured;
    box.querySelectorAll('input').forEach((el) => (el.disabled = !measured));
    const lower = row.querySelector('[data-skill-lower]')?.checked;
    row.querySelectorAll('[data-skill-cut-word]').forEach((el) => (el.textContent = lower ? 'Hasta' : 'Desde'));
    const hint = row.querySelector('[data-skill-cut-hint]');
    if (hint) hint.textContent = lower ? '(hasta cuánto vale cada calificación)' : '(desde cuánto vale cada calificación)';
};
document.querySelectorAll('[data-skill-row]').forEach(syncSkillRow);
document.addEventListener('change', (e) => {
    if (e.target.matches('[data-skill-mode], [data-skill-lower]')) syncSkillRow(e.target.closest('[data-skill-row]'));
});

// Cargar lista con "Reemplazar lista": se confirma antes, porque borra a los alumnos actuales.
document.querySelectorAll('form[data-replace-guard]').forEach((form) => {
    form.addEventListener('submit', (e) => {
        if (form.querySelector('[name="replace"]:checked')?.value !== '1') return;
        if (!confirm(form.dataset.replaceGuard)) {
            e.preventDefault();
            const file = form.querySelector('[data-autosubmit]');
            if (file) {
                file.value = '';
                form.querySelector('[data-file-name]')?.replaceChildren('Subir Excel');
            }
        }
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

/*
 * Renglones repetibles (aspectos del campo, materias, PDA, criterios de un producto):
 *   <section data-repeater data-max="6">
 *     <div data-repeater-list> …renglones [data-repeater-row]… </div>
 *     <template> renglón con __INDEX__ </template>
 *     <button data-repeater-add>   <button data-repeater-remove> (dentro del renglón)
 *   Si hay [data-weight] + [data-weight-total]/[data-weight-bar], suma los % en vivo (deben dar 100).
 */
document.querySelectorAll('[data-repeater]').forEach((editor) => {
    const list = editor.querySelector('[data-repeater-list]');
    const template = editor.querySelector(':scope > template');
    const add = editor.querySelector('[data-repeater-add]');
    const max = Number(editor.dataset.max || 99);
    const total = editor.querySelector('[data-weight-total]');
    const bar = editor.querySelector('[data-weight-bar]');
    let index = list.querySelectorAll('[data-repeater-row]').length + 100;

    const refresh = () => {
        const rows = list.querySelectorAll('[data-repeater-row]').length;
        if (add) add.disabled = rows >= max;
        if (!total) return;
        const sum = [...list.querySelectorAll('[data-weight]')].reduce((acc, el) => acc + (Number(normalize(el.value).replace(',', '.')) || 0), 0);
        const rounded = Math.round(sum * 100) / 100;
        total.textContent = `${fmt(rounded)}%`;
        total.classList.toggle('text-done', rounded === 100);
        total.classList.toggle('text-pending', rounded !== 100);
        if (bar) {
            bar.style.width = `${Math.min(rounded, 100)}%`;
            bar.classList.toggle('bg-done', rounded === 100);
            bar.classList.toggle('bg-pending', rounded !== 100);
        }
    };

    // Repartir 100% en partes iguales.
    editor.querySelector('[data-split-even]')?.addEventListener('click', () => {
        const inputs = [...list.querySelectorAll('[data-weight]')];
        if (!inputs.length) return;
        const each = Math.floor((100 / inputs.length) * 100) / 100;
        inputs.forEach((el, i) => (el.value = i === inputs.length - 1 ? fmt(100 - each * (inputs.length - 1)) : fmt(each)));
        refresh();
    });

    add?.addEventListener('click', () => {
        list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', index++));
        list.lastElementChild.querySelector('input:not([type=hidden]), textarea')?.focus();
        refresh();
    });
    list.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-repeater-remove]');
        if (!btn) return;
        btn.closest('[data-repeater-row]').remove();
        refresh();
    });
    list.addEventListener('input', refresh);
    refresh();
});
