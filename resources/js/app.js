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

// Habilidad (campos/_skill-row): muestra el panel del modo elegido (medición o criterios).
// El servidor ignora lo del modo que no se eligió. Delegado: sirve para renglones nuevos.
const syncSkillRow = (row) => {
    const mode = row.querySelector('[data-skill-mode]:checked')?.value;
    row.querySelectorAll(':scope > [data-skill-panel]').forEach((panel) => (panel.hidden = panel.dataset.skillPanel !== mode));
    const lower = row.querySelector('[data-skill-lower]')?.checked;
    row.querySelectorAll('[data-skill-cut-word]').forEach((el) => (el.textContent = lower ? 'termina' : 'empieza'));
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
 * Renglones repetibles (aspectos, materias, habilidades y sus criterios, PDA, criterios de producto).
 * Se pueden anidar (criterios dentro de una habilidad): todo es delegado y cada editor solo
 * toca SU lista y SU plantilla.
 *   <section data-repeater data-max="6">
 *     <div data-repeater-list> …renglones [data-repeater-row]… </div>
 *     <template> renglón con __INDEX__ (o el marcador de data-placeholder del botón) </template>
 *     <button data-repeater-add [data-placeholder="__CIDX__"]>   <button data-repeater-remove> (en el renglón)
 *   Si hay [data-weight] + [data-weight-total]/[data-weight-bar], suma los % en vivo (deben dar 100).
 */
let repeaterSeq = 1000;
const own = (editor, selector) => [...editor.querySelectorAll(selector)].find((el) => el.closest('[data-repeater]') === editor);

function refreshRepeater(editor) {
    const list = own(editor, '[data-repeater-list]');
    const add = own(editor, '[data-repeater-add]');
    if (!list) return;
    const rows = [...list.children].filter((el) => el.matches('[data-repeater-row]')).length;
    if (add) add.disabled = rows >= Number(editor.dataset.max || 99);

    const total = editor.querySelector('[data-weight-total]');
    if (!total) return;
    const sum = [...editor.querySelectorAll('[data-weight]')].reduce((acc, el) => acc + (Number(normalize(el.value).replace(',', '.')) || 0), 0);
    const rounded = Math.round(sum * 100) / 100;
    total.textContent = `${fmt(rounded)}%`;
    total.classList.toggle('text-done', rounded === 100);
    total.classList.toggle('text-pending', rounded !== 100);
    const bar = editor.querySelector('[data-weight-bar]');
    if (bar) {
        bar.style.width = `${Math.min(rounded, 100)}%`;
        bar.classList.toggle('bg-done', rounded === 100);
        bar.classList.toggle('bg-pending', rounded !== 100);
    }
}

document.addEventListener('click', (e) => {
    const add = e.target.closest('[data-repeater-add]');
    if (add) {
        const editor = add.closest('[data-repeater]');
        const list = own(editor, '[data-repeater-list]');
        const template = editor.querySelector(':scope > template');
        list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll(add.dataset.placeholder || '__INDEX__', String(repeaterSeq++)));
        const row = list.lastElementChild;
        if (row.matches('[data-skill-row]')) syncSkillRow(row);
        row.querySelector('input:not([type=hidden]), textarea')?.focus();
        refreshRepeater(editor);
        return;
    }

    const remove = e.target.closest('[data-repeater-remove]');
    if (remove) {
        const editor = remove.closest('[data-repeater]');
        remove.closest('[data-repeater-row]').remove();
        refreshRepeater(editor);
        return;
    }

    // Repartir 100% en partes iguales.
    const split = e.target.closest('[data-split-even]');
    if (split) {
        const editor = split.closest('[data-repeater]');
        const inputs = [...editor.querySelectorAll('[data-weight]')];
        if (!inputs.length) return;
        const each = Math.floor((100 / inputs.length) * 100) / 100;
        inputs.forEach((el, i) => (el.value = i === inputs.length - 1 ? fmt(100 - each * (inputs.length - 1)) : fmt(each)));
        refreshRepeater(editor);
    }
});
document.addEventListener('input', (e) => {
    if (e.target.matches('[data-weight]')) refreshRepeater(e.target.closest('[data-repeater]'));
});
document.querySelectorAll('[data-repeater]').forEach(refreshRepeater);
