// Branding: the browser tab, the sign-in page, the side menu and the logo files all carry the Kindo mark.
const path = require('path');
const { BASE, OUT, launch, check } = require('./lib.cjs');
(async () => {
  const { browser, page, errors } = await launch({ viewport: { width: 1360, height: 1100 } });
  await page.goto(BASE + '/portal/'); await page.waitForSelector('#em');
  check('the tab title names Kindo', /Kindo/.test(await page.title()), await page.title());
  const icons = await page.$$eval('link[rel~=icon], link[rel=apple-touch-icon]', (l) => l.map((x) => x.href));
  check('the page links an icon, a small PNG and an Apple touch icon', icons.length >= 3, icons.join(' '));
  for (const href of icons) { const r = await page.request.get(href); check(`icon is served: ${href.replace(BASE, '')}`, r.ok() && /image\//.test(r.headers()['content-type'] || ''), `${r.status()}`); }
  const fav = await page.request.get(BASE + '/favicon.ico'); check('favicon.ico is the Kindo mark, not the framework default', fav.ok() && (await fav.body()).length > 1500 && (await fav.body()).length < 20000, String((await fav.body()).length));
  check('the sign-in page shows the logo image', await page.evaluate(() => { const i = document.querySelector('.brand img.logo'); return !!i && i.complete && i.naturalWidth >= 90; }));
  await page.screenshot({ path: path.join(OUT, 'brand-login.png') });
  await page.click('[data-act=use-pin]'); await page.fill('#pe', 'demo.parent@kindo.local'); await page.fill('#pp', '135790'); await page.click('form button[type=submit]'); await page.waitForSelector('nav.nav');
  check('the side menu shows the logo and the Kindo line', await page.evaluate(() => { const i = document.querySelector('.side .brand img.logo'); return !!i && i.complete && i.naturalWidth >= 90 && /Kindo/.test(document.querySelector('.side .brandline').textContent); }));
  await page.screenshot({ path: path.join(OUT, 'brand-sidebar.png') });
  check('no browser errors (the strict CSP allows the logo files)', errors.length === 0, errors.join(' | '));
  await browser.close();
})();
