<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
?>
<script>
(function () {
    'use strict';

    var sidebar = document.getElementById('appSidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var toggle = document.getElementById('sidebarToggle');
    if (!sidebar || !overlay || !toggle) return;

    function setOpen(open) {
        sidebar.classList.toggle('open', open);
        overlay.classList.toggle('active', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
        if (open) {
            overlay.removeAttribute('aria-hidden');
        } else {
            overlay.setAttribute('aria-hidden', 'true');
        }
    }

    toggle.addEventListener('click', function () {
        setOpen(!sidebar.classList.contains('open'));
    });
    overlay.addEventListener('click', function () {
        setOpen(false);
        toggle.focus();
    });
    sidebar.addEventListener('click', function (event) {
        var link = event.target.closest('a');
        if (link) setOpen(false);
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && sidebar.classList.contains('open')) {
            setOpen(false);
            toggle.focus();
        }
    });
    window.addEventListener('resize', function () {
        if (window.innerWidth > 992) setOpen(false);
    });
}());
</script>
