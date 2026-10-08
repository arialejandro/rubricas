/**
 * Teclado de calificaciones tipo punto de venta.
 *
 * Cualquier botón [data-score-cell] dentro de un contenedor [data-grades-url] abre el teclado.
 * Un tap en 0–10 guarda y salta al siguiente alumno; los decimales se escriben a mano.
 * El guardado es optimista: la pantalla avanza sin esperar a la red y, si algo falla,
 * la celda queda en rojo y se reintenta al volver la conexión.
 *
 * Avance: si el contenedor tiene data-advance="column" se baja por el mismo aspecto
 * (matriz); si no, se sigue el orden del documento (captura, ficha del alumno).
 */
import { showToast, fmt } from './ui';

const pad = document.getElementById('keypad');
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const failed = new Map(); // cell → value pendiente de reintentar
let current = null;
let digitBuffer = '';
let digitTimer;

export function initKeypad() {
    if (!pad) return;

    document.addEventListener('click', (e) => {
        const cell = e.target.closest('[data-score-cell]');
        if (cell) open(cell);
    });

    pad.querySelectorAll('[data-key]').forEach((key) => key.addEventListener('click', () => commit(key.dataset.key)));
    pad.querySelector('[data-pad-clear]').addEventListener('click', () => commit(''));
    pad.querySelector('[data-pad-skip]').addEventListener('click', () => move(1));
    pad.querySelector('[data-pad-prev]').addEventListener('click', () => move(-1));
    pad.querySelector('[data-pad-close]').addEventListener('click', close);
    pad.querySelector('[data-pad-decimal]').addEventListener('click', () => toggleDecimal(true));

    const decimalForm = pad.querySelector('[data-pad-decimal-form]');
    decimalForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const input = decimalForm.querySelector('input');
        const value = input.value.trim().replace(',', '.');
        const n = Number(value);
        if (value === '' || isNaN(n) || n < 0 || n > 10 || !/^\d{1,2}(\.\d{1,2})?$/.test(value)) {
            input.setCustomValidity('Número del 0 al 10, máximo dos decimales');
            input.reportValidity();
            return;
        }
        input.setCustomValidity('');
        commit(value);
    });

    // Teclado físico (escritorio / iPad con teclado): dígitos, Esc, flechas, Supr.
    document.addEventListener('keydown', (e) => {
        if (!current || pad.hidden || e.target.closest('[data-pad-decimal-form]')) return;
        if (e.target.matches('input, textarea') && !pad.contains(e.target)) return;

        if (/^[0-9]$/.test(e.key)) {
            e.preventDefault();
            typeDigit(e.key);
        } else if (e.key === 'Escape') {
            close();
        } else if (e.key === 'ArrowDown' || e.key === 'ArrowRight') {
            e.preventDefault();
            move(1);
        } else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') {
            e.preventDefault();
            move(-1);
        } else if (e.key === 'Delete' || e.key === 'Backspace') {
            e.preventDefault();
            commit('');
        } else if (e.key === '.' || e.key === ',') {
            e.preventDefault();
            toggleDecimal(true);
        }
    });

    window.addEventListener('online', () => failed.forEach((value, cell) => save(cell, value)));
    window.addEventListener('beforeunload', (e) => {
        if (failed.size > 0 || document.querySelector('[data-score-cell].is-saving')) e.preventDefault();
    });
}

/** Teclado físico: "1" espera un instante por si sigue "0" (= 10); cualquier otro dígito guarda al momento. */
function typeDigit(d) {
    if (digitBuffer === '1') {
        clearTimeout(digitTimer);
        digitBuffer = '';
        if (d === '0') return commit('10');
        commit('1');
    }
    if (d === '1') {
        digitBuffer = '1';
        digitTimer = setTimeout(() => {
            digitBuffer = '';
            commit('1');
        }, 450);
        return;
    }
    commit(d);
}

export function open(cell) {
    current?.classList.remove('is-active');
    current = cell;
    cell.classList.add('is-active');

    pad.querySelector('[data-pad-student]').textContent = cell.dataset.studentName;
    pad.querySelector('[data-pad-criterion]').textContent = cell.dataset.criterionName;
    pad.querySelector('[data-pad-current]').textContent = cell.dataset.value === '' ? 'Sin calificar' : `Actual: ${fmt(cell.dataset.value)}`;
    pad.querySelectorAll('[data-key]').forEach((k) => k.setAttribute('aria-pressed', String(k.dataset.key === cell.dataset.value)));
    toggleDecimal(false);

    pad.hidden = false;
    document.body.classList.add('keypad-open');
    cell.scrollIntoView({ block: 'center', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
}

export function close() {
    current?.classList.remove('is-active');
    current = null;
    pad.hidden = true;
    document.body.classList.remove('keypad-open');
}

function toggleDecimal(show) {
    const form = pad.querySelector('[data-pad-decimal-form]');
    form.hidden = !show;
    pad.querySelector('[data-pad-grid]').hidden = show;
    if (show) {
        const input = form.querySelector('input');
        input.value = current?.dataset.value ?? '';
        input.focus();
        input.select();
    }
}

function sequence(cell) {
    const container = cell.closest('[data-grades-url]');
    let cells = [...container.querySelectorAll('[data-score-cell]')];
    if (container.dataset.advance === 'column') {
        cells = cells.filter((c) => c.dataset.criterion === cell.dataset.criterion);
    }
    return cells.filter((c) => c.offsetParent !== null);
}

function move(step) {
    if (!current) return;
    const cells = sequence(current);
    const next = cells[cells.indexOf(current) + step];
    next ? open(next) : close();
}

function commit(value) {
    if (!current) return;
    const cell = current;
    setCell(cell, value, 'is-saving');
    move(1);
    save(cell, value);
}

function setCell(cell, value, state = null) {
    cell.dataset.value = value === null ? '' : String(value);
    cell.textContent = cell.dataset.value === '' ? '—' : fmt(cell.dataset.value);
    cell.toggleAttribute('data-empty', cell.dataset.value === '');
    cell.classList.remove('is-saving', 'is-error');
    if (state) cell.classList.add(state);
    const row = cell.closest('[data-search-item]');
    if (row && row.dataset.doneBy === 'cell') row.dataset.done = cell.dataset.value === '' ? '0' : '1';
}

async function save(cell, value) {
    const container = cell.closest('[data-grades-url]');
    try {
        const res = await fetch(container.dataset.gradesUrl, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ student_id: cell.dataset.student, criterion_id: cell.dataset.criterion, score: value }),
        });
        const data = await res.json().catch(() => ({}));
        if (res.status === 419 || res.status === 401) throw new Error('Tu sesión expiró. Recarga la página.');
        if (!res.ok) throw new Error(Object.values(data.errors ?? {})[0]?.[0] ?? data.message ?? 'No se pudo guardar');

        failed.delete(cell);
        setCell(cell, data.score);
        paint(cell, data);
    } catch (e) {
        failed.set(cell, value);
        cell.classList.remove('is-saving');
        cell.classList.add('is-error');
        showToast(e instanceof TypeError ? 'Sin conexión: se guardará al volver la red.' : e.message);
    }
}

/** Refresca totales que dependen de la celda. Los selectores llevan proyecto:alumno. */
function paint(cell, data) {
    const key = `${data.project.id}:${cell.dataset.student}`;
    const set = (sel, fn) => document.querySelectorAll(sel).forEach(fn);

    set(`[data-percent="${key}"]`, (el) => (el.textContent = `${fmt(data.student.percent)}%`));
    set(`[data-final="${key}"]`, (el) => {
        el.textContent = fmt(data.student.final);
        el.classList.toggle('opacity-40', !data.student.complete);
    });
    set(`[data-status="${key}"]`, (el) => {
        el.textContent = data.student.complete ? 'Completo' : `Faltan ${data.student.missing}`;
        el.classList.toggle('chip-done', data.student.complete);
        el.classList.toggle('chip-pending', !data.student.complete);
    });
    set(`[data-row-done="${key}"]`, (el) => (el.dataset.done = data.student.complete ? '1' : '0'));

    const c = cell.dataset.criterion;
    const cDone = data.criterion_total - data.criterion_missing;
    set(`[data-criterion-missing="${c}"]`, (el) => (el.textContent = data.criterion_missing === 0 ? 'Completo' : `${data.criterion_missing} sin calificar`));
    set(`[data-criterion-count="${c}"]`, (el) => (el.textContent = `${cDone}/${data.criterion_total}`));
    set(`[data-criterion-bar="${c}"]`, (el) => (el.style.width = `${data.criterion_total ? (cDone * 100) / data.criterion_total : 0}%`));

    const p = data.project.id;
    set(`[data-project-graded="${p}"]`, (el) => (el.textContent = data.project.graded));
    set(`[data-project-missing="${p}"]`, (el) => (el.textContent = data.project.missing));
    set(`[data-project-progress="${p}"]`, (el) => (el.textContent = `${data.project.progress}%`));
    set(`[data-project-bar="${p}"]`, (el) => (el.style.width = `${data.project.progress}%`));

    document.dispatchEvent(new CustomEvent('grades:changed'));
}
