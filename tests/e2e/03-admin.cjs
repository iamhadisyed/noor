// Super admin: console pages, account search, plan change, make inactive, audit, and a suspended account stays out.
const { BASE, launch, login, check } = require('./lib.cjs');
(async () => {
  const { browser, page, errors } = await launch();
  const victim = `victim${Date.now()}@kindo.test`;
  const v = await browser.newContext(); const vp = await v.newPage();
  await login(vp, victim); await vp.waitForSelector('#nm'); await v.close();

  await login(page, 'admin@kindo.test'); await page.waitForSelector('.kpis');
  check('admin lands on the console with admin navigation', (await page.evaluate(() => location.hash)) === '#/admin' && /Audit log/.test(await page.textContent('nav.nav')));
  for (const [label, sel] of [['Usage', '.bars'], ['Quality', '.kpis'], ['Alerts', '.bars'], ['AI reports', '.kpis'], ['Health', '.item'], ['Audit log', 'table'], ['Accounts', 'tbody tr']]) {
    await page.click(`nav.nav >> text=${label}`); await page.waitForSelector(sel); check(`${label} page loads`, true);
  }
  await page.fill('input[type=search]', victim.split('@')[0]); await page.waitForTimeout(900);
  check('search finds the account', (await page.$$eval('tbody tr', (e) => e.length)) === 1);
  await page.selectOption(`tr[data-email="${victim}"] select`, 'pro'); await page.waitForTimeout(500);
  await page.click(`tr[data-email="${victim}"] >> text=Make inactive`); await page.fill('.modal textarea', 'Reported content'); await page.click('.modal button:has-text("Make inactive")'); await page.waitForTimeout(900);
  check('account is inactive, with the reason shown', (await page.locator(`tr[data-email="${victim}"] .chip`).last().textContent()) === 'Inactive' && /Reported content/.test(await page.textContent(`tr[data-email="${victim}"]`)));
  check('an admin cannot suspend an admin or themselves', (await page.locator('tr[data-email="admin@kindo.test"] button:has-text("Make inactive")').count()) === 0);
  await page.click('nav.nav >> text=Audit log'); await page.waitForSelector('table'); await page.selectOption('select[aria-label="Filter by action"]', 'admin.'); await page.waitForTimeout(600);
  check('the change is in the audit log', (await page.$$eval('tbody tr', (e) => e.length)) >= 1);
  const c2 = await browser.newContext(); const p2 = await c2.newPage(); await login(p2, victim); await p2.waitForTimeout(500);
  check('an inactive parent cannot sign in', /This account is unavailable/.test(await p2.textContent('.box')));
  check('no unexpected browser errors', errors.length === 0, errors.join(' | '));
  await browser.close();
})();
