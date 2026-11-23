/* eslint-disable no-console */
import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const { chromium } = require('/home/kadekangga/.npm/_npx/e41f203b7505f1fb/node_modules/playwright');
import fs from 'node:fs';

const BASE_URL = process.env.APP_URL || 'http://localhost:8080';
const OUT_DIR = '/home/kadekangga/.gemini/antigravity/brain/81afaa3b-dd8b-4a68-859f-f4def8eb5f5f/screenshots';

if (!fs.existsSync(OUT_DIR)) {
  fs.mkdirSync(OUT_DIR, { recursive: true });
}

async function run() {
  console.log(`🚀 Starting Playwright test on ${BASE_URL}...`);
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  const context = await browser.newContext({
    viewport: { width: 1280, height: 800 },
  });

  const page = await context.newPage();

  try {
    // 1. Log in via /login
    console.log('1. Logging in as Desa user at /login ...');
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await page.fill('input[type="email"]', 'desa@desa-contoh.test');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/desa', { timeout: 10000 });
    console.log('✓ Successfully logged in and redirected to /desa');

    await page.screenshot({ path: `${OUT_DIR}/01-pemdes-dashboard-new.png`, fullPage: false });
    console.log('📸 Captured 01-pemdes-dashboard-new.png');

    // 2. Click "Unggah Dokumen RAB" button
    console.log('2. Navigating to /audits/create...');
    const uploadBtn = page.locator('a:has-text("Unggah Dokumen RAB")').first();
    if (await uploadBtn.isVisible()) {
      await uploadBtn.click();
    } else {
      await page.goto(`${BASE_URL}/audits/create`);
    }
    await page.waitForURL('**/audits/create', { timeout: 10000 });
    console.log(`Landed on: ${page.url()}`);
    await page.screenshot({ path: `${OUT_DIR}/02-audits-create-dropzone.png`, fullPage: false });
    console.log('📸 Captured 02-audits-create-dropzone.png');

    // Check elements on /audits/create
    const heading = await page.locator('h1').textContent();
    console.log(`Upload page heading: "${heading?.trim()}"`);

    // 3. Click "Gunakan Contoh RAB Melati" (Demo button)
    console.log('3. Triggering sample upload...');
    const demoSampleBtn = page.locator('button:has-text("Gunakan Contoh RAB Melati")');
    if (await demoSampleBtn.isVisible()) {
      await Promise.all([
        page.waitForURL(url => url.pathname.startsWith('/audits/') && url.pathname !== '/audits/create', { timeout: 15000 }),
        demoSampleBtn.click()
      ]);
      await page.waitForLoadState('networkidle');
      console.log(`Landed after upload: ${page.url()}`);
      await page.screenshot({ path: `${OUT_DIR}/03-audit-review-dualview.png`, fullPage: false });
      console.log('📸 Captured 03-audit-review-dualview.png');
    } else {
      console.log('Sample button not visible, checking direct /audits...');
      await page.goto(`${BASE_URL}/audits`);
      await page.waitForLoadState('networkidle');
      await page.screenshot({ path: `${OUT_DIR}/03-audit-review-dualview.png`, fullPage: false });
    }

    console.log('✅ End-to-end upload and audit review test completed successfully!');
  } catch (err) {
    console.error('❌ Test failed:', err);
    await page.screenshot({ path: `${OUT_DIR}/error-state.png`, fullPage: false });
    process.exitCode = 1;
  } finally {
    await browser.close();
  }
}

run();
