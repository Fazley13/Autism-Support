/* Newsfeed interactions: like, comments, follow, delete (uses PC.api from app.js) */
(function () {
  'use strict';
  var API = window.PAGE.api;
  var $ = function (s, r) { return (r || document).querySelector(s); };

  function commentHtml(c) {
    return '<div class="comment" data-comment="' + c.comment_id + '">' +
      '<img class="avatar avatar-sm" src="' + PC.esc(c.avatar) + '" alt="">' +
      '<div class="comment-bubble"><strong>' + PC.esc(c.username) + '</strong> <span class="text-muted text-xs">' + PC.esc(c.ago) + '</span>' +
      (c.can_delete ? ' <button type="button" class="btn btn-ghost btn-sm" data-comment-del="' + c.comment_id + '" aria-label="Delete comment" style="float:right;padding:.1rem .4rem"><i class="fa-solid fa-xmark"></i></button>' : '') +
      '<div>' + PC.esc(c.comment).replace(/\n/g, '<br>') + '</div></div></div>';
  }
  function setCount(attr, id, n) { var el = $('[' + attr + '="' + id + '"]'); if (el) el.textContent = n; }

  document.addEventListener('click', function (e) {
    var t;

    var likeBtn = e.target.closest('[data-like]');
    if (likeBtn) {
      var id = likeBtn.getAttribute('data-like');
      likeBtn.disabled = true;
      PC.api(API, { action: 'like', post_id: +id }).then(function (r) {
        likeBtn.disabled = false;
        if (!r.success) return PC.toast(r.message || 'Could not like.', 'error');
        likeBtn.classList.toggle('liked', !!r.liked);
        likeBtn.setAttribute('aria-pressed', r.liked ? 'true' : 'false');
        var ic = likeBtn.querySelector('i'); if (ic) ic.className = (r.liked ? 'fa-solid' : 'fa-regular') + ' fa-heart';
        setCount('data-like-count', id, r.count);
        var box = $('#likes-' + id); if (box) { box.removeAttribute('data-loaded'); box.classList.remove('open'); }
      });
      return;
    }

    if ((t = e.target.closest('[data-likes-toggle]'))) {
      var pid = t.getAttribute('data-likes-toggle'), box = $('#likes-' + pid);
      box.classList.toggle('open');
      if (box.classList.contains('open') && !box.getAttribute('data-loaded')) {
        box.textContent = 'Loading…';
        PC.api(API + '?action=likes&post_id=' + pid, null, 'GET').then(function (r) {
          if (!r.success) { box.textContent = r.message || 'Error'; return; }
          box.innerHTML = r.likes.length ? r.likes.map(function (l) {
            return '<a class="badge" style="margin:0 .3rem .3rem 0" href="' + PC.base + '/modules/social/view_profile.php?user_id=' + l.user_id + '">@' + PC.esc(l.username) + '</a>';
          }).join('') : '<span class="text-muted">No likes yet – be the first!</span>';
          box.setAttribute('data-loaded', '1');
        });
      }
    }

    if ((t = e.target.closest('[data-comments-toggle]'))) {
      var cid = t.getAttribute('data-comments-toggle'), cbox = $('#comments-' + cid);
      cbox.classList.toggle('open');
      if (cbox.classList.contains('open') && !cbox.getAttribute('data-loaded')) {
        PC.api(API + '?action=comments&post_id=' + cid, null, 'GET').then(function (r) {
          var list = $('.comments-list', cbox);
          if (!r.success) { list.textContent = r.message || 'Error'; return; }
          list.innerHTML = r.comments.length ? r.comments.map(commentHtml).join('') : '<p class="text-muted text-sm">No comments yet.</p>';
          cbox.setAttribute('data-loaded', '1');
          var ta = $('textarea', cbox); if (ta) ta.focus();
        });
      }
    }

    if ((t = e.target.closest('[data-comment-del]'))) {
      if (!confirm('Delete this comment?')) return;
      var row = t.closest('.comment'), post = t.closest('[data-post]').getAttribute('data-post');
      PC.api(API, { action: 'comment_del', comment_id: +t.getAttribute('data-comment-del') }).then(function (r) {
        if (!r.success) return PC.toast(r.message, 'error');
        row.remove(); setCount('data-comment-count', post, r.count);
      });
    }

    if ((t = e.target.closest('[data-follow]'))) {
      var uid = t.getAttribute('data-follow');
      PC.api(API, { action: 'follow', user_id: +uid }).then(function (r) {
        if (!r.success) return PC.toast(r.message, 'error');
        document.querySelectorAll('[data-follow="' + uid + '"]').forEach(function (b) { b.textContent = r.following ? 'Following' : 'Follow'; });
        PC.toast(r.following ? 'You are now following this parent.' : 'Unfollowed.');
      });
    }

    if ((t = e.target.closest('[data-delete-post]'))) {
      if (!confirm('Delete this post? This cannot be undone.')) return;
      var pId = t.getAttribute('data-delete-post');
      PC.api(API, { action: 'post_del', post_id: +pId }).then(function (r) {
        if (!r.success) return PC.toast(r.message, 'error');
        var card = $('#post-' + pId); if (card) card.remove();
        PC.toast('Post deleted.');
      });
    }
  });

  document.addEventListener('submit', function (e) {
    var f = e.target.closest('[data-comment-form]');
    if (!f) return;
    e.preventDefault();
    var id = f.getAttribute('data-comment-form'), ta = $('textarea', f), text = ta.value.trim();
    if (!text) return;
    PC.api(API, { action: 'comment_add', post_id: +id, comment: text }).then(function (r) {
      if (!r.success) return PC.toast(r.message || 'Could not comment.', 'error');
      var list = $('#comments-' + id + ' .comments-list');
      var empty = $('p.text-muted', list); if (empty) empty.remove();
      list.insertAdjacentHTML('beforeend', commentHtml(r.comment));
      ta.value = ''; setCount('data-comment-count', id, r.count);
    });
  });

  // Ctrl/Cmd+Enter submits a comment
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && e.target.closest('[data-comment-form]')) {
      e.target.closest('form').requestSubmit();
    }
  });
})();
