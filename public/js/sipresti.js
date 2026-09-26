/* SIPRESTI — interaksi UI dasar: sidebar mobile, toast, konfirmasi hapus,
   dan pencegahan double submit pada tombol prediksi. */

(function () {
    'use strict';

    // ----------------------------------------------------------- sidebar
    var sidebar = document.getElementById('sipSidebar');
    var toggle = document.getElementById('sipSidebarToggle');
    var backdrop = document.getElementById('sipBackdrop');

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('is-open');
        if (backdrop) backdrop.classList.remove('is-open');
    }

    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('is-open');
            if (backdrop) backdrop.classList.toggle('is-open');
        });
    }

    if (backdrop) backdrop.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeSidebar();
    });

    // ------------------------------------------------------------- toast
    window.SipToast = {
        show: function (message, type) {
            var container = document.getElementById('sipToastContainer');
            if (!container) return;

            var palette = {
                success: ['text-bg-success', 'bi-check-circle'],
                error: ['text-bg-danger', 'bi-exclamation-octagon'],
                warning: ['text-bg-warning', 'bi-exclamation-triangle'],
                info: ['text-bg-info', 'bi-info-circle']
            };
            var style = palette[type] || palette.success;

            var el = document.createElement('div');
            el.className = 'toast align-items-center ' + style[0] + ' border-0';
            el.setAttribute('role', 'alert');
            el.setAttribute('aria-live', 'assertive');
            el.setAttribute('aria-atomic', 'true');
            el.innerHTML =
                '<div class="d-flex">' +
                '<div class="toast-body"><i class="bi ' + style[1] + ' me-2"></i><span></span></div>' +
                '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Tutup"></button>' +
                '</div>';
            el.querySelector('.toast-body span').textContent = message;

            container.appendChild(el);
            var toast = new bootstrap.Toast(el, { delay: 4500 });
            toast.show();
            el.addEventListener('hidden.bs.toast', function () { el.remove(); });
        }
    };

    // ------------------------------------------- konfirmasi aksi merusak
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.matches('form[data-confirm]')) return;

        if (!window.confirm(form.getAttribute('data-confirm'))) {
            e.preventDefault();
            return;
        }

        var btn = form.querySelector('[type="submit"]');
        if (btn) btn.disabled = true;
    });

    // ------------------------------------- cegah double submit prediksi
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.matches('form[data-loading]')) return;
        if (form.hasAttribute('data-confirm')) return;

        var btn = form.querySelector('[type="submit"]');
        if (!btn || btn.disabled) return;

        btn.disabled = true;
        btn.dataset.originalHtml = btn.innerHTML;
        btn.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' +
            (form.getAttribute('data-loading') || 'Memproses...');
    });

    // -------------------------------------- auto-submit filter on change
    document.querySelectorAll('[data-auto-submit]').forEach(function (el) {
        el.addEventListener('change', function () {
            if (el.form) el.form.submit();
        });
    });
})();
