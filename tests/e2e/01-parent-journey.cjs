// New parent: sign in with an emailed code, onboard, add a special-support child, build and publish a lesson with a picture and sound.
const path = require('path');
const { BASE, OUT, TMP, launch, login, check, wav, picture } = require('./lib.cjs');
(async () => {
  const pic = path.join(TMP, 'pic.png'); picture(pic);
  const { browser, page, errors } = await launch();
  const email = `mama${Date.now()}@kindo.test`;
  await login(page, email);
  await page.waitForSelector('#nm');
  check('first sign-in lands on the welcome step', (await page.evaluate(() => location.hash)) === '#/welcome');
  await page.fill('#nm', 'Hadi Syed'); await page.click('text=Continue');
  await page.waitForSelector('.modal'); await page.fill('#cn', 'Ayan'); await page.fill('#cd', '2020-05-04'); await page.selectOption('#cz', 'autism_spectrum');
  check('a diagnosis picks the special track', (await page.$eval('input[name=track]:checked', (e) => e.value)) === 'special');
  await page.click('.modal >> text=Add child'); await page.waitForSelector('.kid');
  check('child card shows calmer defaults', /slow pace.*3 choices.*no timers/.test(await page.textContent('.kid')));

  await page.click('nav.nav >> text=Lessons'); await page.waitForSelector('text=No lessons yet');
  await page.click('button:has-text("Starter templates")'); await page.click('article:has-text("My day: First, Then") >> text=Use for Ayan');
  await page.waitForSelector('.editor'); await page.waitForTimeout(500);
  check('template copied with 3 tasks', (await page.$$('.task')).length === 3);
  check('checklist names what is missing', /Task 1 "Wash hands" needs a picture/.test(await page.textContent('.check')));
  await page.click('text=Publish lesson'); await page.waitForTimeout(700);
  check('publish is refused while pictures are missing', (await page.locator('.row .chip').first().textContent()) === 'Draft');

  const t1 = page.locator('.task').nth(0);
  await t1.locator('input[type=file][accept="image/*"]').setInputFiles(pic);
  await t1.locator('img').waitFor();
  check('picture is served through a signed link', (await t1.locator('img').getAttribute('src')).includes('signature='));
  await t1.locator('button.switch').click(); await page.waitForTimeout(900);
  check('"needs a sound" makes the sound required', /needs a sound/.test(await page.textContent('.check')));
  await t1.locator('[data-act=record]').click(); await page.waitForSelector('.rec-live'); await page.waitForTimeout(2200); await t1.locator('[data-act=stop]').click();
  await t1.locator('audio').waitFor({ timeout: 8000 });
  check('a recorded sound is saved', true);
  for (const i of [1, 2]) await page.locator('.task').nth(i).locator('input[type=file][accept="image/*"]').setInputFiles(pic);
  await page.waitForTimeout(1300);
  check('checklist clears', /Everything is in place/.test(await page.textContent('.check')));
  await page.screenshot({ path: path.join(OUT, 'editor-ready.png') });
  await page.click('text=Publish lesson'); await page.waitForTimeout(900);
  check('lesson publishes', (await page.locator('.row .chip').first().textContent()) === 'Published');
  await t1.locator('button:has-text("Remove")').last().click(); await page.waitForTimeout(1200);
  check('removing a required sound returns it to draft', (await page.locator('.row .chip').first().textContent()) === 'Draft');
  check('no unexpected browser errors', errors.length === 0, errors.join(' | '));
  await browser.close();
})();
