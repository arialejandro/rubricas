/**
 * Buscador instantáneo de alumnos + filtro "solo pendientes".
 *
 * <input data-search>              → filtra los [data-search-item] de la página.
 *   data-search-reveal              → la lista solo aparece al escribir (tablero).
 * <input type="checkbox" data-pending-toggle> → oculta los que tienen data-done="1".
 * Cada item: data-search-text="nombre" data-search-number="12".
 *
 * Número → busca por N.L. exacto; texto → cada palabra debe aparecer (apellido, nombre o ambos).
 * Enter abre el primer resultado: su teclado de calificación o su enlace.
 */
import { normalize } from './ui';
import { open as openKeypad } from './keypad';

export function initSearch() {
    const input = document.querySelector('[data-search]');
    const toggle = document.querySelector('[data-pending-toggle]');
    const items = [...document.querySelectorAll('[data-search-item]')];
    if (!items.length || (!input && !toggle)) return;

    const empty = document.querySelector('[data-search-empty]');
    const results = document.querySelector('[data-search-results]');
    items.forEach((el) => (el.dataset.searchNorm = normalize(el.dataset.searchText ?? el.textContent)));

    const apply = () => {
        const q = normalize(input?.value ?? '');
        const tokens = q.split(/\s+/).filter(Boolean);
        const pendingOnly = toggle?.checked ?? false;
        let shown = 0;

        items.forEach((el) => {
            let match = true;
            if (/^\d+$/.test(q)) {
                match = el.dataset.searchNumber === q;
            } else if (tokens.length) {
                match = tokens.every((t) => el.dataset.searchNorm.includes(t));
            }
            if (pendingOnly && el.dataset.done === '1') match = false;
            el.hidden = !match;
            if (match) shown++;
        });

        if (results && input?.hasAttribute('data-search-reveal')) results.hidden = q === '';
        if (empty) empty.hidden = shown > 0 || (input?.hasAttribute('data-search-reveal') && q === '');
    };

    input?.addEventListener('input', apply);
    toggle?.addEventListener('change', apply);
    document.addEventListener('grades:changed', () => toggle?.checked && apply());

    input?.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            input.value = '';
            apply();
        }
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const first = items.find((el) => !el.hidden);
        if (!first) return;
        const cell = first.querySelector('[data-score-cell]');
        const link = first.matches('a') ? first : first.querySelector('a');
        if (cell) {
            input.blur();
            openKeypad(cell);
        } else if (link) {
            window.location.href = link.href;
        }
    });

    apply();
}
