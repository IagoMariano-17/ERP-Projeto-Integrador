<!-- ========================= -->
<!-- /includes/footer.php -->
<!-- ========================= -->

<script src="<?= htmlspecialchars($assetBase ?? ($baseUrl ?? ''), ENT_QUOTES, 'UTF-8') ?>/assets/js/funcoes.js?v=3.0.3"></script>
<script>
(function () {
    const btn = document.querySelector('.mobile-menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    if (!btn || !sidebar) return;

    sidebar.addEventListener('click', function (event) {
        if (window.innerWidth <= 900 && event.target.closest('.nav-item, .guia-btn')) {
            document.body.classList.remove('menu-aberto');
            btn.setAttribute('aria-expanded', 'false');
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 900) {
            document.body.classList.remove('menu-aberto');
            btn.setAttribute('aria-expanded', 'false');
        }
    });
})();
</script>
    
</body>
</html>