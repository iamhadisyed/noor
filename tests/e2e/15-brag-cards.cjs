// A milestone the child has really reached shows up under Celebrate, with a picture the parent can preview, switch to Urdu and save.
const path = require('path');
const { OUT, launch, login, check } = require('./lib.cjs');
(async () => {
  const { browser, page, errors } = await launch();
  await login(page, `mama${Date.now()}@kindo.test`);
  await page.waitForSelector('#nm'); await page.fill('#nm', 'Mama'); await page.click('text=Continue');
  await page.waitForSelector('.modal'); await page.fill('#cn', 'Ayan'); await page.fill('#cd', '2020-05-04'); await page.click('.modal >> text=Add child'); await page.waitForSelector('.kid');

  await page.click('nav.nav >> text=Rewards'); await page.waitForSelector('[data-card=milestones]'); await page.waitForTimeout(500);
  check('nothing is celebrated before anything has happened', /Nothing to celebrate yet/.test(await page.textContent('[data-card=milestones]')) && (await page.locator('[data-act=share-card]').count()) === 0);

  await page.evaluate(async () => {
    const H = { Authorization: 'Bearer ' + localStorage.getItem('kindo.token'), 'Content-Type': 'application/json', Accept: 'application/json' };
    const kid = (await (await fetch('/api/v1/children', { headers: H })).json())[0];
    await fetch(`/api/v1/children/${kid.id}/sessions`, { method: 'POST', headers: H, body: JSON.stringify({ language: 'urdu', total_attempts: 10, successful_attempts: 9, duration_seconds: 3700 }) });
  });
  await page.reload(); await page.waitForSelector('[data-milestone]'); await page.waitForTimeout(400);
  check('an hour of practice is offered for sharing', /60 minutes of practice/.test(await page.textContent('[data-milestone]')));
  await page.click('[data-act=share-card]'); await page.waitForSelector('img.card-preview[src^="blob:"]');
  const size = await page.evaluate(async () => { const i = document.querySelector('img.card-preview'); await i.decode(); return [i.naturalWidth, i.naturalHeight]; });
  check('the card is a 1080 square picture', size[0] === 1080 && size[1] === 1080, size.join('x'));
  await page.screenshot({ path: path.join(OUT, 'brag-card-dialog.png') });
  const before = await page.getAttribute('img.card-preview', 'src');
  await page.selectOption('#cl', 'ur'); await page.waitForFunction((b) => document.querySelector('img.card-preview').src !== b, before);
  check('switching to Urdu makes a new picture', true);
  const dl = page.waitForEvent('download'); await page.click('[data-act=download-card]'); const d = await dl;
  check('the picture can be saved', d.suggestedFilename() === 'kindo-milestone.png');
  check('no unexpected browser errors', errors.length === 0, errors.join(' | '));
  await browser.close();
})();
