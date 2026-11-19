document.addEventListener('DOMContentLoaded', function () {
  // New message / create group pickers
  var newBtn = document.getElementById('newMessageBtn');
  var newPanel = document.getElementById('newMessagePanel');
  var peopleSearch = document.getElementById('peopleSearch');
  var createGroupBtn = document.getElementById('createGroupBtn');
  var groupPanel = document.getElementById('newGroupPanel');
  var groupNameInput = document.getElementById('groupNameInput');

  function togglePanel(panel, open) {
    if (!panel) return;
    panel.hidden = !open;
  }

  if (newBtn && newPanel) {
    newBtn.addEventListener('click', function () {
      var opening = newPanel.hidden;
      togglePanel(newPanel, opening);
      togglePanel(groupPanel, false);
      if (opening && peopleSearch) peopleSearch.focus();
    });
  }

  if (createGroupBtn && groupPanel) {
    createGroupBtn.addEventListener('click', function () {
      var opening = groupPanel.hidden;
      togglePanel(groupPanel, opening);
      togglePanel(newPanel, false);
      if (opening && groupNameInput) groupNameInput.focus();
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

  var csrfField = document.getElementById('csrfToken');

  // Create a group chat
  var createGroupForm = document.getElementById('createGroupForm');
  if (createGroupForm) {
    var groupError = document.getElementById('groupFormError');
    var createGroupSubmit = document.getElementById('createGroupSubmit');

    createGroupForm.addEventListener('submit', function (e) {
      e.preventDefault();

      var name = groupNameInput ? groupNameInput.value.trim() : '';
      var selected = createGroupForm.querySelectorAll('input[name="members[]"]:checked');

      if (!name) {
        if (groupError) groupError.textContent = 'Give your group a name.';
        if (groupNameInput) groupNameInput.focus();
        return;
      }
      if (!selected.length) {
        if (groupError) groupError.textContent = 'Choose at least one friend.';
        return;
      }

      if (groupError) groupError.textContent = '';
      var body = new URLSearchParams();
      body.append('name', name);
      selected.forEach(function (checkbox) {
        body.append('members[]', checkbox.value);
      });
      body.append('csrf_token', csrfField ? csrfField.value : '');

      if (createGroupSubmit) {
        createGroupSubmit.disabled = true;
        createGroupSubmit.textContent = 'Creating…';
      }

      fetch('api/create_group_chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.error) {
            if (groupError) groupError.textContent = data.error;
            return;
          }
          location.href = data.redirect || ('messages.php?group=' + data.id);
        })
        .catch(function () {
          if (groupError) groupError.textContent = 'Could not create the group chat.';
        })
        .finally(function () {
          if (createGroupSubmit) {
            createGroupSubmit.disabled = false;
            createGroupSubmit.textContent = 'Create group';
          }
        });
    });
  }

  // Friend requests: add, accept, decline, cancel
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

  // Chat pane (direct or group)
  function initChat(options) {
    var form = options.form;
    var scroll = document.getElementById('chatScroll');
    var input = document.getElementById('chatInput');
    if (!form || !scroll || !input) return;

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

      fetch(options.sendUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: options.buildBody(body, token),
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

      fetch(options.buildFetchUrl(lastId))
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
  }

  var directForm = document.getElementById('chatForm');
  if (directForm) {
    var peerId = directForm.dataset.peer;
    initChat({
      form: directForm,
      sendUrl: 'api/send_message.php',
      buildBody: function (body, token) {
        return 'to=' + encodeURIComponent(peerId)
          + '&body=' + encodeURIComponent(body)
          + '&csrf_token=' + encodeURIComponent(token);
      },
      buildFetchUrl: function (lastId) {
        return 'api/fetch_messages.php?with=' + encodeURIComponent(peerId) + '&after=' + lastId;
      },
    });
  }

  var groupForm = document.getElementById('groupChatForm');
  if (groupForm) {
    var groupId = groupForm.dataset.group;
    initChat({
      form: groupForm,
      sendUrl: 'api/group_chat.php',
      buildBody: function (body, token) {
        return 'group=' + encodeURIComponent(groupId)
          + '&body=' + encodeURIComponent(body)
          + '&csrf_token=' + encodeURIComponent(token);
      },
      buildFetchUrl: function (lastId) {
        return 'api/group_chat.php?group=' + encodeURIComponent(groupId) + '&after=' + lastId;
      },
    });
  }
});
