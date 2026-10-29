// The wider set of activities shows up for the parent: new game groups on Rewards, calm activities in Reports, new lesson task types in the editor.
const path = require('path');
const { BASE, OUT, launch, login, check } = require('./lib.cjs');
(async () => {
  const { browser, page, errors } = await launch();
  await login(page, `mama${Date.now()}@kindo.test`);
  await page.waitForSelector('#nm'); await page.fill('#nm', 'Mama'); await page.click('text=Continue');
  await page.waitForSelector('.modal'); await page.fill('#cn', 'Ayan'); await page.fill('#cd', '2020-05-04'); await page.click('.modal >> text=Add child'); await page.waitForSelector('.kid');
  const lid = await page.evaluate(async () => {
    const H = { Authorization: 'Bearer ' + localStorage.getItem('kindo.token'), 'Content-Type': 'application/json', Accept: 'application/json' };
    const kid = (await (await fetch('/api/v1/children', { headers: H })).json())[0];
    const post = (u, b) => fetch('/api/v1' + u, { method: 'POST', headers: H, body: JSON.stringify(b || {}) });
    await post(`/children/${kid.id}/activities/breathing_bubble/done`, { seconds: 120 });
    await post(`/children/${kid.id}/activities/breathing_bubble/done`, { seconds: 60 });
    await post(`/children/${kid.id}/activities/slow_count/done`, { seconds: 20 });
    const l = await (await post(`/children/${kid.id}/lessons`, { title: 'Counting' })).json();
    await post(`/lessons/${l.id}/tasks`, { type: 'count_objects', title: 'How many apples?' });
    return l.id;
  });

  await page.click('nav.nav >> text=Rewards'); await page.waitForSelector('[data-game]'); await page.waitForTimeout(400);
  const games = await page.$$eval('[data-game]', (els) => els.map((e) => e.getAttribute('data-game')));
  check('all 30 games are listed', games.length === 30, String(games.length));
  const text = await page.textContent('#content');
  check('the new groups are named', ['Letters and words', 'Colours and groups', 'Feelings', 'Daily routines'].every((g) => text.includes(g)));
  check('games made with extra support in mind are marked', (await page.locator('[data-game=feelings] .chip:has-text("Extra support")').count()) === 1 && (await page.locator('[data-game=spell_picture] .chip:has-text("Extra support")').count()) === 0);
  await page.screenshot({ path: path.join(OUT, 'rewards-all-games.png'), fullPage: true });

  await page.click('nav.nav >> text=Reports'); await page.waitForSelector('[data-card=calm]'); await page.waitForTimeout(300);
  const calm = await page.textContent('[data-card=calm]');
  check('calm activities are counted for the parent', /3 times/.test(calm) && /3 min/.test(calm) && /breathing bubble/.test(calm), calm);

  await page.goto(`${BASE}/portal/#/lessons/${lid}`); await page.reload(); await page.waitForSelector('select[aria-label^="Type of task"]');
  const types = await page.$$eval('select[aria-label^="Type of task"] option', (o) => o.map((x) => x.textContent));
  check('the new task types can be chosen', ['Trace a number', 'Count the objects', 'Say what you see', 'Social story question'].every((t) => types.includes(t)), types.join(','));
  check('a count task shows the answer choices editor', (await page.locator('.choices').count()) === 1);
  check('no unexpected browser errors', errors.length === 0, errors.join(' | '));
  await browser.close();
})();
