// "From a photo" on Lessons: the dialog asks for a picture, and an unreadable page gives a plain message (no AI keys in this run).
const path = require('path');
const { OUT, TMP, launch, login, check, picture } = require('./lib.cjs');
(async () => {
  const { browser, page, errors } = await launch();
  await login(page, `mama${Date.now()}@kindo.test`);
  await page.waitForSelector('#nm'); await page.fill('#nm', 'Mama'); await page.click('text=Continue');
  await page.waitForSelector('.modal'); await page.fill('#cn', 'Ayan'); await page.fill('#cd', '2020-05-04'); await page.click('.modal >> text=Add child'); await page.waitForSelector('.kid');
  await page.click('nav.nav >> text=Lessons'); await page.waitForSelector('[data-act=import-photo]');
  await page.click('[data-act=import-photo]'); await page.waitForSelector('#ip');
  await page.screenshot({ path: path.join(OUT, 'photo-import.png') });
  await page.click('[data-act=run-import]');
  check('asks for a photo first', /Choose or take a photo/.test(await page.textContent('.modal')));
  const file = path.join(TMP, 'page.jpg'); picture(file);
  await page.setInputFiles('#ip', file); await page.click('[data-act=run-import]'); await page.waitForTimeout(1200);
  check('a free account is pointed to Pro, with a plain message', /Kindo Pro/.test(await page.textContent('.modal')) && /needs Kindo Pro/.test(await page.textContent('.modal')));
  check('no unexpected browser errors', errors.length === 0, errors.join(' | '));
  await browser.close();
})();
