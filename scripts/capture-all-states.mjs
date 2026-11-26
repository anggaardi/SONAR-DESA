/* eslint-disable */
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';

const BASE = 'http://localhost:8080';
const OUT_DIR = '/work/storage/app/design-refs/screenshots';

fs.mkdirSync(OUT_DIR, { recursive: true });

const chromePath =
    process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ||
    '/ms-playwright/chromium-1255/chrome-linux64/chrome';

async function run() {
    console.log('Launching Chromium from:', chromePath);
    const browser = await chromium.launch({
        executablePath: chromePath,
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
    });

    async function newMobilePage() {
        const context = await browser.newContext({
            viewport: { width: 360, height: 780 },
            locale: 'id-ID',
            deviceScaleFactor: 2,
        });
        return context.newPage();
    }

    async function newDesktopPage() {
        const context = await browser.newContext({
            viewport: { width: 1280, height: 800 },
            locale: 'id-ID',
            deviceScaleFactor: 2,
        });
        return context.newPage();
    }

    // 1. NotFound.vue (not-found.svg)
    {
        console.log('1. Capturing NotFound.vue (not-found.svg)...');
        const page = await newMobilePage();
        await page.goto(`${BASE}/p/proyek-tidak-ditemukan`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(500);
        await page.screenshot({ path: `${OUT_DIR}/01-not-found-mobile.png`, fullPage: true });
        await page.close();
    }

    // 2. Village.vue search empty (search-empty.svg)
    {
        console.log('2. Capturing Village.vue search empty (search-empty.svg)...');
        const page = await newMobilePage();
        await page.goto(`${BASE}/d/desa-contoh-template`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(500);
        const searchInput = page.locator('#project-search-input');
        if (await searchInput.isVisible()) {
            await searchInput.fill('tidak-ada-proyek-999');
            await page.waitForTimeout(500);
        }
        await page.screenshot({ path: `${OUT_DIR}/02-village-search-empty-mobile.png`, fullPage: true });
        await page.close();
    }

    // 3. ProjectItems.vue filter empty (search-empty.svg)
    {
        console.log('3. Capturing ProjectItems filter empty (search-empty.svg)...');
        const page = await newMobilePage();
        await page.goto(`${BASE}/p/rabat-beton-jl-melati-rt-02-03`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(600);
        const rincian = page.locator('#rincian');
        if (await rincian.isVisible()) {
            await rincian.scrollIntoViewIfNeeded();
            await page.waitForTimeout(300);
            const tiles = page.locator('#rincian button');
            const count = await tiles.count();
            for (let i = 0; i < count; i++) {
                const text = await tiles.nth(i).innerText();
                if (text.includes('Tanpa acuan') || text.includes('0')) {
                    await tiles.nth(i).click();
                    await page.waitForTimeout(400);
                    break;
                }
            }
        }
        await page.screenshot({ path: `${OUT_DIR}/03-project-items-filter-empty-mobile.png`, fullPage: false });
        await page.close();
    }

    // 4. BPD Dashboard empty state (bpd-empty.svg)
    {
        console.log('4. Capturing BPD Dashboard empty states (bpd-empty.svg)...');
        const page = await newDesktopPage();
        await page.goto(`${BASE}/demo`, { waitUntil: 'networkidle' });
        const bpdBtn = page.locator('button:has-text("BPD")').first();
        if (await bpdBtn.isVisible()) {
            await bpdBtn.click();
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(800);
        }
        await page.screenshot({ path: `${OUT_DIR}/04-bpd-dashboard-desktop.png`, fullPage: true });
        await page.close();
    }

    // 5. Audit Review empty filter state (search-empty.svg)
    {
        console.log('5. Capturing Audit Review empty filter (search-empty.svg)...');
        const page = await newDesktopPage();
        await page.goto(`${BASE}/audits/1`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(600);
        const tabNeedsCheck = page.locator('button:has-text("Perlu Dicek (0)")').first();
        if (await tabNeedsCheck.isVisible()) {
            await tabNeedsCheck.click();
            await page.waitForTimeout(400);
        } else {
            const tabNoRef = page.locator('button:has-text("Tanpa Acuan")').first();
            if (await tabNoRef.isVisible()) {
                await tabNoRef.click();
                await page.waitForTimeout(400);
            }
        }
        await page.screenshot({ path: `${OUT_DIR}/05-audit-review-filter-empty-desktop.png`, fullPage: false });
        await page.close();
    }

    // 6. Submitted.vue confirmation (success-seal.svg)
    {
        console.log('6. Capturing Submitted.vue confirmation (success-seal.svg)...');
        const page = await newMobilePage();
        // Login as warga demo
        await page.goto(`${BASE}/demo`, { waitUntil: 'networkidle' });
        const wargaBtn = page.locator('button:has-text("Warga")').first();
        await wargaBtn.click();
        await page.waitForLoadState('networkidle');

        // Get first project href from the demo village page and navigate directly
        await page.waitForSelector('a[href*="/p/"]', { timeout: 10000 });
        const projectCard = page.locator('a[href*="/p/"]').first();
        const projectHref = await projectCard.getAttribute('href');
        console.log('Navigating directly to project check spec:', `${projectHref}/cek`);
        await page.goto(`${BASE}${projectHref}/cek`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(500);

        console.log('On check spec page:', page.url());

        // Step 1: Click sample button
        const sampleBtn = page.locator('button:has-text("Pakai foto & lokasi contoh")').first();
        await sampleBtn.waitFor({ state: 'visible', timeout: 10000 });
        await sampleBtn.click();
        console.log('Sample button clicked, waiting for sample active confirmation...');

        // Wait for "Foto & lokasi contoh aktif"
        await page.locator('text=Foto & lokasi contoh aktif').waitFor({ state: 'visible', timeout: 10000 });
        console.log('Sample is ready, waiting for distance check & Lanjut button...');

        // Click Lanjut in LocationGate (step 1 -> step 2)
        const lanjutLocation = page.locator('button:has-text("Lanjut")').first();
        await lanjutLocation.waitFor({ state: 'visible', timeout: 15000 });
        await lanjutLocation.click();
        console.log('Clicked Lanjut to measure step');

        // Step 2 (measure): Click Sesuai for all measurements
        const sesuaiLabels = page.locator('label:has-text("Sesuai")');
        await sesuaiLabels.first().waitFor({ state: 'visible', timeout: 10000 });
        const sesuaiCount = await sesuaiLabels.count();
        console.log('Found measurement items:', sesuaiCount);
        for (let i = 0; i < sesuaiCount; i++) {
            await sesuaiLabels.nth(i).click();
            await page.waitForTimeout(200);
        }

        // Click Lanjut to step 3 (photo) using the visible button in measure section
        const lanjutMeasure = page.locator('button:visible:has-text("Lanjut")').first();
        await lanjutMeasure.click();
        console.log('Clicked Lanjut to photo step');

        // Step 3 (photo): Click Kirim laporan
        const kirimBtn = page.locator('button:visible:has-text("Kirim laporan")').first();
        await kirimBtn.waitFor({ state: 'visible', timeout: 10000 });
        await kirimBtn.click();
        console.log('Clicked Kirim laporan, waiting for redirect to /terkirim...');

        await page.waitForURL(/\/terkirim/, { timeout: 15000 });
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);

        console.log('Final URL after submit:', page.url());
        await page.screenshot({ path: `${OUT_DIR}/06-submitted-report-mobile.png`, fullPage: true });
        await page.close();
    }

    await browser.close();
    console.log('Done! All screenshots captured in', OUT_DIR);
}

run().catch((err) => {
    console.error('Capture error:', err);
    process.exit(1);
});
