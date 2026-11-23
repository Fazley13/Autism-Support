(function () {
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-fb]');
    if (b) { document.getElementById('fbSession').value = b.getAttribute('data-fb'); document.getElementById('fbModal').classList.add('open'); }
  });
  if (!window.Chart || !window.PAGE) return;
  var cs = getComputedStyle(document.documentElement);
  var c = function (n) { return cs.getPropertyValue(n).trim(); };
  var palette = [c('--primary'), c('--accent'), c('--info'), c('--success'), c('--warning'), c('--danger')];
  Chart.defaults.color = c('--muted'); Chart.defaults.font.family = c('--font');
  var keys = function (o) { return Object.keys(o); }, vals = function (o) { return Object.values(o); };
  function mk(id, type, data, opts) { var el = document.getElementById(id); if (el) new Chart(el, { type: type, data: data, options: Object.assign({ responsive: true, maintainAspectRatio: false, plugins: { legend: { display: type === 'doughnut' } } }, opts || {}) }); }
  mk('chMonths', 'bar', { labels: keys(PAGE.months), datasets: [{ data: vals(PAGE.months), backgroundColor: c('--primary'), borderRadius: 6 }] }, { scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } });
  mk('chType', 'doughnut', { labels: keys(PAGE.byType), datasets: [{ data: vals(PAGE.byType), backgroundColor: palette, borderWidth: 0 }] });
  mk('chTher', 'bar', { labels: keys(PAGE.byTher), datasets: [{ data: vals(PAGE.byTher), backgroundColor: c('--accent'), borderRadius: 6 }] }, { indexAxis: 'y', scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } });
})();
