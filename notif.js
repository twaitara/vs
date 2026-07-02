/* In-app notification bell — shared by staff & portal. Expects window.NOTIF_CFG = {url, csrf}. */
(function () {
  var cfg = window.NOTIF_CFG; if (!cfg) return;
  var bell = document.getElementById('notifBell');
  var badge = document.getElementById('notifBadge');
  var panel = document.getElementById('notifPanel');
  var list = document.getElementById('notifList');
  var markAll = document.getElementById('notifMarkAll');
  if (!bell || !panel || !list) return;

  function lastSeen() { try { return parseInt(localStorage.getItem('notif_last') || '0', 10); } catch (e) { return 0; } }
  function setLastSeen(v) { try { localStorage.setItem('notif_last', String(v)); } catch (e) {} }

  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

  function render(items) {
    list.innerHTML = '';
    if (!items.length) { list.innerHTML = '<div class="notif-empty">No notifications yet.</div>'; return; }
    items.forEach(function (n) {
      var a = document.createElement(n.url ? 'a' : 'div');
      a.className = 'notif-item' + (n.read ? '' : ' unread');
      if (n.url) a.href = n.url;
      a.innerHTML = '<div class="notif-title">' + esc(n.title) + '</div>'
        + (n.body ? '<div class="notif-body">' + esc(n.body) + '</div>' : '')
        + '<div class="notif-time">' + esc(n.created) + '</div>';
      a.addEventListener('click', function () { markRead([n.id]); });
      list.appendChild(a);
    });
  }

  function markRead(ids) {
    var fd = new FormData(); fd.append('_csrf', cfg.csrf);
    (ids || []).forEach(function (id) { fd.append('ids[]', id); });
    fetch(cfg.url + '?action=read', { method: 'POST', body: fd }).then(function (r) { return r.json(); })
      .then(function (d) { if (d && d.ok) setBadge(d.count); }).catch(function () {});
  }

  function setBadge(n) {
    if (!badge) return;
    if (n > 0) { badge.textContent = n > 99 ? '99+' : n; badge.style.display = ''; }
    else badge.style.display = 'none';
  }

  function popup(items) {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    var last = lastSeen(), maxId = last;
    items.slice().reverse().forEach(function (n) {
      if (n.id > last && !n.read) {
        try { new Notification(n.title, { body: n.body || '', tag: 'k' + n.id }); } catch (e) {}
      }
      if (n.id > maxId) maxId = n.id;
    });
    if (maxId > last) setLastSeen(maxId);
  }

  function poll() {
    fetch(cfg.url + '?action=list').then(function (r) { return r.json(); }).then(function (d) {
      if (!d || !d.ok) return;
      setBadge(d.count);
      render(d.items || []);
      popup(d.items || []);
    }).catch(function () {});
  }

  bell.addEventListener('click', function (e) {
    e.preventDefault(); e.stopPropagation();
    panel.classList.toggle('open');
    if ('Notification' in window && Notification.permission === 'default') { try { Notification.requestPermission(); } catch (e) {} }
  });
  document.addEventListener('click', function () { panel.classList.remove('open'); });
  panel.addEventListener('click', function (e) { e.stopPropagation(); });
  if (markAll) markAll.addEventListener('click', function () { markRead([]); poll(); });

  poll();
  setInterval(poll, 30000);
})();
