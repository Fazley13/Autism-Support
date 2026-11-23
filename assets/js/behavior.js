(function () {
  // Intensity slider label
  var r = document.getElementById('intensity');
  if (r) {
    var labels = JSON.parse(r.getAttribute('data-labels') || '[]'), t = document.querySelector(r.getAttribute('data-label-target'));
    var upd = function () { if (t) t.textContent = r.value + ' – ' + labels[r.value]; };
    r.addEventListener('input', upd); upd();
  }
  if (!window.Chart || !window.PAGE) return;
  var cs = getComputedStyle(document.documentElement), c = function (n) { return cs.getPropertyValue(n).trim(); };
  var palette = [c('--primary'), c('--accent'), c('--info'), c('--success'), c('--warning'), c('--danger'), '#8e7cc3'];
  Chart.defaults.color = c('--muted'); Chart.defaults.font.family = c('--font');
  var K = Object.keys, V = Object.values;
  function mk(id, type, data, opts) {
    var el = document.getElementById(id); if (!el) return;
    new Chart(el, { type: type, data: data, options: Object.assign({ responsive: true, maintainAspectRatio: false, plugins: { legend: { display: type === 'doughnut' } } }, opts || {}) });
  }
  var yInt = { y: { beginAtZero: true, ticks: { precision: 0 } } };
  var P = window.PAGE;
  if (P.kind === 'analysis') {
    mk('chSeries', 'line', { labels: K(P.series), datasets: [{ data: V(P.series), borderColor: c('--primary'), backgroundColor: c('--primary-50'), fill: true, tension: .3, pointRadius: 2 }] }, { scales: yInt });
    mk('chType', 'doughnut', { labels: K(P.byType), datasets: [{ data: V(P.byType), backgroundColor: palette, borderWidth: 0 }] });
    mk('chInt', 'bar', { labels: K(P.avgByType), datasets: [{ data: V(P.avgByType), backgroundColor: c('--accent'), borderRadius: 6 }] }, { scales: { y: { beginAtZero: true, max: 5 } } });
  }
  if (P.kind === 'patterns') {
    mk('chHour', 'bar', { labels: K(P.hours).map(function (h) { return h + ':00'; }), datasets: [{ data: V(P.hours), backgroundColor: c('--primary'), borderRadius: 4 }] }, { scales: yInt });
    mk('chDay', 'bar', { labels: K(P.wd), datasets: [{ data: V(P.wd), backgroundColor: c('--info'), borderRadius: 6 }] }, { scales: yInt });
  }
})();
