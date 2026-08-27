document.addEventListener('DOMContentLoaded', () => {
  const footer = document.querySelector('.konekt-footer');
  if (footer) {
    footer.classList.add('js-ready');
  }

  document.querySelectorAll('a[href]').forEach((link) => {
    link.addEventListener('click', () => {
      link.classList.add('is-active');
    });
  });

  const cards = document.querySelectorAll('.card, .panel, .konekt-card');
  cards.forEach((card, index) => {
    card.style.transition = 'transform 0.25s ease, box-shadow 0.25s ease';
    card.addEventListener('mouseenter', () => {
      card.style.transform = 'translateY(-3px)';
      card.style.boxShadow = '0 10px 24px rgba(15, 24, 52, 0.12)';
    });
    card.addEventListener('mouseleave', () => {
      card.style.transform = '';
      card.style.boxShadow = '';
    });
  });
});
