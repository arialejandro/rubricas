let toastTimer;

export function showToast(message) {
    const el = document.getElementById('toast');
    if (!el) return;
    el.textContent = message;
    el.classList.remove('hidden');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.add('hidden'), 4500);
}

export function fmt(n) {
    return Number(n).toLocaleString('es-MX', { maximumFractionDigits: 2 });
}

/** Mismo corte que App\Support\Level::of(). */
export function levelOf(score) {
    if (score === '' || score === null || score === undefined) return null;
    const n = Number(score);
    if (n >= 9.5) return 'logrado';
    if (n >= 8.5) return 'satisfactorio';
    if (n >= 6.5) return 'proceso';
    return 'apoyo';
}

export const LEVEL_LABELS = { logrado: 'Logrado', satisfactorio: 'Satisfactorio', proceso: 'En proceso', apoyo: 'Requiere apoyo' };

/** "José Ñúñez" → "jose nunez": búsqueda sin acentos ni mayúsculas. */
export function normalize(text) {
    return String(text).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
}
