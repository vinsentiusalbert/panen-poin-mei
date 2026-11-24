(() => {
    'use strict';
    const button = document.querySelector('.festive-motion-toggle');
    if (!button) return;
    let paused = false;
    try { paused = localStorage.getItem('festive-motion-paused') === 'true'; } catch {}
    const render = () => {
        document.body.classList.toggle('festive-motion-paused', paused);
        button.setAttribute('aria-pressed', String(paused));
        button.textContent = paused ? 'Putar animasi' : 'Jeda animasi';
    };
    button.addEventListener('click', () => {
        paused = !paused;
        render();
        try { localStorage.setItem('festive-motion-paused', String(paused)); } catch {}
    });
    const syncVisibility = () => document.body.classList.toggle('festive-tab-hidden', document.hidden);
    document.addEventListener('visibilitychange', syncVisibility);
    syncVisibility();
    render();
    button.hidden = false;
})();
