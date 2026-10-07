// Runs before the page is drawn, so the saved colour theme never flashes.
(function () {
    try {
        var theme = localStorage.getItem('pms.theme') || 'blue';
        var mode = localStorage.getItem('pms.mode') || 'light';
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-bs-theme', mode);
    } catch (e) { /* storage blocked: keep the default look */ }
})();
