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

/** "José Ñúñez" → "jose nunez": búsqueda sin acentos ni mayúsculas. */
export function normalize(text) {
    return String(text).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
}
