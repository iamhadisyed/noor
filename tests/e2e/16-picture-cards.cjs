// A parent makes, renames and deletes their own picture card; a blocked word is refused.
const path = require('path');
const { OUT, TMP, launch, login, check, picture } = require('./lib.cjs');
(async () => {
  const { browser, page, errors } = await launch();
  await login(page, `mama${Date.now()}@kindo.test`);
  await page.waitForSelector('#nm'); await page.fill('#nm', 'Mama'); await page.click('text=Continue');
  await page.waitForSelector('.modal'); await page.fill('#cn', 'Ayan'); await page.fill('#cd', '2020-05-04'); await page.click('.modal >> text=Add child'); await page.waitForSelector('.kid');
  await page.click('nav.nav >> text=Cards'); await page.waitForSelector('[data-card=my-cards]'); await page.waitForTimeout(400);
  check('starts with no cards of their own', /No cards of your own yet/.test(await page.textContent('[data-card=my-cards]')));

  const file = path.join(TMP, 'car.jpg'); picture(file);
  await page.click('[data-act=new-card]'); await page.waitForSelector('#ac-en');
  await page.click('[data-act=save-card]');
  check('a name and picture are asked for', /Give the card a name/.test(await page.textContent('.modal')));
  await page.fill('#ac-en', 'stupid'); await page.setInputFiles('#ac-pic', file); await page.click('[data-act=save-card]'); await page.waitForTimeout(700);
  check('a blocked word is refused plainly', /choose a different word/.test(await page.textContent('.modal')));
  await page.fill('#ac-en', 'Blue car'); await page.fill('#ac-ur', 'نیلی گاڑی'); await page.click('[data-act=save-card]'); await page.waitForSelector('[data-card-id]');
  check('the card is listed with its picture', /Blue car/.test(await page.textContent('[data-card-id]')) && await page.$eval('[data-card-id] img', (i) => i.complete && i.naturalWidth > 0));
  await page.screenshot({ path: path.join(OUT, 'picture-cards.png') });

  await page.click('[data-act=edit-card]'); await page.waitForSelector('#ac-en'); await page.fill('#ac-en', 'Red car'); await page.click('[data-act=save-card]'); await page.waitForTimeout(800);
  check('it can be renamed', /Red car/.test(await page.textContent('[data-card=my-cards]')));
  await page.click('[data-act=delete-card]'); await page.click('.modal button:has-text("Delete")'); await page.waitForTimeout(800);
  check('it can be deleted', /No cards of your own yet/.test(await page.textContent('[data-card=my-cards]')));
  check('no unexpected browser errors', errors.length === 0, errors.join(' | '));
  await browser.close();
})();
