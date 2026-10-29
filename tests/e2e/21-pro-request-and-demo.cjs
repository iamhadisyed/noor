// The paid-upgrade workflow, end to end in the browser: a free parent sees screen time is Pro, taps "Make me Pro", is told how to pay,
// says they have paid; the owner signs in with the admin PIN, approves; the parent now has screen time. Also the demo parent PIN login.
const path = require('path');
const { BASE, OUT, launch, login, check } = require('./lib.cjs');

async function pinLogin(page, email, pin) {
  await page.goto(BASE + '/portal/');
  await page.waitForSelector('#em');
  await page.click('[data-act=use-pin]');
  await page.fill('#pe', email); await page.fill('#pp', pin);
  await page.click('form button[type=submit]');
  await page.waitForTimeout(1200);
}

(async () => {
  const { browser, page, errors } = await launch();
  const email = `mama${Date.now()}@kindo.test`;
  await login(page, email);
  await page.waitForSelector('#nm'); await page.fill('#nm', 'Mama'); await page.click('text=Continue');
  await page.waitForSelector('.modal'); await page.fill('#cn', 'Ayan'); await page.fill('#cd', '2020-05-04'); await page.click('.modal >> text=Add child'); await page.waitForSelector('.kid');

  await page.click('nav.nav >> text=Screen time'); await page.waitForSelector('[data-card=pro-locked]');
  check('a free parent is told screen time is part of Pro', /part of Kindo Pro/.test(await page.textContent('#content')));
  await page.click('nav.nav >> text=Activity'); await page.waitForSelector('[data-card=timeline]');
  check('activity works on the free plan for today, with a nudge to Pro', /today only/.test(await page.textContent('#content')));

  await page.goto(BASE + '/portal/#/settings'); await page.waitForSelector('[data-act=make-pro]');
  await page.fill('#up-phone', '0300 1234567'); await page.click('[data-act=make-pro]');
  await page.waitForSelector('[data-act=i-paid]');
  check('after "Make me Pro" the parent is shown how to pay', /We emailed you how to pay/.test(await page.textContent('[data-card=upgrade-request]')));
  await page.fill('#up-method', 'Easypaisa'); await page.fill('#up-ref', 'TX998877'); await page.click('[data-act=i-paid]');
  await page.waitForSelector('text=We are checking your payment');
  check('after "I have paid" the parent is told it is being checked', true);
  await page.screenshot({ path: path.join(OUT, 'upgrade-paid.png') });

  // the owner
  const owner = await launch();
  await pinLogin(owner.page, 'admin@kindo.local', '482916');
  await owner.page.goto(BASE + '/portal/#/admin/upgrades'); await owner.page.waitForSelector('[data-card=upgrades]', { timeout: 8000 });
  const row = owner.page.locator(`[data-upgrade]:has-text("${email}")`);
  check('the owner sees the request with the payment details', /Easypaisa: TX998877/.test(await row.textContent()));
  await row.locator('[data-act=open-approve]').click();
  await owner.page.fill('#up-days', '365'); await owner.page.click('[data-act=approve-upgrade]');
  await owner.page.waitForTimeout(900);
  check('approving removes it from the open list', !(await owner.page.textContent('#content')).includes(email));
  await owner.page.screenshot({ path: path.join(OUT, 'admin-upgrades.png') });
  await owner.browser.close();

  // the parent now has Pro
  await page.goto(BASE + '/portal/#/screen-time'); await page.reload(); await page.waitForSelector('[data-card=screen-rules]');
  await page.click('[data-act=toggle-screen-time]'); await page.waitForSelector('#st-min');
  await page.fill('#st-min', '45'); await page.fill('#st-qs', '20:00'); await page.fill('#st-qe', '07:00'); await page.click('[data-act=save-screen-time]');
  await page.waitForSelector('text=Used 0 of 45 minutes');
  const tx = await page.textContent('#content'); check('with Pro the parent can set a daily limit and quiet hours', /Used 0 of 45 minutes/.test(tx), tx.slice(0, 300));
  await page.click('[data-act=extend-15]'); await page.waitForTimeout(800);
  check('and give extra minutes', /of 60 minutes/.test(await page.textContent('#content')));
  await page.screenshot({ path: path.join(OUT, 'screen-time.png'), fullPage: true });

  // the demo parent: no purchase, full access, signs in with a PIN
  const demo = await launch();
  await pinLogin(demo.page, 'demo.parent@kindo.local', '135790');
  await demo.page.waitForSelector('nav.nav');
  await demo.page.click('nav.nav >> text=Screen time'); await demo.page.waitForSelector('[data-card=screen-rules]');
  check('the demo parent has Pro features without paying', true);
  await demo.page.click('nav.nav >> text=Activity'); await demo.page.waitForSelector('[data-card=timeline]');
  check('the demo parent sees a week of activity already filled in', (await demo.page.locator('[data-kind]').count()) > 5);
  await demo.page.screenshot({ path: path.join(OUT, 'demo-activity.png'), fullPage: true });
  await demo.browser.close();

  check('no browser errors', errors.length === 0, errors.join(' | '));
  await browser.close();
})();
