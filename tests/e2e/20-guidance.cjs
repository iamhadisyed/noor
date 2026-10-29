// The parent guide for autism (Pro): a free account sees what is inside but no content; a Pro account reads the whole guide with the warning
// first and last, in English and Urdu, on a computer and on a phone.
const path = require('path');
const { execSync } = require('child_process');
const { BASE, OUT, launch, login, check } = require('./lib.cjs');
(async () => {
  const { browser, page, errors } = await launch();
  const email = `mama${Date.now()}@kindo.test`;
  await login(page, email);
  await page.waitForSelector('#nm'); await page.fill('#nm', 'Mama'); await page.click('text=Continue');
  await page.waitForSelector('.modal'); await page.fill('#cn', 'Ayan'); await page.fill('#cd', '2020-05-04'); await page.click('.modal >> text=Add child'); await page.waitForSelector('.kid');

  await page.click('nav.nav >> text=Parent guide'); await page.waitForSelector('[data-card=guide-locked]');
  const locked = await page.textContent('#content');
  check('a free account is told the guide is part of Pro and sees what is inside', /part of Kindo Pro/.test(locked) && /Health checks doctors often ask about/.test(locked) && /Food and eating/.test(locked));
  check('nothing of the guide itself is shown on the free plan', !/ferritin|not medical advice|trampoline/i.test(locked));
  await page.screenshot({ path: path.join(OUT, 'guide-locked.png') });

  // give this account Pro, the way an admin gift would
  execSync(`php artisan tinker --execute="App\\\\Models\\\\User::where('email','${email}')->update(['subscription_tier'=>'pro']);"`, { cwd: path.resolve(__dirname, '../..'), stdio: 'ignore' });
  await page.reload(); await page.waitForSelector('[data-card=disclaimer]');
  const text = await page.textContent('#content');
  check('the warning comes first', /Please read first/.test(text) && /not medical advice/.test(text) && text.indexOf('not medical advice') < text.indexOf('Health checks doctors often ask about'));
  check('the warning also comes last', /Remember/.test(text) && /not a replacement for your child/.test(text) && text.lastIndexOf('Remember') > text.indexOf('Hearing and eye checks'));
  await page.waitForSelector('[data-section=tests] summary');
  check('it says no blood test diagnoses autism and lists the usual checks', /no blood test that can diagnose autism/.test(text) && /Full blood count/.test(text) && /Vitamin D/.test(text) && /Thyroid/.test(text) && /Iron and ferritin/.test(text));
  check('it tells parents to talk to their own doctor before changing food or starting exercise', /talk to your own child.s doctor/.test(text));
  check('it says no food cures autism and warns against starting special diets alone', /no food or diet treats or cures autism/i.test(text) || (await page.evaluate(() => { document.querySelectorAll('details').forEach((d) => (d.open = true)); return document.body.textContent; })).match(/no food or diet treats or cures autism/i));
  await page.evaluate(() => document.querySelectorAll('details').forEach((d) => { d.open = true; }));
  const all = await page.textContent('#content');
  check('food, exercise, sleep and when to see a doctor are all there', ['Food and eating', 'Exercise and movement', 'Sleep, routines and comfort', 'See a doctor soon if', 'Swimming (with close supervision)', 'Do not start special diets on your own', 'Your child loses skills'].every((x) => all.includes(x)));
  await page.screenshot({ path: path.join(OUT, 'guide-pro.png'), fullPage: true });

  await page.selectOption('#guide-lang', 'ur'); await page.waitForTimeout(400);
  const ur = await page.textContent('#content');
  check('Urdu shows the warning and reads right to left', /طبی مشورہ نہیں/.test(ur) && (await page.getAttribute('#content [dir]', 'dir')) === 'rtl');
  await page.screenshot({ path: path.join(OUT, 'guide-pro-urdu.png'), fullPage: true });

  const phone = await browser.newContext({ viewport: { width: 375, height: 800 } }); const p2 = await phone.newPage();
  await p2.goto(BASE + '/portal/'); await p2.evaluate((t) => localStorage.setItem('kindo.token', t), await page.evaluate(() => localStorage.getItem('kindo.token')));
  await p2.goto(BASE + '/portal/#/guide'); await p2.waitForSelector('[data-card=disclaimer]');
  check('it fits a phone with no sideways scrolling', await p2.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1));
  await p2.screenshot({ path: path.join(OUT, 'guide-pro-phone.png') });
  check('no browser errors', errors.length === 0, errors.join(' | '));
  await browser.close();
})();
