(() => {
  const motion = matchMedia('(prefers-reduced-motion: reduce)');
  // Delegation also covers buttons and notification rows created after page load.
  document.addEventListener('pointerdown', event => {
    if (motion.matches || event.button !== 0 || !(event.target instanceof Element)) return;
    const control = event.target.closest('.btn, .btn-login, .btn-register, .admin-bell, .notif-btn, .mobile-menu');
    if (!control || control.matches(':disabled, [aria-disabled="true"]')) return;
    const bounds = control.getBoundingClientRect();
    if (!bounds.width || !bounds.height) return;
    control.querySelector('.interaction-ripple')?.remove();
    const ripple = document.createElement('span');
    ripple.className = 'interaction-ripple'; ripple.setAttribute('aria-hidden', 'true');
    const wave = document.createElement('span'); wave.className = 'interaction-wave';
    const diameter = Math.hypot(bounds.width, bounds.height) * 2;
    wave.style.width = wave.style.height = `${diameter}px`;
    wave.style.left = `${event.clientX - bounds.left}px`;
    wave.style.top = `${event.clientY - bounds.top}px`;
    ripple.append(wave); control.append(ripple);
    setTimeout(() => ripple.remove(), 650);
  });
})();
