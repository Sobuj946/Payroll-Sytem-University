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
