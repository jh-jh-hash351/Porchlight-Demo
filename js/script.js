document.addEventListener('DOMContentLoaded', function () {
  var navToggle = document.getElementById('navToggle');
  var sidebarLeft = document.getElementById('sidebarLeft');
  var overlay = document.getElementById('overlay');
  var postList = document.getElementById('postList');
  var searchInput = document.getElementById('searchInput');
  var emptyState = document.getElementById('emptyState');
  var composerForm = document.getElementById('composerForm');
  var composerText = document.getElementById('composerText');
  var composerError = document.getElementById('composerError');
  var composerMedia = document.getElementById('composerImage');
  var composerPreview = document.getElementById('composerPreview');
  var composerPreviewImage = document.getElementById('composerPreviewImage');
  var composerPreviewVideo = document.getElementById('composerPreviewVideo');
  var composerPreviewName = document.getElementById('composerPreviewName');
  var composerPreviewRemove = document.getElementById('composerPreviewRemove');
  var composerPreviewUrl = null;

  function isVideoFile(file) {
    return file.type.indexOf('video/') === 0 || /\.(mp4|webm|mov|ogv|ogg|m4v)$/i.test(file.name);
  }

  function fileSizeLabel(bytes) {
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
    if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
    return bytes + ' B';
  }

  function clearComposerPreview() {
    if (composerPreviewUrl) {
      URL.revokeObjectURL(composerPreviewUrl);
      composerPreviewUrl = null;
    }
    if (composerPreviewImage) {
      composerPreviewImage.hidden = true;
      composerPreviewImage.removeAttribute('src');
    }
    if (composerPreviewVideo) {
      composerPreviewVideo.hidden = true;
      composerPreviewVideo.pause();
      composerPreviewVideo.removeAttribute('src');
      composerPreviewVideo.load();
    }
    if (composerPreviewName) composerPreviewName.textContent = '';
    if (composerPreview) composerPreview.hidden = true;
  }

  if (composerMedia) {
    composerMedia.addEventListener('change', function () {
      var file = composerMedia.files[0];
      clearComposerPreview();
      if (!file) return;

      composerPreviewUrl = URL.createObjectURL(file);
      if (isVideoFile(file) && composerPreviewVideo) {
        composerPreviewVideo.src = composerPreviewUrl;
        composerPreviewVideo.hidden = false;
      } else if (composerPreviewImage) {
        composerPreviewImage.src = composerPreviewUrl;
        composerPreviewImage.hidden = false;
      }

      if (composerPreviewName) {
        composerPreviewName.textContent = file.name + ' · ' + fileSizeLabel(file.size);
      }
      if (composerPreview) composerPreview.hidden = false;
    });

    if (composerPreviewRemove) {
      composerPreviewRemove.addEventListener('click', function () {
        composerMedia.value = '';
        clearComposerPreview();
      });
    }
  }

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
  }

  // Mobile navigation drawer
  if (navToggle && sidebarLeft && overlay) {
    function closeNav() {
      sidebarLeft.classList.remove('is-open');
      overlay.classList.remove('is-visible');
    }
    navToggle.addEventListener('click', function () {
      sidebarLeft.classList.toggle('is-open');
      overlay.classList.toggle('is-visible');
    });
    overlay.addEventListener('click', closeNav);
  }

  // Client-side search filter
  if (searchInput && postList && emptyState) {
    searchInput.addEventListener('input', function () {
      var term = searchInput.value.trim().toLowerCase();
      var visibleCount = 0;
      postList.querySelectorAll('.post').forEach(function (post) {
        var author = post.querySelector('.post-author').textContent.toLowerCase();
        var content = post.querySelector('.post-content').textContent.toLowerCase();
        var match = author.indexOf(term) !== -1 || content.indexOf(term) !== -1;
        post.hidden = !match;
        if (match) visibleCount += 1;
      });
      emptyState.hidden = visibleCount !== 0;
    });
  }

  if (postList) {
    // Like / unlike
    postList.addEventListener('click', function (e) {
      var likeBtn = e.target.closest('.like-btn');
      if (!likeBtn || likeBtn.classList.contains('is-disabled')) return;

      fetch('api/like.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(likeBtn.dataset.id),
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.error) return;
          likeBtn.classList.toggle('is-liked', data.liked);
          likeBtn.querySelector('.like-count').textContent = data.likes;
        });
    });

    // Save / unsave to Booksave
    postList.addEventListener('click', function (e) {
      var saveBtn = e.target.closest('.save-btn');
      if (!saveBtn) return;
      saveBtn.disabled = true;

      fetch('api/save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(saveBtn.dataset.id)
          + '&csrf_token=' + encodeURIComponent(csrfToken()),
      })
        .then(function (r) {
          if (r.status === 401) {
            alert('You need an account to save posts');
            return Promise.reject();
          }
          return r.json();
        })
        .then(function (data) {
          if (data.error) {
            alert(data.error);
            return;
          }

          saveBtn.classList.toggle('is-saved', data.saved);
          saveBtn.setAttribute('aria-pressed', data.saved ? 'true' : 'false');
          var label = saveBtn.querySelector('span');
          if (label) label.textContent = data.saved ? 'Saved' : 'Save';

          if (!data.saved && document.body.dataset.booksave === 'true') {
            var card = saveBtn.closest('.post');
            if (card) card.remove();
            var remaining = postList.querySelectorAll('.post').length;
            var empty = document.getElementById('booksaveEmpty');
            if (empty) empty.hidden = remaining > 0;
            var count = document.querySelector('.booksave-count');
            if (count) count.textContent = remaining + ' saved';
          }
        })
        .catch(function () {})
        .finally(function () {
          saveBtn.disabled = false;
        });
    });

    // Delete your own post
    postList.addEventListener('click', function (e) {
      var deleteBtn = e.target.closest('.delete-btn');
      if (!deleteBtn) return;
      if (!window.confirm('Delete this post? This cannot be undone.')) return;

      deleteBtn.disabled = true;

      fetch('api/delete_post.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(deleteBtn.dataset.id)
          + '&csrf_token=' + encodeURIComponent(csrfToken()),
      })
        .then(function (r) {
          if (r.status === 401) {
            alert('You need an account to delete posts');
            return Promise.reject();
          }
          return r.json();
        })
        .then(function (data) {
          if (data.error) {
            alert(data.error);
            deleteBtn.disabled = false;
            return;
          }

          var card = deleteBtn.closest('.post');
          if (card) card.remove();

          var empty = document.getElementById('booksaveEmpty');
          if (empty) {
            var remaining = postList.querySelectorAll('.post').length;
            empty.hidden = remaining > 0;
            var count = document.querySelector('.booksave-count');
            if (count) count.textContent = remaining + ' saved';
          }
        })
        .catch(function () {
          deleteBtn.disabled = false;
        });
    });

    // Show / hide the comment panel
    postList.addEventListener('click', function (e) {
      var toggleBtn = e.target.closest('.comment-toggle');
      if (!toggleBtn) return;
      var panel = toggleBtn.closest('.post').querySelector('.comments');
      panel.hidden = !panel.hidden;
      if (!panel.hidden) {
        var input = panel.querySelector('input');
        if (input) input.focus();
      }
    });

    // Submit a comment
    postList.addEventListener('submit', function (e) {
      var form = e.target.closest('.comment-form');
      if (!form) return;
      e.preventDefault();

      if (document.body.dataset.authenticated !== 'true') {
        alert('You need account to post this');
        return;
      }

      var input = form.querySelector('input');
      var text = input.value.trim();
      if (!text) return;

      fetch('api/comment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(form.dataset.id) + '&text=' + encodeURIComponent(text),
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.error) return;
          form.previousElementSibling.insertAdjacentHTML('beforeend', data.html);
          form.closest('.post').querySelector('.comment-count').textContent = data.comment_count;
          input.value = '';
        });
    });
  }

  // Create a new post
  if (composerForm) {
    composerForm.addEventListener('submit', function (e) {
      e.preventDefault();

      if (document.body.dataset.authenticated !== 'true') {
        alert('You need account to post this');
        return;
      }

      var text = composerText.value.trim();
      var mediaFile = composerMedia && composerMedia.files[0];
      var submitBtn = composerForm.querySelector('button[type="submit"]');
      composerError.textContent = '';

      if (!text && !mediaFile) {
        composerError.textContent = 'Write something or add a photo or video before posting.';
        return;
      }

      var formData = new FormData();
      formData.append('text', text);
      if (mediaFile) {
        formData.append('media', mediaFile);
      }

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Posting…';
      }

      fetch('api/create_post.php', {
        method: 'POST',
        body: formData,
      })
        .then(function (r) {
          return r.text().then(function (body) {
            try {
              return JSON.parse(body);
            } catch (err) {
              return { error: 'The server could not save that post. The file may be too large for PHP to accept.' };
            }
          });
        })
        .then(function (data) {
          if (data.error) {
            composerError.textContent = data.error;
            return;
          }
          postList.insertAdjacentHTML('afterbegin', data.html);
          composerText.value = '';
          if (composerMedia) composerMedia.value = '';
          clearComposerPreview();
        })
        .catch(function () {
          composerError.textContent = 'Could not reach the server. Please try again.';
        })
        .finally(function () {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Post';
          }
        });
    });
  }

  // Location syncing for Local Spots
  var locateBtn = document.getElementById('locateMe');
  var spotsList = document.getElementById('localSpotsList');

  function updateLocationLinks(position) {
    var lat = position.coords.latitude;
    var lng = position.coords.longitude;

    spotsList.querySelectorAll('a').forEach(function (link) {
      var query = link.dataset.query;
      link.href = 'https://www.google.com/maps/search/' + query + '@' + lat + ',' + lng + ',15z';
    });

    if (locateBtn) {
      locateBtn.style.color = 'var(--positive)';
      setTimeout(function () { locateBtn.style.color = ''; }, 2000);
    }
  }

  function handleError() {
    console.warn('Location access denied or unavailable.');
  }

  if (locateBtn && spotsList) {
    locateBtn.addEventListener('click', function () {
      locateBtn.style.opacity = '0.5';
      navigator.geolocation.getCurrentPosition(updateLocationLinks, handleError);
      setTimeout(function () { locateBtn.style.opacity = '1'; }, 1000);
    });

    window.addEventListener('load', function () {
      navigator.geolocation.getCurrentPosition(updateLocationLinks, handleError);
    });
  }

  // Follow / following
  document.querySelectorAll('.follow-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var targetId = btn.dataset.id;
      if (!targetId) return;

      fetch('api/follow.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(targetId),
      })
        .then(function (r) {
          if (r.status === 401) {
            alert('You need account to follow users');
            return Promise.reject();
          }
          return r.json();
        })
        .then(function (data) {
          if (data.error) {
            alert(data.error);
            return;
          }
          var isFollowing = data.following;
          btn.classList.toggle('is-following', isFollowing);
          btn.textContent = isFollowing ? 'Following' : 'Follow';
        })
        .catch(function (err) {
          console.error('Follow error:', err);
        });
    });
  });

});
