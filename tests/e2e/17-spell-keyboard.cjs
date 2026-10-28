// The lesson editor shows the keyboard a child spells on: the alphabet in order, not QWERTY, and warns about letters it does not have.
const path = require('path');
const { OUT, launch, login, check } = require('./lib.cjs');
(async () => {
  const { browser, page, errors } = await launch();
  await login(page, `mama${Date.now()}@kindo.test`);
  await page.waitForSelector('#nm'); await page.fill('#nm', 'Mama'); await page.click('text=Continue');
  await page.waitForSelector('.modal'); await page.fill('#cn', 'Ayan'); await page.fill('#cd', '2020-05-04'); await page.click('.modal >> text=Add child'); await page.waitForSelector('.kid');
  const id = await page.evaluate(async () => {
    const H = { Authorization: 'Bearer ' + localStorage.getItem('kindo.token'), 'Content-Type': 'application/json', Accept: 'application/json' };
    const kid = (await (await fetch('/api/v1/children', { headers: H })).json())[0];
    const l = await (await fetch(`/api/v1/children/${kid.id}/lessons`, { method: 'POST', headers: H, body: JSON.stringify({ title: 'Spelling', language: 'english' }) })).json();
    await fetch(`/api/v1/lessons/${l.id}/tasks`, { method: 'POST', headers: H, body: JSON.stringify({ type: 'spell_word', title: 'Cat', target: 'cat' }) });
    return l.id;
  });
  await page.goto(`${page.url().split('#')[0]}#/lessons/${id}`); await page.reload(); await page.waitForSelector('[data-keyboard] .key');
  const keys = await page.$$eval('[data-keyboard] .key', (els) => els.map((e) => e.textContent).join(''));
  check('the keyboard is the alphabet in order', keys === 'abcdefghijklmnopqrstuvwxyz', keys);
  check('the letters of the word are lit', (await page.$$eval('[data-keyboard] .key.on', (els) => els.map((e) => e.textContent).sort().join(''))) === 'act');
  check('no stray text under the task cards', !/\bnull\b/.test(await page.textContent('#content')));
  await page.screenshot({ path: path.join(OUT, 'spell-keyboard.png'), fullPage: true });

  await page.fill('[id^=tg-]', 'باپ'); await page.waitForTimeout(300);
  const urdu = await page.$$eval('[data-keyboard] .key', (els) => els.map((e) => e.textContent).slice(0, 4).join(''));
  check('an Urdu word switches to the Urdu alphabet, right to left', urdu === 'اآبپ' && (await page.getAttribute('[data-keyboard] .kbd', 'dir')) === 'rtl', urdu);
  await page.fill('[id^=tg-]', 'ice-cream'); await page.waitForTimeout(300);
  check('a letter the keyboard lacks is called out', /no -/.test(await page.textContent('[data-keyboard]')));
  check('no unexpected browser errors', errors.length === 0, errors.join(' | '));
  await browser.close();
})();
