const dialog = document.querySelector('#pledge');
if (dialog) {
  document.querySelector('#open-pledge').addEventListener('click', event => {
    event.preventDefault();
    if (dialog.open) dialog.close();
    dialog.showModal();
  });
  document.querySelector('#close-pledge').addEventListener('click', () => dialog.close());
}
const copy = document.querySelector('#copy-iban');
if (copy) {
  copy.hidden = !navigator.clipboard;
  copy.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText('AT653219500000215897');
      copy.textContent = 'IBAN kopiert';
    } catch { copy.textContent = 'Bitte die IBAN oben markieren und kopieren'; }
  });
}
for (const control of document.querySelectorAll('[data-size]')) {
  control.addEventListener('click', () => {
    document.documentElement.dataset.textSize = control.dataset.size;
  });
}
