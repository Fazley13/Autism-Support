(function () {
  var P = window.PAGE, box = document.getElementById('chatMessages');
  var last = P.last || 0;

  function bubble(m, pending) {
    var d = document.createElement('div');
    d.className = 'bubble' + (m.mine ? ' mine' : ''); d.setAttribute('data-id', m.id || '');
    if (pending) d.style.opacity = '.6';
    d.innerHTML = PC.esc(m.body).replace(/\n/g, '<br>') + '<time>' + PC.esc(m.time || 'sending…') + '</time>';
    return d;
  }
  function scroll() { if (box) box.scrollTop = box.scrollHeight; }
  scroll();

  /* send */
  var form = document.getElementById('chatForm'), input = document.getElementById('chatInput');
  if (form) {
    var send = function () {
      var body = input.value.trim(); if (!body) return;
      var empty = document.getElementById('chatEmpty'); if (empty) empty.remove();
      var tmp = bubble({ mine: true, body: body }, true); box.appendChild(tmp); scroll(); input.value = '';
      PC.api(P.api, { action: 'send', to: P.with, body: body }).then(function (r) {
        if (!r.success) { tmp.remove(); input.value = body; return PC.toast(r.message || 'Could not send.', 'error'); }
        tmp.replaceWith(bubble(r.message)); last = Math.max(last, r.message.id);
      });
    };
    form.addEventListener('submit', function (e) { e.preventDefault(); send(); });
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); } });
    input.focus();

    /* poll */
    setInterval(function () {
      if (document.hidden) return;
      PC.api(P.api + '?action=fetch&with=' + P.with + '&after=' + last, null, 'GET').then(function (r) {
        if (!r.success || !r.messages.length) return;
        var empty = document.getElementById('chatEmpty'); if (empty) empty.remove();
        var atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 80;
        r.messages.forEach(function (m) {
          if (box.querySelector('[data-id="' + m.id + '"]')) return;
          box.appendChild(bubble(m)); last = Math.max(last, m.id);
        });
        if (atBottom) scroll();
      });
    }, 5000);
  }

  /* new chat picker */
  var s = document.getElementById('userSearch'), out = document.getElementById('userResults'), t;
  if (s) s.addEventListener('input', function () {
    clearTimeout(t);
    var q = s.value.trim();
    if (q.length < 2) { out.innerHTML = ''; return; }
    t = setTimeout(function () {
      PC.api(P.api + '?action=users&q=' + encodeURIComponent(q), null, 'GET').then(function (r) {
        out.innerHTML = (r.users && r.users.length) ? r.users.map(function (u) {
          return '<a class="chat-item" style="border:0;border-radius:10px;padding:.5rem" href="' + P.base + '?with=' + u.id + '"><img class="avatar avatar-sm" src="' + PC.esc(u.avatar) + '" alt=""><div><strong>' + PC.esc(u.name) + '</strong><div class="text-muted text-xs">@' + PC.esc(u.username) + '</div></div></a>';
        }).join('') : '<p class="text-muted text-sm" style="padding:.5rem">No one found.</p>';
      });
    }, 250);
  });
})();
