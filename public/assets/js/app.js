// Show flash messages as toasts.
document.querySelectorAll('.toast').forEach(function (el) {
    new bootstrap.Toast(el, { delay: 5000 }).show();
});

// Confirmation dialog for any form that has a data-confirm="..." attribute.
(function () {
    var modalEl = document.getElementById('confirmModal');
    if (!modalEl) return;

    var modal = new bootstrap.Modal(modalEl);
    var pendingForm = null;

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form.dataset || !form.dataset.confirm || form.dataset.confirmed === '1') return;

        event.preventDefault();
        pendingForm = form;
        modalEl.querySelector('.confirm-message').textContent = form.dataset.confirm;
        modal.show();
    });

    modalEl.querySelector('.confirm-ok').addEventListener('click', function () {
        if (!pendingForm) return;
        pendingForm.dataset.confirmed = '1';
        modal.hide();
        pendingForm.submit();
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        if (pendingForm && pendingForm.dataset.confirmed !== '1') pendingForm = null;
    });
})();

// Colour theme and dark mode switcher (saved in this browser only).
(function () {
    var root = document.documentElement;
    var swatches = document.querySelectorAll('[data-theme-choice]');
    var darkSwitch = document.getElementById('darkModeSwitch');

    function save(key, value) {
        try { localStorage.setItem(key, value); } catch (e) { /* ignore */ }
    }

    function markActive() {
        var current = root.getAttribute('data-theme') || 'blue';
        swatches.forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.themeChoice === current);
        });
        if (darkSwitch) darkSwitch.checked = root.getAttribute('data-bs-theme') === 'dark';
    }

    swatches.forEach(function (btn) {
        btn.addEventListener('click', function () {
            root.setAttribute('data-theme', btn.dataset.themeChoice);
            save('pms.theme', btn.dataset.themeChoice);
            markActive();
        });
    });

    if (darkSwitch) {
        darkSwitch.addEventListener('change', function () {
            var mode = darkSwitch.checked ? 'dark' : 'light';
            root.setAttribute('data-bs-theme', mode);
            save('pms.mode', mode);
        });
    }

    markActive();
})();

// ---- Behaviour that used to be written inline in pages (a strict content security policy forbids inline scripts) ----

// A select with data-auto-submit sends its form as soon as the choice changes.
document.addEventListener('change', function (event) {
    var el = event.target;
    if (el.matches && el.matches('select[data-auto-submit]') && el.form) el.form.submit();
});

document.addEventListener('click', function (event) {
    // Print button
    if (event.target.closest('[data-print]')) {
        window.print();
        return;
    }

    // Attendance sheet: "Mark everyone present"
    if (event.target.closest('[data-mark-all-present]')) {
        document.querySelectorAll('.att-status').forEach(function (select) { select.value = 'present'; });
        return;
    }

    // Leave requests: the reject dialog is shared by every row
    var reject = event.target.closest('[data-reject-url]');
    if (reject) {
        var form = document.getElementById('rejectForm');
        if (form) {
            form.action = reject.dataset.rejectUrl;
            document.getElementById('rejectWho').textContent = reject.dataset.rejectWho;
            document.getElementById('remarks').value = '';
        }
    }
});

// Payments: the box in the table heading ticks every unpaid row
document.addEventListener('change', function (event) {
    var box = event.target;
    if (box.matches && box.matches('[data-tick-all]')) {
        document.querySelectorAll('.tick-row').forEach(function (row) { row.checked = box.checked; });
    }
});

