document.addEventListener('DOMContentLoaded', function () {
  // New message people picker
  var newBtn = document.getElementById('newMessageBtn');
  var newPanel = document.getElementById('newMessagePanel');
  var peopleSearch = document.getElementById('peopleSearch');

  if (newBtn && newPanel) {
    newBtn.addEventListener('click', function () {
      newPanel.hidden = !newPanel.hidden;
      if (!newPanel.hidden && peopleSearch) peopleSearch.focus();
    });
  }

  if (peopleSearch) {
    peopleSearch.addEventListener('input', function () {
      var term = peopleSearch.value.trim().toLowerCase();
      document.querySelectorAll('.people-result').forEach(function (item) {
        item.hidden = (item.dataset.username || '').indexOf(term) === -1;
      });
    });
  }

  // Friend requests: add, accept, decline, cancel
  var csrfField = document.getElementById('csrfToken');

  function refreshRequestPanel() {
    var panel = document.getElementById('friendRequests');
    if (!panel) return;
    var list = panel.querySelector('.fr-list');
    if (list && !list.children.length) panel.remove();
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.friend-action');
    if (!btn || btn.disabled) return;
    e.preventDefault();

    var token = csrfField ? csrfField.value : '';
    var action = btn.dataset.action;
    btn.disabled = true;

    fetch('api/friend.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'id=' + encodeURIComponent(btn.dataset.id)
        + '&action=' + encodeURIComponent(action)
        + '&csrf_token=' + encodeURIComponent(token),
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.error) {
          alert(data.error);
          btn.disabled = false;
          return;
        }

        if (action === 'decline') {
          var item = btn.closest('.fr-item');
          if (item) item.remove();
          refreshRequestPanel();
          return;
        }

        if (data.status === 'friends') {
          location.reload();
          return;
        }

        btn.disabled = false;
        btn.dataset.status = data.status;
        btn.textContent = data.label;
        btn.dataset.action = data.status === 'outgoing' ? 'cancel'
          : data.status === 'incoming' ? 'accept' : 'send';
      })
      .catch(function () {
        alert('Could not update the friend request.');
        btn.disabled = false;
      });
  });

  // Chat pane
  var form = document.getElementById('chatForm');
  var scroll = document.getElementById('chatScroll');
  var input = document.getElementById('chatInput');
  if (!form || !scroll || !input) return;

  var peerId = form.dataset.peer;
  var lastId = parseInt(scroll.dataset.lastId || '0', 10);
  var token = form.querySelector('input[name="csrf_token"]').value;

  function scrollToBottom() {
    scroll.scrollTop = scroll.scrollHeight;
  }
  scrollToBottom();

  function rememberLastId(html) {
    var match = html.match(/data-id="(\d+)"/);
    if (match) lastId = Math.max(lastId, parseInt(match[1], 10));
  }

  function bumpConversation(body) {
    var item = document.querySelector('.conv-item.is-active');
    if (!item) return;

    var preview = item.querySelector('.conv-preview');
    var time = item.querySelector('.conv-time');
    if (preview) preview.textContent = 'You: ' + body;
    if (time) time.textContent = 'Now';

    var list = item.parentNode;
    if (list.firstElementChild !== item) {
      list.insertBefore(item, list.firstElementChild);
    }
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    var body = input.value.trim();
    if (!body) return;
    input.disabled = true;

    fetch('api/send_message.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'to=' + encodeURIComponent(peerId)
        + '&body=' + encodeURIComponent(body)
        + '&csrf_token=' + encodeURIComponent(token),
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.error) {
          alert(data.error);
          return;
        }
        var intro = scroll.querySelector('.chat-intro');
        if (intro) intro.remove();
        scroll.insertAdjacentHTML('beforeend', data.html);
        rememberLastId(data.html);
        input.value = '';
        scrollToBottom();
        bumpConversation(body);
      })
      .catch(function () {
        alert('Could not send your message.');
      })
      .finally(function () {
        input.disabled = false;
        input.focus();
      });
  });

  // Live delivery: poll for new messages while the conversation is open
  setInterval(function () {
    if (document.hidden) return;

    fetch('api/fetch_messages.php?with=' + encodeURIComponent(peerId) + '&after=' + lastId)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.messages || !data.messages.length) return;
        data.messages.forEach(function (html) {
          scroll.insertAdjacentHTML('beforeend', html);
          rememberLastId(html);
        });
        scrollToBottom();
      })
      .catch(function () {});
  }, 5000);
});
