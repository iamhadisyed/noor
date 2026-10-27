// Seeds practice and alerts through the API, then checks dashboard, alerts, reports, CSV and settings.
const { BASE, launch, login, check } = require('./lib.cjs');
(async () => {
  const { browser, page, errors } = await launch();
  const email = `report${Date.now()}@kindo.test`;
  await login(page, email); await page.waitForSelector('#nm'); await page.fill('#nm', 'Reporter'); await page.click('text=Continue');
  await page.waitForSelector('.modal'); await page.fill('#cn', 'Zara'); await page.fill('#cd', '2019-02-03'); await page.click('.modal >> text=Add child'); await page.waitForSelector('.kid');
  await page.evaluate(async () => {
    const H = { Authorization: 'Bearer ' + localStorage.getItem('kindo.token'), Accept: 'application/json', 'Content-Type': 'application/json' };
    const j = (p, m = 'GET', b) => fetch('/api/v1' + p, { method: m, headers: H, body: b ? JSON.stringify(b) : undefined }).then((r) => r.json());
    const id = (await j('/children'))[0].id;
    await j(`/children/${id}/sessions`, 'POST', { language: 'urdu', total_attempts: 12, successful_attempts: 9, duration_seconds: 840, mistakes: [{ target_string: 'ب', attempted_string: 'پ', mistake_type: 'dot_misplacement', stroke_deviation_score: 12.5 }] });
    await j(`/children/${id}/sessions`, 'POST', { language: 'english', total_attempts: 10, successful_attempts: 7, duration_seconds: 600 });
    await j('/child/alert', 'POST', { child_id: id, need_type: 'potty' });
  });
  await page.goto(BASE + '/portal/#/alerts'); await page.reload(); await page.waitForSelector('.item');
  check('alert is listed as waiting', /needs the potty.*Waiting for you/.test(await page.textContent('.item')));
  await page.click('.item >> text=I\'m on my way'); await page.waitForTimeout(1000);
  check('replying marks it on the way with the replier', /Reporter replied.*On the way/.test(await page.textContent('.item')));
  await page.click('nav.nav >> text=Reports'); await page.waitForSelector('.tiles'); await page.waitForTimeout(700);
  const tiles = await page.$$eval('.tile', (e) => e.map((x) => x.textContent.replace(/\s+/g, ' ')));
  check('report adds up practice and accuracy', /24\s?min/.test(tiles[0]) && /73\s?%/.test(tiles[1]), tiles.slice(0, 2).join(' | '));
  const [dl] = await Promise.all([page.waitForEvent('download'), page.click('text=Export CSV')]);
  check('CSV export downloads', /report\.csv$/.test(dl.suggestedFilename()));
  await page.click('nav.nav >> text=Settings'); await page.waitForSelector('text=This device');
  check('this device is listed as a session', /This device/.test(await page.textContent('#content')));
  await page.goto(BASE + '/portal/#/admin'); await page.waitForTimeout(700);
  check('a parent is kept out of the admin console', (await page.evaluate(() => location.hash)) === '#/');
  check('no unexpected browser errors', errors.length === 0, errors.join(' | '));
  await browser.close();
})();
