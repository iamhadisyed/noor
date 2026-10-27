// A parent downloads a copy of their data, then deletes the account with an emailed code, and the email can start fresh.
const fs = require('fs');
const path = require('path');
const { BASE, OUT, TMP, launch, login, check, picture, deleteCodes } = require('./lib.cjs');
(async () => {
  const pic = path.join(TMP, 'pic.png'); picture(pic);
  const { browser, ctx, page, errors } = await launch({ acceptDownloads: true });
  const email = `leaving${Date.now()}@kindo.test`;
  await login(page, email);
  await page.waitForSelector('#nm'); await page.fill('#nm', 'Leaving Mama'); await page.click('text=Continue');
  await page.waitForSelector('.modal'); await page.fill('#cn', 'Noor'); await page.fill('#cd', '2020-05-04'); await page.click('.modal >> text=Add child'); await page.waitForSelector('.kid');
  await page.evaluate(async () => {
    const t = localStorage.getItem('kindo.token'); const H = { Authorization: 'Bearer ' + t, 'Content-Type': 'application/json', Accept: 'application/json' };
    const kids = await (await fetch('/api/v1/children', { headers: H })).json();
    const l = await (await fetch(`/api/v1/children/${kids[0].id}/lessons`, { method: 'POST', headers: H, body: JSON.stringify({ title: 'Alif week' }) })).json();
    await fetch(`/api/v1/lessons/${l.id}/tasks`, { method: 'POST', headers: H, body: JSON.stringify({ type: 'trace_letter', title: 'Alif', target: 'ا' }) });
  });

  await page.goto(`${BASE}/portal/#/settings`); await page.waitForSelector('[data-act=export]');
  const [dl] = await Promise.all([page.waitForEvent('download'), page.click('[data-act=export]')]);
  const file = path.join(TMP, 'mine.zip'); await dl.saveAs(file);
  const buf = fs.readFileSync(file);
  check('the download is a real zip with the parent\'s data', /kindo-my-data-.*\.zip$/.test(dl.suggestedFilename()) && buf.slice(0, 2).toString() === 'PK' && buf.includes('data.json'));
  await page.screenshot({ path: path.join(OUT, 'settings-privacy.png') });

  const before = deleteCodes().length;
  await page.click('[data-act=delete]'); await page.waitForSelector('.modal');
  check('the first step warns that it cannot be undone', /cannot be undone/.test(await page.textContent('.modal')));
  await page.click('.modal >> text=Email me a confirmation code'); await page.waitForSelector('#dc');
  check('the delete button stays off until a 6-digit code is typed', await page.locator('.modal button:has-text("Delete everything")').isDisabled());
  await page.fill('#dc', '000000'); await page.click('.modal button:has-text("Delete everything")');
  await page.waitForSelector('.modal .msg-err:not(:empty)');
  check('a wrong code deletes nothing', /not valid/.test(await page.textContent('.modal .msg-err')));
  for (let i = 0; i < 50 && deleteCodes().length === before; i++) await page.waitForTimeout(100);
  const code = deleteCodes().pop().slice(-6);
  await page.fill('#dc', code); await page.click('.modal button:has-text("Delete everything")');
  await page.waitForSelector('#em'); check('after deleting, the parent is signed out', (await page.evaluate(() => location.hash)).includes('login'));

  const c2 = await browser.newContext({ viewport: { width: 1360, height: 900 } }); const p2 = await c2.newPage();
  await login(p2, email);
  check('the same email starts as a brand-new account', p2.sawTerms === true || (await p2.evaluate(() => location.hash)) === '#/welcome');
  const kids = await p2.evaluate(async () => { const t = localStorage.getItem('kindo.token'); return (await (await fetch('/api/v1/children', { headers: { Authorization: 'Bearer ' + t, Accept: 'application/json' } })).json()).length; });
  check('nothing of the old account came back', kids === 0);
  check('no unexpected browser errors', errors.length === 0, errors.join(' | '));
  await browser.close();
})();
