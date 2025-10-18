document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('.menu-toggle');
  const nav = document.getElementById('main-menu-list');

  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      nav.classList.toggle('active');
      const expanded = toggle.getAttribute('aria-expanded') === 'true';
      toggle.setAttribute('aria-expanded', !expanded);
    });
  }

  // Optional: dropdown click per mobile
  const menuItems = document.querySelectorAll('.custom-main-nav li.menu__item');

  menuItems.forEach(item => {
    const submenu = item.querySelector('ul');
    if (submenu) {
      item.querySelector('a.menu__link').addEventListener('click', e => {
        if (window.innerWidth <= 768) {
          e.preventDefault();
          submenu.classList.toggle('active');
        }
      });
    }
  });
});
