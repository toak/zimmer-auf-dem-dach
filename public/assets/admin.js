function filterRows() {
  const search = document.querySelector('#term').value.toLocaleLowerCase().trim();
  const status = document.querySelector('#status').value;
  const reminder = document.querySelector('#reminder').value;
  let visible = 0;
  for (const row of document.querySelectorAll('#records tr')) {
    const match = row.cells[0].textContent.toLocaleLowerCase().includes(search)
      && (!status || row.dataset.status === status)
      && (!reminder || row.dataset.reminded === reminder);
    row.hidden = !match;
    if (match) visible++;
  }
  document.querySelector('#matches').textContent = visible === 1 ? '1 Zusage' : visible + ' Zusagen';
}
function showTab() {
  const links = document.querySelectorAll('.tabs a');
  if (!links.length) return;
  const selected = Array.from(links).some(a => a.hash === location.hash) ? location.hash : '#spenden';
  for (const link of links) {
    const isCurrent = link.hash === selected;
    document.querySelector(link.hash).hidden = !isCurrent;
    if (isCurrent) link.setAttribute('aria-current', 'page');
    else link.removeAttribute('aria-current');
  }
}
if (document.querySelector('.tabs')) {
  for (const el of document.querySelectorAll('.filters input,.filters select')) el.addEventListener('input',filterRows);
  window.addEventListener('hashchange',showTab);
  showTab();
  filterRows();
}
