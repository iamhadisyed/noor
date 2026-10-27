// Notification settings: quiet hours and muted kinds are saved, and urgent alerts cannot be muted.
const path = require('path');
const { BASE, OUT, launch, login, check } = require('./lib.cjs');
(async () => {
  const { browser, page, errors } = await launch();
  await login(page, `quiet${Date.now()}@kindo.test`);
  await page.waitForSelector('#nm'); await page.fill('#nm', 'Quiet Mama'); await page.click('text=Continue');
  await page.waitForSelector('.modal'); await page.keyboard.press('Escape');
  await page.goto(`${BASE}/portal/#/settings`); await page.waitForSelector('[data-card=notifications] #qh-start'); await page.waitForTimeout(300);
  check('urgent alerts are explained and cannot be muted', /Needs the potty.*always reach you/.test(await page.textContent('[data-card=notifications]')) && (await page.locator('[data-need=potty]').count()) === 0 && (await page.locator('[data-need=hug]').count()) === 1);

  await page.click('button[aria-label="Quiet hours"]'); await page.fill('#qh-start', '21:30'); await page.fill('#qh-end', '06:45'); await page.selectOption('#qh-zone', 'Asia/Karachi');
  await page.check('[data-need=hug] input'); await page.click('[data-act=save-notifications]'); await page.waitForTimeout(700);
  await page.screenshot({ path: path.join(OUT, 'notifications.png') });

  await page.reload(); await page.waitForSelector('#qh-start'); await page.waitForTimeout(400);
  check('the choices are still there after a reload', (await page.inputValue('#qh-start')) === '21:30' && (await page.inputValue('#qh-end')) === '06:45' && (await page.inputValue('#qh-zone')) === 'Asia/Karachi'
    && (await page.getAttribute('button[aria-label="Quiet hours"]', 'aria-checked')) === 'true' && (await page.isChecked('[data-need=hug] input')));
  check('no unexpected browser errors', errors.length === 0, errors.join(' | '));
  await browser.close();
})();
