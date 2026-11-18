// The other half of tests/bin/vendor-page-gate.php: serves <html-file> AS
// the vendor's page in a headless browser, lets the vendor's own scripts
// and stylesheets load from the vendor, and records — and blocks —
// everything else before any click. Run it once with the page as saved and
// once with the gated copy; the difference is what the gate stops.
//
// Usage: node tests/bin/vendor-page-run.js <page-url> <html-file> <own-host,…> [selector-to-screenshot] [out-prefix]
//
// This contacts the vendor's site (their page's own assets). It is the
// harness doing that, by hand, not the plugin — and it is not part of any
// suite.
const { chromium } = require('playwright');
const fs = require('fs');
(async () => {
  const [url, file, ownList, selector, out] = process.argv.slice(2);
  if (!ownList) { console.error('usage: node tests/bin/vendor-page-run.js <page-url> <html-file> <own-host,…> [selector] [out-prefix]'); process.exit(2); }
  const own = ownList.split(',');
  const isOwn = (h) => own.some((o) => h === o || h.endsWith('.' + o));
  const html = fs.readFileSync(file, 'utf8');
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  const third = []; const errors = [];
  page.on('pageerror', (e) => errors.push(String(e).slice(0, 160)));
  await page.route('**/*', (route) => {
    const u = route.request().url();
    if (u.startsWith('data:') || u.startsWith('blob:')) return route.continue();
    if (u.split('#')[0] === url.split('#')[0]) return route.fulfill({ contentType: 'text/html; charset=utf-8', body: html });
    const h = new URL(u).hostname;
    if (isOwn(h)) return route.continue();
    third.push(route.request().resourceType() + ' ' + h + new URL(u).pathname.slice(0, 50));
    return route.abort();
  });
  await page.goto(url, { waitUntil: 'load', timeout: 60000 }).catch((e) => console.log('goto:', String(e).slice(0, 120)));
  await page.waitForTimeout(5000);
  const hosts = {}; third.forEach((t) => { const h = t.split(' ')[1].split('/')[0]; hosts[h] = (hosts[h] || 0) + 1; });
  const dom = await page.evaluate(() => ({ panels: document.querySelectorAll('.cg-embed:not(.cg-embed--silent)').length, iframes: Array.from(document.querySelectorAll('iframe')).map((f) => (f.getAttribute('src') || '').slice(0, 70)) }));
  console.log(JSON.stringify({ thirdPartyHosts: hosts, dom, errors: errors.slice(0, 5) }, null, 1));
  if (selector && out) {
    const el = page.locator(selector).first();
    if (await el.count()) { await el.scrollIntoViewIfNeeded(); await page.screenshot({ path: out + '.png' }); console.log('box', JSON.stringify(await el.boundingBox())); }
  }
  await browser.close();
})();
