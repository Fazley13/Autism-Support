(function () {
  document.addEventListener('change', function (e) {
    var cb = e.target.closest('.task-check');
    if (!cb) return;
    var row = cb.closest('.task');
    PC.api(location.pathname, { action: 'toggle', task_id: +row.getAttribute('data-task') }).then(function (r) {
      if (!r.success) { cb.checked = !cb.checked; return PC.toast(r.message || 'Could not update.', 'error'); }
      row.classList.toggle('done', r.status === 'Completed');
      PC.toast(r.status === 'Completed' ? 'Nice work! Task completed.' : 'Task reopened.');
      setTimeout(function () { location.reload(); }, 700);
    });
  });
})();
