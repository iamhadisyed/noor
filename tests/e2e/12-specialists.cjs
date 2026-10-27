// A parent gives a therapist a code; the therapist sees progress and sets an exercise; the parent sees it and ends the access.
const path = require('path');
const { BASE, OUT, launch, login, check } = require('./lib.cjs');
(async () => {
  const { browser, page, errors } = await launch();
  const stamp = Date.now();
  await login(page, `mama${stamp}@kindo.test`);
  await page.waitForSelector('#nm'); await page.fill('#nm', 'Mama'); await page.click('text=Continue');
  await page.waitForSelector('.modal'); await page.fill('#cn', 'Ayan'); await page.fill('#cd', '2020-05-04'); await page.click('.modal >> text=Add child'); await page.waitForSelector('.kid');
  // a little practice so there is something to see
  await page.evaluate(async () => {
    const t = localStorage.getItem('kindo.token'); const H = { Authorization: 'Bearer ' + t, 'Content-Type': 'application/json', Accept: 'application/json' };
    const kid = (await (await fetch('/api/v1/children', { headers: H })).json())[0];
    await fetch(`/api/v1/children/${kid.id}/sessions`, { method: 'POST', headers: H, body: JSON.stringify({ language: 'urdu', total_attempts: 10, successful_attempts: 8, duration_seconds: 600, mistakes: [{ target_string: 'ب', attempted_string: 'پ', mistake_type: 'dot_misplacement' }] }) });
  });

  await page.click('nav.nav >> text=Specialists'); await page.waitForSelector('#sp-label'); await page.waitForTimeout(400);
  await page.fill('#sp-label', 'Ms Sara, speech therapist'); await page.click('[data-act=make-code]'); await page.waitForSelector('[data-code]');
  const code = await page.getAttribute('[data-code]', 'data-code');
  check('a 6-digit code is shown once', /^\d{6}$/.test(code));
  await page.screenshot({ path: path.join(OUT, 'specialist-code.png') });
  await page.click('.modal >> text=Done'); await page.waitForSelector('[data-access]');
  check('the code is listed as waiting', /Waiting/.test(await page.textContent('[data-access]')) && !(await page.textContent('[data-card=access]')).includes(code));

  const c = await browser.newContext({ viewport: { width: 1360, height: 900 } }); const sp = await c.newPage();
  await login(sp, `sara${stamp}@kindo.test`); await sp.waitForSelector('#nm'); await sp.fill('#nm', 'Sara'); await sp.click('text=Continue');
  await sp.waitForSelector('.modal'); await sp.keyboard.press('Escape');
  await sp.goto(`${BASE}/portal/#/settings`); await sp.waitForSelector('[data-card=specialist-code] #sc');
  await sp.fill('#sc', '000000'); await sp.click('[data-act=redeem-specialist]'); await sp.waitForTimeout(700);
  check('a wrong code is refused plainly', /not valid, has been used, or has expired/.test(await sp.textContent('[data-card=specialist-code]')));
  await sp.fill('#sc', code); await sp.click('[data-act=redeem-specialist]'); await sp.waitForSelector('[data-card=exercise-form]');
  check('the right code opens the child, read-only progress', /Ayan/.test(await sp.textContent('#content')) && /80/.test(await sp.textContent('.tiles')) && /ب/.test(await sp.textContent('#content')));
  check('Shared with me appears in the menu', /Shared with me/.test(await sp.textContent('nav.nav')));
  check('no alerts, rewards or family are shown', !/alert|reward|family card/i.test(await sp.textContent('#content')));
  await sp.screenshot({ path: path.join(OUT, 'specialist-view.png'), fullPage: true });
  await sp.fill('#ex-title', 'Trace Bay slowly'); await sp.fill('#ex-note', 'Five minutes a day.'); await sp.click('[data-act=assign]'); await sp.waitForSelector('[data-exercise]');
  check('the exercise is sent', /Trace Bay slowly/.test(await sp.textContent('[data-card=exercise-form]')));

  await page.reload(); await page.waitForSelector('[data-card=exercises]'); await page.waitForTimeout(600);
  check('the parent sees the exercise and who set it', /Trace Bay slowly/.test(await page.textContent('[data-card=exercises]')) && /From Sara/.test(await page.textContent('[data-card=exercises]')));
  check('the parent sees the therapist has looked', /Sara.*last looked/.test(await page.textContent('[data-access]')));
  await page.click('[data-exercise] button.switch'); await page.waitForTimeout(600);
  check('the parent can tick it off', (await page.getAttribute('[data-exercise] button.switch', 'aria-checked')) === 'true');

  await page.click('[data-act=revoke]'); await page.click('.modal button:has-text("Stop sharing")'); await page.waitForTimeout(900);
  await sp.reload(); await sp.waitForTimeout(1200);
  check('after the parent stops sharing, the therapist can no longer see the child', (await sp.locator('[data-card=specialist-code]').count()) === 1 && !/Ayan/.test(await sp.textContent('#content')));
  check('no unexpected browser errors', errors.length === 0, errors.join(' | '));
  await browser.close();
})();
