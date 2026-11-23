/* ParentConnect – shared front-end helpers (loaded on every page) */
(function () {
  'use strict';

  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var BASE = (document.querySelector('meta[name="base-url"]') || {}).content || '';

  /* ---------- Toasts ---------- */
  function toast(message, type) {
    var stack = document.querySelector('.toast-stack');
    if (!stack) {
      stack = document.createElement('div');
      stack.className = 'toast-stack';
      stack.setAttribute('aria-live', 'polite');
      document.body.appendChild(stack);
    }
    var t = document.createElement('div');
    t.className = 'toast ' + (type || 'success');
    var icon = document.createElement('i');
    icon.className = 'fa-solid ' + (type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check');
    var span = document.createElement('span');
    span.textContent = message;
    t.appendChild(icon); t.appendChild(span);
    stack.appendChild(t);
    setTimeout(function () { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; setTimeout(function () { t.remove(); }, 300); }, 3500);
  }

  /* ---------- fetch wrapper: JSON + CSRF ----------
     api('like.php', {post_id: 3})            -> POST JSON
     api('likes.php?post_id=3', null, 'GET')  -> GET                      */
  function api(url, data, method) {
    method = method || (data ? 'POST' : 'GET');
    var opts = { method: method, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf } };
    if (data instanceof FormData) {
      opts.body = data;
    } else if (data && method !== 'GET') {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(data);
    }
    return fetch(url, opts).then(function (r) {
      return r.json().catch(function () { return { success: false, message: 'Unexpected server response.' }; }).then(function (j) {
        if (r.status === 401 && j.login) { window.location = j.login; }
        if (!r.ok && j.success === undefined) { j.success = false; }
        return j;
      });
    }).catch(function () { return { success: false, message: 'Network error. Please check your connection.' }; });
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
  }

  window.PC = { toast: toast, api: api, esc: escapeHtml, csrf: csrf, base: BASE };

  document.addEventListener('DOMContentLoaded', function () {

    /* ---------- Theme: light → dark → calm ---------- */
    var tt = document.getElementById('themeToggle');
    if (tt) {
      var order = ['light', 'dark', 'calm'], names = { light: 'Light', dark: 'Dark', calm: 'Calm (low-stimulation)' };
      tt.addEventListener('click', function () {
        var cur = document.documentElement.getAttribute('data-theme') || 'light';
        var next = order[(order.indexOf(cur) + 1) % order.length];
        document.documentElement.setAttribute('data-theme', next);
        try { localStorage.setItem('pc-theme', next); } catch (e) {}
        toast(names[next] + ' theme', 'success');
      });
    }

    /* ---------- Mobile menu ---------- */
    var nav = document.querySelector('.navbar');
    var toggle = document.querySelector('.menu-toggle');
    if (toggle && nav) {
      toggle.addEventListener('click', function () {
        var open = nav.classList.toggle('menu-open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }

    /* ---------- Dropdowns (click, keyboard friendly) ---------- */
    document.addEventListener('click', function (e) {
      var trigger = e.target.closest('[data-dropdown]');
      document.querySelectorAll('.dropdown.open').forEach(function (d) {
        if (!trigger || !d.contains(trigger)) { d.classList.remove('open'); }
      });
      if (trigger) {
        var dd = trigger.closest('.dropdown');
        dd.classList.toggle('open');
        trigger.setAttribute('aria-expanded', dd.classList.contains('open') ? 'true' : 'false');
        e.preventDefault();
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        document.querySelectorAll('.dropdown.open').forEach(function (d) { d.classList.remove('open'); });
        document.querySelectorAll('.modal-backdrop.open').forEach(function (m) { m.classList.remove('open'); });
      }
    });

    /* ---------- Modals: data-modal-open="#id" / data-modal-close ---------- */
    function fillForm(form, data) {
      Object.keys(data).forEach(function (k) {
        var el = form.elements[k]; if (!el) return;
        el.value = data[k] == null ? '' : data[k];
        el.dispatchEvent(new Event('input', { bubbles: true }));
      });
    }
    function resetForm(form) {
      form.reset();
      Array.prototype.forEach.call(form.elements, function (el) {
        if (el.type === 'hidden' && el.name !== 'csrf' && el.name !== 'action') el.value = '0';
        else if (el.tagName === 'TEXTAREA' || el.type === 'text') el.value = '';
      });
      form.querySelectorAll('.alert').forEach(function (a) { a.remove(); });
    }
    document.addEventListener('click', function (e) {
      var o = e.target.closest('[data-modal-open]');
      if (o) {
        var m = document.querySelector(o.getAttribute('data-modal-open'));
        if (m) {
          var f = m.querySelector('form'), h = m.querySelector('.modal-header h3');
          if (f && o.hasAttribute('data-fill')) { try { fillForm(f, JSON.parse(o.getAttribute('data-fill'))); } catch (err) {} }
          else if (f && o.hasAttribute('data-new')) { resetForm(f); }
          if (h && o.hasAttribute('data-modal-title')) h.textContent = o.getAttribute('data-modal-title');
          m.classList.add('open');
          var first = m.querySelector('input[type=text], textarea'); if (first) setTimeout(function () { first.focus(); }, 50);
        }
        e.preventDefault(); return;
      }
      if (e.target.closest('[data-modal-close]') || e.target.classList.contains('modal-backdrop')) {
        var b = e.target.closest('.modal-backdrop'); if (b) b.classList.remove('open');
      }
    });

    /* ---------- Confirm before dangerous actions: <form data-confirm="Delete?"> or <a/button data-confirm> ---------- */
    document.addEventListener('submit', function (e) {
      var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
      if (msg && !window.confirm(msg)) { e.preventDefault(); }
    });
    document.addEventListener('click', function (e) {
      var el = e.target.closest('a[data-confirm], button[data-confirm]:not([type=submit])');
      if (el && !window.confirm(el.getAttribute('data-confirm'))) { e.preventDefault(); e.stopPropagation(); }
    });

    /* ---------- Prevent double submit ---------- */
    document.addEventListener('submit', function (e) {
      if (e.defaultPrevented) return;
      var btn = e.target.querySelector('button[type=submit]:not([data-no-lock])');
      if (btn) { setTimeout(function () { btn.disabled = true; btn.classList.add('is-loading'); }, 0); setTimeout(function () { btn.disabled = false; }, 8000); }
    });

    /* ---------- Image inputs: auto-resize + compress in the browser, then preview ----------
       <input type=file accept="image/*" data-preview="#img" data-max-dim="800">  (any size is fine)   */
    var TARGET = 600 * 1024;
    function shrink(file, maxDim) {
      return new Promise(function (resolve) {
        if (!/^image\/(jpeg|png|webp|bmp)/.test(file.type)) return resolve(file);   // gif/svg untouched
        var url = URL.createObjectURL(file), img = new Image();
        img.onload = function () {
          var w = img.naturalWidth, h = img.naturalHeight, dim = Math.min(maxDim, Math.max(w, h)), q = 0.86, tries = 0;
          function attempt() {
            var sc = Math.min(1, dim / Math.max(w, h)), c = document.createElement('canvas');
            c.width = Math.max(1, Math.round(w * sc)); c.height = Math.max(1, Math.round(h * sc));
            var ctx = c.getContext('2d'); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height); ctx.drawImage(img, 0, 0, c.width, c.height);
            c.toBlob(function (b) {
              if (!b) return resolve(file);
              if (b.size > TARGET && tries++ < 8) { q = Math.max(0.5, q - 0.08); dim = Math.round(dim * 0.82); return attempt(); }
              URL.revokeObjectURL(url);
              resolve(new File([b], (file.name.replace(/\.[^.]+$/, '') || 'image') + '.jpg', { type: 'image/jpeg' }));
            }, 'image/jpeg', q);
          }
          if (file.size <= TARGET && w <= maxDim && h <= maxDim) { URL.revokeObjectURL(url); return resolve(file); }
          attempt();
        };
        img.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
        img.src = url;
      });
    }
    document.querySelectorAll('input[type=file][accept*="image"]').forEach(function (inp) {
      inp.addEventListener('change', function () {
        var f = inp.files && inp.files[0], img = inp.getAttribute('data-preview') && document.querySelector(inp.getAttribute('data-preview'));
        if (!f) return;
        shrink(f, parseInt(inp.getAttribute('data-max-dim') || '1600', 10)).then(function (out) {
          try { if (out !== f && window.DataTransfer) { var dt = new DataTransfer(); dt.items.add(out); inp.files = dt.files; } } catch (e) {}
          if (img) { img.src = URL.createObjectURL(out); img.classList.add('show'); }
        });
      });
    });

    /* ---------- Auto-grow textareas ---------- */
    document.querySelectorAll('textarea[data-autogrow]').forEach(function (t) {
      var fit = function () { t.style.height = 'auto'; t.style.height = Math.min(t.scrollHeight, 220) + 'px'; };
      t.addEventListener('input', fit); fit();
    });

    /* ---------- Password toggle: <button data-toggle-password="#pw"> ---------- */
    document.querySelectorAll('[data-toggle-password]').forEach(function (b) {
      b.addEventListener('click', function () {
        var i = document.querySelector(b.getAttribute('data-toggle-password'));
        if (!i) return;
        i.type = i.type === 'password' ? 'text' : 'password';
        b.querySelector('i').className = i.type === 'password' ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
      });
    });

    /* ---------- Fade out flash alerts ---------- */
    document.querySelectorAll('.alert[data-autohide]').forEach(function (a) {
      setTimeout(function () { a.style.transition = 'opacity .4s'; a.style.opacity = '0'; setTimeout(function () { a.remove(); }, 400); }, 5000);
    });
  });
})();
