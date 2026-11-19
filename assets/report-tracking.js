(() => {
  const page = document.querySelector('.report-tracking-page');
  if (!page) return;
  page.querySelectorAll('a[href^="#"]').forEach(link => {
    link.addEventListener('click', () => {
      const target = document.querySelector(link.getAttribute('href'));
      if (!target) return;
      if (target.id === 'conversation') {
        target.querySelector('textarea')?.focus({preventScroll: true});
      } else {
        target.focus({preventScroll: true});
      }
    });
  });
})();
