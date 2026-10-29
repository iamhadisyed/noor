// The side menu must never spill out of its coloured panel: on a short screen it scrolls inside the panel, on a tall one it fits.
const path = require('path');
const { BASE, OUT, launch, check } = require('./lib.cjs');

async function pinLogin(page, email, pin) {
  await page.goto(BASE + '/portal/');
  await page.waitForSelector('#em');
  await page.click('[data-act=use-pin]');
  await page.fill('#pe', email); await page.fill('#pp', pin);
  await page.click('form button[type=submit]');
  await page.waitForSelector('nav.nav');
}

(async () => {
  for (const [label, email, pin] of [['owner', 'admin@kindo.local', '482916'], ['parent', 'demo.parent@kindo.local', '135790']]) {
    for (const h of [600, 800, 1000]) {
      const { browser, page, errors } = await launch({ viewport: { width: 1360, height: h } });
      await pinLogin(page, email, pin);
      const r = await page.evaluate(() => {
        const side = document.querySelector('.side'); const box = side.getBoundingClientRect();
        const scrolls = getComputedStyle(side).overflowY === 'auto' && side.scrollHeight > side.clientHeight;
        const last = side.querySelector('.foot').getBoundingClientRect();
        // everything is either inside the panel as drawn, or the panel scrolls to reach it
        const kids = [...side.children].every((c) => c.getBoundingClientRect().bottom <= box.bottom + 1);
        return { scrolls, kids, overflowY: getComputedStyle(side).overflowY, bottom: Math.round(box.bottom), footBottom: Math.round(last.bottom), h: innerHeight };
      });
      check(`${label}, ${h}px high: the menu stays inside the panel (fits, or the panel scrolls)`, r.kids || (r.overflowY === 'auto' && r.scrolls), JSON.stringify(r));
      if (h === 600) {
        await page.evaluate(() => { const s = document.querySelector('.side'); s.scrollTop = s.scrollHeight; });
        const reach = await page.evaluate(() => { const s = document.querySelector('.side').getBoundingClientRect(); const b = [...document.querySelectorAll('.side .foot button, .side .foot a')].pop().getBoundingClientRect(); return b.bottom <= s.bottom + 1 && b.top >= s.top; });
        check(`${label}: Sign out can be scrolled into view on a short screen`, reach);
        await page.screenshot({ path: path.join(OUT, `sidebar-${label}-600.png`) });
      }
      check(`${label}, ${h}px: no browser errors`, errors.length === 0, errors.join(' | '));
      await browser.close();
    }
  }
})();
