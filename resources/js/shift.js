/**
 * Selector de turno: al tocar el otro turno se pinta su cielo, el sol sale o se oculta,
 * y al terminar la animación se navega a ese grupo. Con "reducir movimiento" navega directo.
 */
export function initShiftSwitch() {
    const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;

    document.querySelectorAll('[data-shift-link]').forEach((link) => {
        link.addEventListener('click', (e) => {
            if (link.classList.contains('is-active') || reduce || e.metaKey || e.ctrlKey) return;
            e.preventDefault();

            link.parentElement.querySelectorAll('[data-shift-link]').forEach((el) => {
                el.classList.remove('is-active', 'is-switching');
                el.removeAttribute('aria-current');
            });
            link.classList.add('is-active', 'is-switching');
            link.setAttribute('aria-current', 'true');

            setTimeout(() => (window.location.href = link.href), 650);
        });
    });
}
