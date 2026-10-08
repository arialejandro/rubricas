/**
 * Autoguardado de calificaciones.
 *
 * Contenedor: [data-grades-url] con inputs .score-input[data-student][data-criterion].
 * Al salir de una celda (o Enter) se manda PUT; la respuesta trae los totales recalculados
 * y se pintan en los elementos marcados con data-* (ver projects/show y projects/capture).
 * Enter / flecha abajo bajan al siguiente alumno de la misma columna.
 */
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const failed = new Set();
let inFlight = 0;

function setState(input, state, message = '') {
    input.classList.remove('is-empty', 'is-saving', 'is-saved', 'is-error');
    if (state) input.classList.add(state);
    input.title = message;
}

function normalize(value) {
    return value.trim().replace(',', '.');
}

function setText(selector, text) {
    document.querySelectorAll(selector).forEach((el) => (el.textContent = text));
}

function fmt(n) {
    return Number(n).toLocaleString('es-MX', { maximumFractionDigits: 2 });
}

function paint(input, data) {
    const s = input.dataset.student;
    const c = input.dataset.criterion;

    setText(`[data-student-percent="${s}"]`, `${fmt(data.student.percent)}%`);
    document.querySelectorAll(`[data-student-final="${s}"]`).forEach((el) => {
        el.textContent = fmt(data.student.final);
        el.classList.toggle('text-slate-300', !data.student.complete);
        el.title = data.student.complete ? '' : 'Provisional: faltan aspectos por calificar';
    });
    document.querySelectorAll(`[data-student-status="${s}"]`).forEach((el) => {
        el.textContent = data.student.complete ? 'Completo' : `Faltan ${data.student.missing}`;
        el.classList.toggle('bg-emerald-100', data.student.complete);
        el.classList.toggle('text-emerald-800', data.student.complete);
        el.classList.toggle('bg-amber-100', !data.student.complete);
        el.classList.toggle('text-amber-800', !data.student.complete);
    });
    document.querySelectorAll(`[data-student-row="${s}"]`).forEach((el) => {
        el.dataset.complete = data.student.complete ? '1' : '0';
    });

    setText(`[data-criterion-missing="${c}"]`, data.criterion_missing === 0 ? '✓' : `${data.criterion_missing} sin calificar`);
    setText('[data-project-graded]', data.project.graded);
    setText('[data-project-missing]', data.project.missing);
    document.querySelectorAll('[data-project-bar]').forEach((el) => (el.style.width = `${data.project.progress}%`));
    setText('[data-project-progress]', `${data.project.progress}%`);
}

async function save(input) {
    const container = input.closest('[data-grades-url]');
    const value = normalize(input.value);

    if (value === input.dataset.saved && !failed.has(input)) {
        setState(input, value === '' ? 'is-empty' : null);
        return;
    }

    if (value !== '' && (isNaN(Number(value)) || Number(value) < 0 || Number(value) > 10)) {
        setState(input, 'is-error', 'Escribe un número del 0 al 10');
        return;
    }

    // Enter + blur disparan dos veces el mismo guardado; el segundo se descarta.
    if (input.dataset.sending === value) return;
    input.dataset.sending = value;

    setState(input, 'is-saving');
    inFlight++;
    try {
        const res = await fetch(container.dataset.gradesUrl, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ student_id: input.dataset.student, criterion_id: input.dataset.criterion, score: value }),
        });
        const data = await res.json().catch(() => ({}));

        if (res.status === 419 || res.status === 401) {
            throw new Error('Tu sesión expiró. Recarga la página.');
        }
        if (!res.ok) {
            throw new Error(Object.values(data.errors ?? {})[0]?.[0] ?? data.message ?? 'No se pudo guardar');
        }

        input.dataset.saved = data.score === null ? '' : String(data.score);
        input.value = data.score === null ? '' : fmt(data.score);
        failed.delete(input);
        setState(input, data.score === null ? 'is-empty' : 'is-saved');
        paint(input, data);
    } catch (e) {
        failed.add(input);
        setState(input, 'is-error', e.message === 'Failed to fetch' ? 'Sin conexión: se reintentará' : e.message);
        showToast(e.message === 'Failed to fetch' ? 'Sin conexión. La calificación se reintentará al volver la red.' : e.message);
    } finally {
        inFlight--;
        delete input.dataset.sending;
    }
}

function focusNext(input, step) {
    const container = input.closest('[data-grades-url]');
    const column = [...container.querySelectorAll(`.score-input[data-criterion="${input.dataset.criterion}"]`)].filter(
        (el) => el.offsetParent !== null,
    );
    const next = column[column.indexOf(input) + step];
    if (next) {
        next.focus();
        next.select();
    } else {
        input.blur();
    }
}

let toastTimer;
function showToast(message) {
    const el = document.getElementById('toast');
    if (!el) return;
    el.textContent = message;
    el.classList.remove('hidden');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.add('hidden'), 5000);
}

document.querySelectorAll('[data-grades-url] .score-input').forEach((input) => {
    input.dataset.saved = normalize(input.value);
    if (input.value === '') setState(input, 'is-empty');

    input.addEventListener('focus', () => input.select());
    input.addEventListener('change', () => save(input));
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === 'ArrowDown') {
            e.preventDefault();
            save(input);
            focusNext(input, 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            save(input);
            focusNext(input, -1);
        }
    });
});

window.addEventListener('online', () => failed.forEach((input) => save(input)));
window.addEventListener('beforeunload', (e) => {
    if (inFlight > 0 || failed.size > 0) e.preventDefault();
});

// Filtro "solo pendientes" en la matriz / captura.
document.querySelectorAll('[data-filter-pending]').forEach((toggle) => {
    toggle.addEventListener('change', () => {
        document.querySelectorAll('[data-student-row]').forEach((row) => {
            row.hidden = toggle.checked && row.dataset.complete === '1';
        });
    });
});

// En modo captura: ocultar a quien ya tiene calificación en este aspecto.
document.querySelectorAll('[data-filter-pending-criterion]').forEach((toggle) => {
    toggle.addEventListener('change', () => {
        document.querySelectorAll('[data-capture-row]').forEach((row) => {
            const input = row.querySelector('.score-input');
            row.hidden = toggle.checked && input.dataset.saved !== '' && input !== document.activeElement;
        });
    });
});

// Formularios con confirmación: <form data-confirm="¿Seguro?">
document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (e) => {
        if (!confirm(form.dataset.confirm)) e.preventDefault();
    });
});

// Editor de aspectos de la rúbrica (projects/form): agregar/quitar renglones y sumar pesos.
const criteriaEditor = document.querySelector('[data-criteria-editor]');
if (criteriaEditor) {
    const list = criteriaEditor.querySelector('[data-criteria-list]');
    const template = criteriaEditor.querySelector('template');
    const total = criteriaEditor.querySelector('[data-weight-total]');
    let index = list.children.length;

    const recalc = () => {
        const sum = [...list.querySelectorAll('[data-weight]')].reduce((acc, el) => acc + (Number(normalize(el.value)) || 0), 0);
        const rounded = Math.round(sum * 100) / 100;
        total.textContent = `${fmt(rounded)}%`;
        total.classList.toggle('text-emerald-700', rounded === 100);
        total.classList.toggle('text-red-700', rounded !== 100);
    };

    criteriaEditor.querySelector('[data-add-criterion]').addEventListener('click', () => {
        list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', index++));
        list.lastElementChild.querySelector('input')?.focus();
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
