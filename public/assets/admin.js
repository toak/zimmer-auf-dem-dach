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

const loginDialog = document.querySelector('#login-code');
if (loginDialog && typeof loginDialog.showModal === 'function') {
  const fields = loginDialog.querySelector('#code-fields');
  const code = fields.querySelector('input');
  const digits = [];
  code.type = 'hidden';
  fields.className = 'otp-fields';
  for (let index = 0; index < 6; index++) {
    const input = document.createElement('input');
    input.type = 'text';
    input.inputMode = 'numeric';
    input.autocomplete = index === 0 ? 'one-time-code' : 'off';
    input.required = true;
    input.pattern = '[0-9]';
    input.setAttribute('aria-label', 'Ziffer ' + (index + 1) + ' von 6');
    input.setAttribute('aria-describedby', 'code-help');
    digits.push(input);
    fields.append(input);
  }

  function updateCode() {
    code.value = digits.map(input => input.value).join('');
  }
  function insertDigits(index, value) {
    if (value.length === 6) index = 0;
    const available = value.slice(0, 6 - index);
    Array.from(available).forEach((digit, offset) => { digits[index + offset].value = digit; });
    updateCode();
    digits[Math.min(5, index + available.length)].focus();
    digits[Math.min(5, index + available.length)].select();
  }
  digits.forEach((input, index) => {
    input.addEventListener('focus', () => input.select());
    input.addEventListener('paste', event => {
      event.preventDefault();
      const value = event.clipboardData.getData('text').replace(/[^0-9]/g, '');
      if (value) insertDigits(index, value);
    });
    input.addEventListener('input', event => {
      const value = input.value.replace(/[^0-9]/g, '');
      if (value) insertDigits(index, value.length === 2 && event.data?.length === 1 ? event.data.replace(/[^0-9]/g, '') : value);
      else { input.value = ''; updateCode(); }
    });
    input.addEventListener('keydown', event => {
      if (event.key === 'Backspace' && !input.value && index > 0) {
        event.preventDefault();
        digits[index - 1].value = '';
        digits[index - 1].focus();
        updateCode();
      }
      if (event.key === 'ArrowLeft' && index > 0) digits[index - 1].focus();
      if (event.key === 'ArrowRight' && index < 5) digits[index + 1].focus();
    });
  });
  const opener = document.querySelector('#open-code');
  const close = loginDialog.querySelector('#close-code');
  close.hidden = false;
  function openCode() {
    loginDialog.showModal();
    const email = loginDialog.querySelector('[name=email]');
    (email.value ? digits[0] : email).focus();
  }
  opener.addEventListener('click', event => { event.preventDefault(); openCode(); });
  close.addEventListener('click', () => loginDialog.close());
  loginDialog.addEventListener('close', () => opener.focus());
  if (loginDialog.open) {
    loginDialog.open = false;
    openCode();
  }
}
