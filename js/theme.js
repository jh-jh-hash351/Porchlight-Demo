(function () {
  // Applied before paint so pages never flash the wrong theme.
  var saved = localStorage.getItem('porchlight-theme');
  if (saved) document.documentElement.setAttribute('data-theme', saved);

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.theme-toggle').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('porchlight-theme', next);
      });
    });
  });
})();
