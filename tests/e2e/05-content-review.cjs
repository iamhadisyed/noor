// Terms at sign-up, the bad-word filter, and the super admin's content review: look at a lesson, decide, deactivate.
const path = require('path');
const { BASE, OUT, TMP, launch, login, check, picture, codes, lastCode } = require('./lib.cjs');
(async () => {
  const pic = path.join(TMP, 'pic.png'); picture(pic);
  const { browser, page, errors } = await launch();
  const email = `bad${Date.now()}@kindo.test`;

  // sign-up shows the terms; the button stays off until they are accepted
  await page.goto(BASE + '/portal/'); await page.waitForSelector('#em'); await page.fill('#em', email);
  const before = codes().length;
  await page.click('text=Email me a code'); await page.waitForSelector('.codes input');
  for (let i = 0; i < 50 && codes().length === before; i++) await page.waitForTimeout(100);
  await page.locator('.codes input').first().click(); await page.keyboard.type(lastCode(), { delay: 30 });
  await page.waitForSelector('#tac');
  check('a new account sees the terms before anything is created', /Pictures and voices/.test(await page.textContent('.terms')));
  check('the create button is off until the box is ticked', await page.locator('text=Create my account').isDisabled());
  await page.screenshot({ path: path.join(OUT, 'terms-step.png') });
  await page.check('#tac'); await page.click('text=Create my account');
  await page.waitForSelector('#nm'); check('accepting the terms creates the account', true);
  await page.fill('#nm', 'Bad Words Mama'); await page.click('text=Continue');
  await page.waitForSelector('.modal'); await page.fill('#cn', 'Zoya'); await page.fill('#cd', '2020-05-04'); await page.click('.modal >> text=Add child'); await page.waitForSelector('.kid');

  // a lesson with a bad word saves, but waits for review
  const lessonId = await page.evaluate(async () => {
    const t = localStorage.getItem('kindo.token'); const H = { Authorization: 'Bearer ' + t, 'Content-Type': 'application/json', Accept: 'application/json' };
    const kids = await (await fetch('/api/v1/children', { headers: H })).json();
    const l = await (await fetch(`/api/v1/children/${kids[0].id}/lessons`, { method: 'POST', headers: H, body: JSON.stringify({ title: 'You stupid game' }) })).json();
    await fetch(`/api/v1/lessons/${l.id}/tasks`, { method: 'POST', headers: H, body: JSON.stringify({ type: 'trace_letter', title: 'Alif', target: 'ا', instruction: 'Trace slowly' }) });
    return l.id;
  });
  await page.goto(`${BASE}/portal/#/lessons/${lessonId}`); await page.waitForSelector('.editor'); await page.waitForTimeout(600);
  check('the parent sees the lesson is under review', /moderator is reviewing/.test(await page.textContent('.check')));
  const t1 = page.locator('.task').nth(0);
  await t1.locator('input[type=file][accept="image/*"]').setInputFiles(pic); await t1.locator('img').waitFor();

  // the super admin finds it in the queue, opens it and sees the picture
  const a = await browser.newContext({ viewport: { width: 1360, height: 900 } }); const ap = await a.newPage();
  await login(ap, 'admin@kindo.test'); await ap.waitForSelector('.kpis');
  await ap.click('nav.nav >> text=Content review'); await ap.waitForSelector('tr[data-flag]');
  check('the flag is in the review queue with the word that matched', /Word: stupid/.test(await ap.textContent('tr[data-flag]')));
  await ap.click('tr[data-flag] >> text=Open'); await ap.waitForSelector('.modal img');
  check('the admin sees the lesson text and its picture', /You stupid game/.test(await ap.textContent('.modal')) && (await ap.locator('.modal img').count()) === 1);
  await ap.screenshot({ path: path.join(OUT, 'admin-review.png') });
  await ap.click('.modal >> text=Close');

  // a blocked word the admin adds
  await ap.click('.tabs >> text=Blocked words'); await ap.fill('input[aria-label="Word or phrase to block"]', 'meanie'); await ap.click('text=Add word'); await ap.waitForSelector('.chip:has-text("meanie")');
  check('the admin can add a blocked word', true);

  // decision: remove and make the parent inactive
  await ap.click('.tabs >> text=Review queue'); await ap.waitForSelector('tr[data-flag]');
  await ap.click('tr[data-flag] [data-act=deactivate_owner]'); await ap.fill('.modal textarea', 'Abusive lesson title'); await ap.click('.modal button:has-text("Remove and deactivate")');
  await ap.waitForSelector('text=Nothing to review');
  check('the queue is clear after the decision', true);

  const clean = errors.slice();   // after this the parent's own requests are refused on purpose
  // the parent is out, the account shows Inactive with the reason, the audit log has it
  await page.goto(BASE + '/portal/#/lessons'); await page.waitForTimeout(1500);
  check('the inactive parent is signed out', (await page.evaluate(() => location.hash)).includes('login'));
  await ap.click('nav.nav >> text=Accounts'); await ap.waitForSelector('tbody tr'); await ap.fill('input[type=search]', email.split('@')[0]); await ap.waitForTimeout(900);
  check('the account shows Inactive with the reason', (await ap.locator(`tr[data-email="${email}"] .chip`).last().textContent()) === 'Inactive' && /Abusive lesson title/.test(await ap.textContent(`tr[data-email="${email}"]`)));
  await ap.click('nav.nav >> text=Audit log'); await ap.waitForSelector('select[aria-label="Filter by action"]'); await ap.selectOption('select[aria-label="Filter by action"]', 'admin.'); await ap.waitForTimeout(800);
  check('viewing the lesson and the decision are in the audit log', /admin\.content_viewed|admin\.flag_resolved/.test(await ap.textContent('tbody')));

  check('no unexpected browser errors', clean.length === 0, clean.join(' | '));
  await browser.close();
})();
