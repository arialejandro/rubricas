/**
 * Teclado de calificaciones tipo punto de venta.
 *
 * Cualquier botón [data-score-cell] dentro de un contenedor [data-score-url] abre el teclado.
 * Un tap guarda y salta al siguiente alumno. El guardado es optimista: la pantalla avanza sin
 * esperar a la red y, si algo falla, la celda queda en rojo y se reintenta al volver la conexión.
 *
 * Modo según la celda (data-mode): "score" = 0–10 (decimales a mano); "levels" = criterio de
 * proyecto por nivel de logro, con el descriptor de la rúbrica (data-descriptors del contenedor).
 *
 * Avance: con data-advance="column" en el contenedor se baja por la misma columna (matriz);
 * si no, se sigue el orden del documento (captura, ficha del alumno).
 *
 * El servidor responde qué repintar (TermBook::paint): textos, niveles, barras y filas completas,
 * por clave → elementos con data-paint="clave" / data-bar="clave" / data-paint-done="clave".
 */
import { showToast, fmt, levelOf, LEVEL_LABELS } from './ui';

const pad = document.getElementById('keypad');
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const desktop = matchMedia('(min-width: 768px)');
const failed = new Map(); // celda → { value, measure } pendiente de reintentar
let current = null;
let digitBuffer = '';
let digitTimer;
let measureBuffer = ''; // lo que se va tecleando en modo medición
let config = {}; // de la celda abierta: descriptors, labels, unit, lower, thresholds

export function initKeypad() {
    if (!pad) return;

    document.addEventListener('click', (e) => {
        const cell = e.target.closest('[data-score-cell]');
        if (cell) return open(cell);
        // Tocar fuera del teclado lo cierra (como cualquier pop-up).
        if (!pad.hidden && !pad.contains(e.target)) close();
    });

    // El pop-up sigue a su casilla al hacer scroll (página o tabla) o al girar el iPad.
    let frame;
    const follow = () => {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(place);
    };
    window.addEventListener('scroll', follow, { capture: true, passive: true });
    window.addEventListener('resize', follow);

    pad.querySelectorAll('[data-key]').forEach((key) => key.addEventListener('click', () => commit(key.dataset.key)));
    pad.querySelectorAll('[data-digit]').forEach((key) => key.addEventListener('click', () => typeMeasure(key.dataset.digit)));
    pad.querySelector('[data-measure-back]').addEventListener('click', () => typeMeasure('back'));
    pad.querySelector('[data-measure-ok]').addEventListener('click', commitMeasure);
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

        // Modo medición: los dígitos arman el número; Retroceso borra un dígito; Enter guarda.
        if (current.dataset.mode === 'measure') {
            if (/^[0-9.]$/.test(e.key) || e.key === ',') {
                e.preventDefault();
                typeMeasure(e.key === ',' ? '.' : e.key);
                return;
            }
            if (e.key === 'Backspace') {
                e.preventDefault();
                typeMeasure('back');
                return;
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                commitMeasure();
                return;
            }
        }

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
        } else if ((e.key === '.' || e.key === ',') && current.dataset.mode === 'score') {
            e.preventDefault();
            toggleDecimal(true);
        }
    });

    window.addEventListener('online', () => failed.forEach(({ value, measure }, cell) => save(cell, value, measure)));
    window.addEventListener('beforeunload', (e) => {
        if (failed.size > 0 || document.querySelector('[data-score-cell].is-saving')) e.preventDefault();
    });
}

/** Teclado físico: "1" espera un instante por si sigue "0" (= 10). En modo niveles solo 6–10. */
function typeDigit(d) {
    const levels = current.dataset.mode === 'levels';
    if (digitBuffer === '1') {
        clearTimeout(digitTimer);
        digitBuffer = '';
        if (d === '0') return commit('10');
        if (!levels) commit('1');
    }
    if (d === '1') {
        digitBuffer = '1';
        digitTimer = setTimeout(() => {
            digitBuffer = '';
            if (!levels) commit('1');
        }, 450);
        return;
    }
    if (levels && !['6', '7', '8', '9'].includes(d)) return;
    commit(d);
}

export function open(cell) {
    current?.classList.remove('is-active');
    current = cell;
    cell.classList.add('is-active');

    const mode = cell.dataset.mode;
    config = readConfig(cell);
    const label = (v) => (config.labels ?? {})[levelOf(v)] ?? LEVEL_LABELS[levelOf(v)];
    pad.querySelector('[data-pad-student]').textContent = cell.dataset.studentName;
    pad.querySelector('[data-pad-unit]').textContent = cell.dataset.unitName;
    pad.querySelector('[data-pad-current]').textContent =
        cell.dataset.value === ''
            ? 'Sin calificar'
            : mode === 'measure'
              ? `Actual: ${fmt(cell.dataset.measure || cell.dataset.value)} → ${fmt(cell.dataset.value)}`
              : `Actual: ${fmt(cell.dataset.value)}${mode === 'levels' ? ' · ' + label(cell.dataset.value) : ''}`;
    pad.querySelectorAll('[data-key]').forEach((k) => k.setAttribute('aria-pressed', String(k.dataset.key === cell.dataset.value)));

    pad.querySelector('[data-pad-levels]').hidden = mode !== 'levels';
    pad.querySelector('[data-pad-measure]').hidden = mode !== 'measure';
    pad.querySelector('[data-pad-grid]').hidden = mode !== 'score';
    pad.querySelector('[data-pad-decimal-form]').hidden = true;
    if (mode === 'levels') fillDescriptors();
    if (mode === 'measure') {
        measureBuffer = cell.dataset.measure || '';
        renderMeasure();
    }

    if (pad.hidden) {
        pad.hidden = false;
        pad.classList.add('is-entering');
        pad.firstElementChild.addEventListener('animationend', () => pad.classList.remove('is-entering'), { once: true });
    }
    document.body.classList.add('keypad-open');

    // En escritorio solo se desplaza si la casilla no se ve; en teléfono se centra arriba de la hoja.
    const smooth = matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
    cell.scrollIntoView({ block: desktop.matches ? 'nearest' : 'center', inline: 'nearest', behavior: smooth });
    place();
}

/**
 * Configuración de la celda desde data-descriptors del contenedor (por id):
 * criterio → { nivel: descriptor }; habilidad → { descriptors, labels, unit, lower, thresholds }.
 */
function readConfig(cell) {
    let map = {};
    try {
        map = JSON.parse(cell.closest('[data-score-url]').dataset.descriptors || '{}');
    } catch (e) {
        map = {};
    }
    const raw = map[cell.dataset.id] || {};
    return raw.descriptors || raw.labels || raw.thresholds ? raw : { descriptors: raw };
}

/** Descriptor (y nombre propio, si lo hay) debajo de cada nivel. */
function fillDescriptors() {
    const d = config.descriptors || {};
    pad.querySelectorAll('[data-pad-descriptor]').forEach((el) => {
        el.textContent = d[el.dataset.padDescriptor] || '';
        el.hidden = !el.textContent;
    });
    pad.querySelectorAll('[data-pad-label]').forEach((el) => {
        el.textContent = (config.labels ?? {})[el.dataset.padLabel] ?? LEVEL_LABELS[el.dataset.padLabel];
    });
}

/** Calificación que corresponde a una medición (misma regla que Skill::scoreFor). */
function scoreForMeasure(n) {
    const t = config.thresholds || {};
    for (const score of ['10', '9', '8', '7']) {
        const limit = t[score];
        if (limit !== null && limit !== undefined && (config.lower ? n <= limit : n >= limit)) return Number(score);
    }
    return 6;
}

function typeMeasure(key) {
    if (key === 'back') {
        measureBuffer = measureBuffer.slice(0, -1);
    } else if (key === '.') {
        if (!measureBuffer.includes('.')) measureBuffer = (measureBuffer || '0') + '.';
    } else if (measureBuffer.replace('.', '').length < 6) {
        measureBuffer = measureBuffer === '0' ? key : measureBuffer + key;
    }
    renderMeasure();
}

function renderMeasure() {
    const preview = pad.querySelector('[data-measure-preview]');
    const n = Number(measureBuffer);
    pad.querySelector('[data-measure-display]').textContent = measureBuffer === '' ? '—' : measureBuffer;
    pad.querySelector('[data-measure-unit]').textContent = config.unit || '';
    pad.querySelector('[data-measure-ok]').disabled = measureBuffer === '' || isNaN(n);
    if (measureBuffer === '' || isNaN(n)) {
        preview.removeAttribute('data-level');
        pad.querySelector('[data-measure-result]').textContent = 'Escribe la medición';
        return;
    }
    const score = scoreForMeasure(n);
    const lvl = levelOf(score);
    preview.dataset.level = lvl;
    pad.querySelector('[data-measure-result]').textContent = `${score} · ${(config.labels ?? {})[lvl] ?? LEVEL_LABELS[lvl]}`;
}

function commitMeasure() {
    if (!current || measureBuffer === '' || isNaN(Number(measureBuffer))) return;
    const measure = String(Number(measureBuffer));
    commit(String(scoreForMeasure(Number(measure))), measure);
}

export function close() {
    current?.classList.remove('is-active');
    current = null;
    pad.hidden = true;
    document.body.classList.remove('keypad-open');
}

/** Pop-up junto a la casilla: a su derecha si cabe, si no a su izquierda, si no debajo/encima. */
function place() {
    if (!current || pad.hidden) return;
    if (!desktop.matches) {
        pad.style.top = pad.style.left = '';
        return;
    }

    const r = current.getBoundingClientRect();
    const w = pad.offsetWidth;
    const h = pad.offsetHeight;
    const gap = 12;
    const margin = 12;
    const minTop = (document.querySelector('header')?.getBoundingClientRect().bottom ?? 0) + margin;
    const clampTop = (t) => Math.min(Math.max(t, minTop), innerHeight - h - margin);
    let left;
    let top;
    let origin;

    if (r.right + gap + w <= innerWidth - margin) {
        [left, top, origin] = [r.right + gap, clampTop(r.top + r.height / 2 - h / 2), 'left center'];
    } else if (r.left - gap - w >= margin) {
        [left, top, origin] = [r.left - gap - w, clampTop(r.top + r.height / 2 - h / 2), 'right center'];
    } else {
        left = Math.min(Math.max(r.left + r.width / 2 - w / 2, margin), innerWidth - w - margin);
        const below = r.bottom + gap + h <= innerHeight - margin;
        [top, origin] = below ? [r.bottom + gap, 'top center'] : [clampTop(r.top - gap - h), 'bottom center'];
    }

    pad.style.left = `${Math.round(left)}px`;
    pad.style.top = `${Math.round(top)}px`;
    pad.style.setProperty('--pad-origin', origin);
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
    place();
}

function sequence(cell) {
    const container = cell.closest('[data-score-url]');
    let cells = [...container.querySelectorAll('[data-score-cell]')];
    if (container.dataset.advance === 'column') {
        cells = cells.filter((c) => c.dataset.kind === cell.dataset.kind && c.dataset.id === cell.dataset.id);
    }
    return cells.filter((c) => c.offsetParent !== null);
}

function move(step) {
    if (!current) return;
    const cells = sequence(current);
    const next = cells[cells.indexOf(current) + step];
    next ? open(next) : close();
}

/** measure: solo en habilidades medibles (el servidor recalcula la calificación con sus rangos). */
function commit(value, measure = null) {
    if (!current) return;
    const cell = current;
    setCell(cell, value, 'is-saving', measure);
    move(1);
    save(cell, value, measure);
}

function setCell(cell, value, state = null, measure = null) {
    cell.dataset.value = value === null ? '' : String(value);
    const empty = cell.dataset.value === '';
    if (cell.dataset.mode === 'measure') {
        cell.dataset.measure = empty || measure === null ? '' : String(measure);
        cell.textContent = empty ? '—' : fmt(cell.dataset.measure || cell.dataset.value);
        cell.title = empty ? '' : `Calificación ${fmt(cell.dataset.value)}`;
    } else {
        cell.textContent = empty ? '—' : fmt(cell.dataset.value);
    }
    cell.toggleAttribute('data-empty', empty);
    empty ? cell.removeAttribute('data-level') : (cell.dataset.level = levelOf(cell.dataset.value));
    cell.classList.remove('is-saving', 'is-error');
    if (state) cell.classList.add(state);
    const row = cell.closest('[data-search-item]');
    if (row && row.dataset.doneBy === 'cell') row.dataset.done = empty ? '0' : '1';
}

async function save(cell, value, measure = null) {
    const container = cell.closest('[data-score-url]');
    const measured = cell.dataset.mode === 'measure';
    try {
        const res = await fetch(container.dataset.scoreUrl, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({
                kind: cell.dataset.kind,
                id: cell.dataset.id,
                term: cell.dataset.term,
                student_id: cell.dataset.student,
                // Medible: se manda la medición (vacía = borrar); el servidor calcula la calificación.
                ...(measured ? { measure: value === '' ? '' : measure } : { score: value }),
            }),
        });
        const data = await res.json().catch(() => ({}));
        if (res.status === 419 || res.status === 401) throw new Error('Tu sesión expiró. Recarga la página.');
        if (!res.ok) throw new Error(Object.values(data.errors ?? {})[0]?.[0] ?? data.message ?? 'No se pudo guardar');

        failed.delete(cell);
        setCell(cell, data.score, null, data.measure ?? null);
        paint(data.paint);
    } catch (e) {
        failed.set(cell, { value, measure });
        cell.classList.remove('is-saving');
        cell.classList.add('is-error');
        showToast(e instanceof TypeError ? 'Sin conexión: se guardará al volver la red.' : e.message);
    }
}

function paint({ text = {}, level = {}, bar = {}, done = {} } = {}) {
    const each = (attr, key, fn) => document.querySelectorAll(`[${attr}="${CSS.escape(key)}"]`).forEach(fn);

    Object.entries(text).forEach(([k, v]) => each('data-paint', k, (el) => (el.textContent = v)));
    Object.entries(level).forEach(([k, v]) =>
        each('data-paint', k, (el) => (v ? (el.dataset.level = v) : el.removeAttribute('data-level'))),
    );
    Object.entries(bar).forEach(([k, v]) => each('data-bar', k, (el) => (el.style.width = `${v}%`)));
    Object.entries(done).forEach(([k, v]) => each('data-paint-done', k, (el) => (el.dataset.done = v ? '1' : '0')));

    document.dispatchEvent(new CustomEvent('grades:changed'));
}
