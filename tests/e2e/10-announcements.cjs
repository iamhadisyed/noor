// The super admin publishes a message; a parent sees it above every page, dismisses it, and it stays gone.
const path = require('path');
const { BASE, OUT, launch, login, check } = require('./lib.cjs');
(async () => {
  const { browser, page, errors } = await launch();
  const stamp = Date.now();
  await login(page, 'admin@kindo.test'); await page.waitForSelector('.kpis');
  await page.click('nav.nav >> text=Announcements'); await page.waitForSelector('#an-title');
  await page.fill('#an-title', `Maintenance ${stamp}`); await page.fill('#an-body', 'Kindo will be offline for a few minutes tonight.'); await page.selectOption('#an-level', 'important');
  await page.click('[data-act=publish]'); await page.waitForSelector('[data-announcement-row]');
  check('the message is published and shown as live', /Live/.test(await page.textContent('[data-announcement-row]')));

  const p = await browser.newContext({ viewport: { width: 1360, height: 900 } }); const pp = await p.newPage();
  await login(pp, `reader${stamp}@kindo.test`); await pp.waitForSelector('#nm'); await pp.fill('#nm', 'Reader'); await pp.click('text=Continue');
  await pp.waitForSelector('.modal'); await pp.keyboard.press('Escape');
  await pp.goto(`${BASE}/portal/#/settings`); await pp.waitForSelector('[data-announcement]');
  check('a parent sees it above the page', new RegExp(`Maintenance ${stamp}`).test(await pp.textContent('[data-announcement]')));
  await pp.screenshot({ path: path.join(OUT, 'announcement.png') });
  await pp.goto(`${BASE}/portal/#/lessons`); await pp.waitForSelector('[data-announcement]');
  check('it follows them to other pages', true);
  await pp.click('[data-announcement] button[aria-label^="Dismiss"]'); await pp.waitForTimeout(800);
  check('dismissing removes it', (await pp.locator('[data-announcement]').count()) === 0);
  await pp.reload(); await pp.waitForTimeout(1200);
  check('and it stays gone after a reload', (await pp.locator('[data-announcement]').count()) === 0);

  await page.click('[data-announcement-row] >> text=End now'); await page.waitForTimeout(700);
  check('the admin can end it', /Ended/.test(await page.textContent('[data-announcement-row]')));
  check('no unexpected browser errors', errors.length === 0, errors.join(' | '));
  await browser.close();
})();
