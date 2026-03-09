document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.mo-error').forEach((node) => {
    node.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });
});
