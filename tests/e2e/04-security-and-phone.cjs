// Hostile text is shown as plain text and never runs; the layout fits phone widths with no sideways scroll.
const { BASE, OUT, launch, login, check } = require('./lib.cjs');
const path = require('path');
(async () => {
  const { browser, page, errors } = await launch();
  await login(page, `xss${Date.now()}@kindo.test`); await page.waitForSelector('#nm');
  const evil = '<img src=x onerror="window.__xss=1"><script>window.__xss=1</script>';
  await page.fill('#nm', '<b onmouseover=window.__xss=1>Eve</b>'); await page.click('text=Continue');
  await page.waitForSelector('.modal'); await page.fill('#cn', evil); await page.fill('#cd', '2019-01-02'); await page.click('.modal >> text=Add child'); await page.waitForSelector('.kid');
  await page.click('nav.nav >> text=Lessons'); await page.click('text=New lesson'); await page.fill('#lt', evil); await page.click('.modal button:has-text("Create lesson")'); await page.waitForSelector('.editor');
  await page.click('[data-act=add-task]'); await page.click('.types button >> nth=0'); await page.waitForSelector('.task'); await page.locator('.task .title input').first().fill(evil); await page.waitForTimeout(1000);
  for (const h of ['#/', '#/children', '#/lessons', '#/reports', '#/alerts', '#/settings']) { await page.goto(BASE + '/portal/' + h); await page.reload(); await page.waitForTimeout(500); }
  check('no injected script ran', !(await page.evaluate(() => window.__xss === 1)));
  await page.goto(BASE + '/portal/#/children'); await page.waitForSelector('.kid');
  check('hostile name is shown literally', (await page.textContent('.kid h2')).includes('<img'));
  check('no element was injected', (await page.evaluate(() => document.querySelectorAll('#content img[src="x"]').length)) === 0);
  for (const w of [360, 390, 768]) {
    const m = await browser.newContext({ viewport: { width: w, height: 844 } }); const p = await m.newPage();
    await login(p, `xss${Date.now()}b@kindo.test`.replace('b@', 'b@')).catch(() => {});
    await m.close();
  }
  const m = await browser.newContext({ viewport: { width: 390, height: 844 } }); const mp = await m.newPage();
  await mp.goto(BASE + '/portal/'); await mp.evaluate((t) => localStorage.setItem('kindo.token', t), await page.evaluate(() => localStorage.getItem('kindo.token')));
  for (const w of [360, 390, 768]) {
    await mp.setViewportSize({ width: w, height: 844 }); await mp.goto(BASE + '/portal/#/lessons'); await mp.reload(); await mp.waitForSelector('.lesson-card'); await mp.click('.lesson-card >> text=Open'); await mp.waitForSelector('.task');
    check(`editor fits ${w}px wide`, !(await mp.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)));
    if (w === 390) await mp.screenshot({ path: path.join(OUT, 'editor-phone.png') });
  }
  check('no unexpected browser errors', errors.length === 0, errors.join(' | '));
  await browser.close();
})();
