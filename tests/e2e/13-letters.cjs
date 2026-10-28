// Per-letter tracing results from the app show up in the parent's report, weakest first, with a trend.
const path = require('path');
const { OUT, launch, login, check } = require('./lib.cjs');
(async () => {
  const { browser, page, errors } = await launch();
  await login(page, `mama${Date.now()}@kindo.test`);
  await page.waitForSelector('#nm'); await page.fill('#nm', 'Mama'); await page.click('text=Continue');
  await page.waitForSelector('.modal'); await page.fill('#cn', 'Ayan'); await page.fill('#cd', '2020-05-04'); await page.click('.modal >> text=Add child'); await page.waitForSelector('.kid');
  await page.evaluate(async () => {
    const H = { Authorization: 'Bearer ' + localStorage.getItem('kindo.token'), 'Content-Type': 'application/json', Accept: 'application/json' };
    const kid = (await (await fetch('/api/v1/children', { headers: H })).json())[0];
    const post = (letters) => fetch(`/api/v1/children/${kid.id}/sessions`, { method: 'POST', headers: H, body: JSON.stringify({ language: 'urdu', total_attempts: 10, successful_attempts: 7, duration_seconds: 300, letters }) });
    await post([{ letter: 'ب', attempts: 4, successes: 1, assisted: 3, deviation: 12 }, { letter: 'ا', attempts: 6, successes: 6, assisted: 0, deviation: 3 }]);
    await post([{ letter: 'ب', attempts: 4, successes: 4, assisted: 1 }]);
  });
  await page.click('nav.nav >> text=Reports'); await page.waitForSelector('#letters-card'); await page.waitForTimeout(500);
  const first = await page.textContent('#letters-card [data-letter]');
  check('the weakest letter is listed first with its accuracy', /ب/.test(first) && /63% right/.test(first));
  check('the trend and guide use are shown', /improving/.test(first) && /guide used/.test(first));
  check('a steady letter is marked as got it', /Got it/.test(await page.textContent('#letters-card [data-letter="ا"]')));
  check('a focus line names what to practise', /Focus on: ب/.test(await page.textContent('#letters-card')));
  const dl = page.waitForEvent('download'); await page.click('[data-act=export-pdf]'); const d = await dl;
  check('the progress report downloads as a PDF', /progress-report\.pdf$/.test(d.suggestedFilename()));
  await page.screenshot({ path: path.join(OUT, 'letters-report.png'), fullPage: true });
  check('no unexpected browser errors', errors.length === 0, errors.join(' | '));
  await browser.close();
})();
