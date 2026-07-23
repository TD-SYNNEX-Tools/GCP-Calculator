/* Aplica o tema salvo antes da renderização do body para evitar "flash".
   Carregado de forma síncrona no <head> (compatível com CSP script-src 'self'). */
(function () {
    try {
        var t = localStorage.getItem('theme');
        var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (t === 'dark' || (!t && prefersDark)) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    } catch (e) { /* localStorage indisponível: mantém tema claro */ }
})();
