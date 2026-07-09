    </div><!-- /.content -->
</div><!-- /.main -->
</div><!-- /.app-shell -->
<script>
(function () {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const shell = document.querySelector('.app-shell');

    if (!toggle || !sidebar || !shell) return;

    toggle.addEventListener('click', function () {
        const isCollapsed = shell.classList.toggle('sidebar-collapsed');
        sidebar.style.display = isCollapsed ? 'none' : '';
        toggle.setAttribute('aria-expanded', String(!isCollapsed));
        toggle.classList.toggle('active', isCollapsed);
    });
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="/uiri-ims/assets/js/dashboard.js"></script>
