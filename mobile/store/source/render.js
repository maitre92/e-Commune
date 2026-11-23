// Génère les images de l'application (assets/images) et de la fiche Play Store (store/play) depuis logo.js.
// Usage : PLAYWRIGHT_CORE=/chemin/node_modules/playwright-core CHROME=/usr/bin/google-chrome node store/source/render.js
const path = require('path');
const { chromium } = require(process.env.PLAYWRIGHT_CORE || 'playwright-core');
const logo = require('./logo');

const root = path.resolve(__dirname, '..', '..');
const jobs = [
  ['assets/images/icon.png', logo.icon(1024), 1024, 1024, false],
  ['assets/images/adaptive-icon.png', logo.adaptive(), 1024, 1024, true],
  ['assets/images/monochrome-icon.png', logo.monochrome(), 1024, 1024, true],
  ['assets/images/splash-icon.png', logo.splash(), 1024, 1024, true],
  ['assets/images/favicon.png', logo.icon(1024), 48, 48, false],
  ['store/play/icon-512.png', logo.icon(1024), 512, 512, false],
  ['store/play/feature-graphic-1024x500.png', logo.feature(), 1024, 500, false],
];

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROME || '/usr/bin/google-chrome' });
  for (const [file, svg, w, h, transparent] of jobs) {
    const page = await browser.newPage({ viewport: { width: w, height: h } });
    await page.setContent(`<html><body style="margin:0;background:transparent">${svg.replace(/width="\d+" height="\d+"/, `width="${w}" height="${h}"`)}</body></html>`);
    await page.screenshot({ path: path.join(root, file), omitBackground: transparent, clip: { x: 0, y: 0, width: w, height: h } });
    await page.close();
    console.log('✔', file);
  }
  await browser.close();
})();
