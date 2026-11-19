(function () {
  function closeMenus(except) {
    document.querySelectorAll('details.icon-menu[open]').forEach((menu) => {
      if (menu !== except) {
        menu.open = false;
      }
    });
  }

  document.addEventListener('click', (event) => {
    const menu = event.target.closest('details.icon-menu');
    if (!menu) {
      closeMenus();
      return;
    }

    if (event.target.closest('.icon-menu-panel a')) {
      menu.open = false;
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeMenus();
    }
  });
})();
