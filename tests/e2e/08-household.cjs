// A second parent: invited by email, accepts after signing in, then sees and works with the same child.
const path = require('path');
const { BASE, OUT, launch, login, check } = require('./lib.cjs');
(async () => {
  const { browser, page, errors } = await launch();
  const mama = `mama${Date.now()}@kindo.test`, baba = `baba${Date.now()}@kindo.test`;
  await login(page, mama);
  await page.waitForSelector('#nm'); await page.fill('#nm', 'Mama Syed'); await page.click('text=Continue');
  await page.waitForSelector('.modal'); await page.fill('#cn', 'Ayan'); await page.fill('#cd', '2020-05-04'); await page.click('.modal >> text=Add child'); await page.waitForSelector('.kid');

  await page.goto(`${BASE}/portal/#/settings`); await page.waitForSelector('[data-card=family]'); await page.waitForTimeout(400);
  await page.fill('#hh-email', baba); await page.click('[data-act=invite]');
  await page.waitForSelector(`[data-member="${baba}"]`);
  check('the invitation shows as waiting', /Waiting for them/.test(await page.textContent(`[data-member="${baba}"]`)));
  await page.fill('#hh-email', 'not-an-email'); await page.click('[data-act=invite]');
  check('a bad email is refused on the spot', /valid email/.test(await page.textContent('[data-card=family] .msg-err')));

  const b = await browser.newContext({ viewport: { width: 1360, height: 900 } }); const bp = await b.newPage();
  await login(bp, baba); await bp.waitForSelector('#nm'); await bp.fill('#nm', 'Baba Syed'); await bp.click('text=Continue');
  await bp.waitForSelector('.modal'); await bp.click('.modal >> text=Cancel').catch(() => bp.keyboard.press('Escape'));
  await bp.goto(`${BASE}/portal/#/`); await bp.waitForSelector('[data-banner=invite]');
  check('the invited parent sees an invitation and no children yet', /Mama Syed invited you/.test(await bp.textContent('[data-banner=invite]')) && !/Ayan/.test(await bp.textContent('#content')));
  await bp.screenshot({ path: path.join(OUT, 'household-invite.png') });
  await bp.click('[data-act=accept-banner]'); await bp.waitForTimeout(1200);
  await bp.goto(`${BASE}/portal/#/children`); await bp.waitForSelector('.kid');
  check('after accepting, the shared child appears', /Ayan/.test(await bp.textContent('#content')));
  await bp.goto(`${BASE}/portal/#/lessons`); await bp.waitForTimeout(700);
  await bp.click('text=New lesson'); await bp.fill('#lt', 'Lesson by Baba'); await bp.click('.modal >> text=Create lesson'); await bp.waitForSelector('.editor');

  await page.goto(`${BASE}/portal/#/lessons`); await page.waitForTimeout(900);
  check('the first parent sees the lesson the second one made', /Lesson by Baba/.test(await page.textContent('#content')));

  // Mama removes Baba
  await page.goto(`${BASE}/portal/#/settings`); await page.waitForSelector(`[data-member="${baba}"]`);
  await page.click(`[data-member="${baba}"] button[aria-label^="Remove"]`); await page.click('.modal button:has-text("Stop sharing")'); await page.waitForTimeout(900);
  const kids = await bp.evaluate(async () => (await (await fetch('/api/v1/children', { headers: { Authorization: 'Bearer ' + localStorage.getItem('kindo.token'), Accept: 'application/json' } })).json()).length);
  check('after removal the second parent no longer sees the child', kids === 0);
  check('no unexpected browser errors', errors.length === 0, errors.join(' | '));
  await browser.close();
})();
