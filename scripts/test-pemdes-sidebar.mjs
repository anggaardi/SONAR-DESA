/* eslint-disable no-console */
import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const { chromium } = require('/home/kadekangga/.npm/_npx/e41f203b7505f1fb/node_modules/playwright');
import fs from 'fs';
import path from 'path';

const BASE_URL = 'http://localhost:8080';
const OUT_DIR = path.resolve('out/playwright/pemdes-sidebar');

if (!fs.existsSync(OUT_DIR)) {
    fs.mkdirSync(OUT_DIR, { recursive: true });
}

async function run() {
    console.log('=== STARTING PLAYWRIGHT PEMDES SIDEBAR CHECK ===');
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const context = await browser.newContext({
        viewport: { width: 1280, height: 800 },
    });
    const page = await context.newPage();

    const results = {
        login: false,
        pages: {},
        mobile: {},
        issues: [],
    };

    try {
        // 1. LOGIN
        console.log('\n1. Logging in as Desa user at /login ...');
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        await page.fill('input[type="email"]', 'desa@desa-contoh.test');
        await page.fill('input[type="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL('**/desa', { timeout: 10000 });
        console.log('✓ Successfully logged in and redirected to /desa');
        results.login = true;

        // 2. CHECK DESKTOP ON /desa (Dashboard)
        console.log('\n2. Checking Desktop Sidebar on /desa (Dashboard)...');
        await page.waitForSelector('#app-sidebar', { state: 'visible' });
        const sidebar = page.locator('#app-sidebar');
        const sidebarBox = await sidebar.boundingBox();
        console.log(`- Sidebar dimensions: width=${sidebarBox?.width}px, height=${sidebarBox?.height}px`);

        const desktopHeader = page.locator('header.sticky.hidden.md\\:flex');
        const isHeaderVisible = await desktopHeader.isVisible();
        const breadcrumbText = isHeaderVisible ? await desktopHeader.locator('div').first().innerText() : '';
        console.log(`- Desktop breadcrumb header visible: ${isHeaderVisible}, text: "${breadcrumbText.replace(/\n+/g, ' ').trim()}"`);

        const logoText = await sidebar.locator('div.items-center span, div.items-center div').allInnerTexts();
        console.log('- Logo / Header content:', logoText.map(t => t.trim()).filter(Boolean).join(' | '));

        const navLinks = await sidebar.locator('a').evaluateAll(elements =>
            elements.map(el => ({
                text: el.innerText.trim(),
                href: el.getAttribute('href'),
                classes: el.className,
                isActive: el.className.includes('bg-primary-subtle') || el.className.includes('text-primary'),
            }))
        );
        console.log('- Nav Links found:', JSON.stringify(navLinks, null, 2));

        const footerText = await sidebar.locator('.border-t').innerText().catch(() => '');
        console.log('- Footer content:', footerText.replace(/\n+/g, ' | ').trim());

        await page.screenshot({ path: path.join(OUT_DIR, '01-desktop-desa-dashboard.png') });
        console.log('✓ Saved screenshot: 01-desktop-desa-dashboard.png');

        results.pages.dashboard = {
            sidebarVisible: await sidebar.isVisible(),
            width: sidebarBox?.width,
            navLinks,
            footerText: footerText.trim(),
        };

        // 3. CHECK DESKTOP ON /desa/projects/create (Tambah Proyek Fisik)
        console.log('\n3. Checking Desktop Sidebar on /desa/projects/create...');
        await page.goto(`${BASE_URL}/desa/projects/create`, { waitUntil: 'networkidle' });
        await page.waitForSelector('#app-sidebar', { state: 'visible' });
        const createSidebar = page.locator('#app-sidebar');
        const createNavLinks = await createSidebar.locator('a').evaluateAll(elements =>
            elements.map(el => ({
                text: el.innerText.trim(),
                href: el.getAttribute('href'),
                classes: el.className,
                isActive: el.className.includes('bg-primary-subtle') || el.className.includes('text-primary'),
            }))
        );
        console.log('- Nav Links found:', JSON.stringify(createNavLinks, null, 2));
        const createFooterText = await createSidebar.locator('.border-t').innerText().catch(() => '');
        console.log('- Footer content:', createFooterText.replace(/\n+/g, ' | ').trim());

        await page.screenshot({ path: path.join(OUT_DIR, '02-desktop-desa-create.png') });
        console.log('✓ Saved screenshot: 02-desktop-desa-create.png');

        results.pages.create = {
            sidebarVisible: await createSidebar.isVisible(),
            navLinks: createNavLinks,
            footerText: createFooterText.trim(),
        };

        if (!createFooterText.toLowerCase().includes('keluar')) {
            results.issues.push('ProjectCreate.vue sidebarFooter does NOT have a "Keluar" (Logout) button (unlike Dashboard.vue)');
        }

        // 4. CHECK DESKTOP ON /desa/projects/1 (Kelola Proyek Fisik)
        console.log('\n4. Checking Desktop Sidebar on /desa/projects/1...');
        await page.goto(`${BASE_URL}/desa/projects/1`, { waitUntil: 'networkidle' });
        const manageSidebar = page.locator('#app-sidebar');
        const manageNavLinks = await manageSidebar.locator('a').evaluateAll(elements =>
            elements.map(el => ({
                text: el.innerText.trim(),
                href: el.getAttribute('href'),
                classes: el.className,
            }))
        );
        console.log('- Nav Links found:', JSON.stringify(manageNavLinks, null, 2));
        const manageSidebarText = await manageSidebar.innerText();
        console.log('- Sidebar raw innerText:\n', manageSidebarText);

        await page.screenshot({ path: path.join(OUT_DIR, '03-desktop-desa-manage.png') });
        console.log('✓ Saved screenshot: 03-desktop-desa-manage.png');

        results.pages.manage = {
            sidebarVisible: await manageSidebar.isVisible(),
            navLinks: manageNavLinks,
            sidebarText: manageSidebarText.trim(),
        };

        if (manageNavLinks.length === 0) {
            results.issues.push('ProjectManage.vue has EMPTY sidebar navigation because it uses <template #sidebar> which does not match AppLayout slots (#logo, #nav, #sidebarFooter)!');
        }

        // 4b. CHECK DESKTOP ON /audits/1 (Review Dokumen RAB)
        console.log('\n4b. Checking Desktop Sidebar on /audits/1...');
        await page.goto(`${BASE_URL}/audits/1`, { waitUntil: 'networkidle' });
        const auditSidebar = page.locator('#app-sidebar');
        const auditNavLinks = await auditSidebar.locator('a').evaluateAll(elements =>
            elements.map(el => ({
                text: el.innerText.trim(),
                href: el.getAttribute('href'),
                classes: el.className,
                isActive: el.className.includes('bg-primary-subtle') || el.className.includes('text-primary'),
            }))
        );
        console.log('- Nav Links found:', JSON.stringify(auditNavLinks, null, 2));
        const auditFooterText = await auditSidebar.locator('.border-t').innerText().catch(() => '');
        console.log('- Footer content:', auditFooterText.replace(/\n+/g, ' | ').trim());

        await page.screenshot({ path: path.join(OUT_DIR, '03b-desktop-audit-review.png') });
        console.log('✓ Saved screenshot: 03b-desktop-audit-review.png');

        results.pages.auditReview = {
            sidebarVisible: await auditSidebar.isVisible(),
            navLinks: auditNavLinks,
            footerText: auditFooterText.trim(),
        };

        // 5. MOBILE VIEWPORT CHECKS
        console.log('\n5. Checking Mobile Sidebar (viewport: 375x667)...');
        await page.setViewportSize({ width: 375, height: 667 });
        await page.goto(`${BASE_URL}/desa`, { waitUntil: 'networkidle' });

        const mobileHeader = page.locator('header');
        const hamburgerBtn = mobileHeader.locator('button[aria-controls="app-sidebar"]');
        const isHamburgerVisible = await hamburgerBtn.isVisible();
        const initialAriaExpanded = await hamburgerBtn.getAttribute('aria-expanded');
        console.log(`- Hamburger button visible: ${isHamburgerVisible}, aria-expanded: ${initialAriaExpanded}`);

        // Check if sidebar is offscreen
        const mobileSidebar = page.locator('#app-sidebar');
        const sidebarClass = await mobileSidebar.getAttribute('class');
        const isOffscreen = sidebarClass?.includes('-translate-x-full');
        console.log(`- Mobile sidebar initial class: "${sidebarClass}", is offscreen (-translate-x-full): ${isOffscreen}`);

        await page.screenshot({ path: path.join(OUT_DIR, '04-mobile-desa-closed.png') });
        console.log('✓ Saved screenshot: 04-mobile-desa-closed.png');

        // Click hamburger to open sidebar
        console.log('- Clicking hamburger button to open mobile sidebar...');
        await hamburgerBtn.click();
        await page.waitForTimeout(300); // transition

        const openAriaExpanded = await hamburgerBtn.getAttribute('aria-expanded');
        const openSidebarClass = await mobileSidebar.getAttribute('class');
        const isOpen = openSidebarClass?.includes('translate-x-0') && !openSidebarClass?.includes('-translate-x-full');
        console.log(`- Hamburger aria-expanded after click: ${openAriaExpanded}, isOpen: ${isOpen}`);

        // Check mobile overlay
        const overlay = page.locator('.fixed.inset-0.z-20');
        const isOverlayVisible = await overlay.isVisible();
        console.log(`- Mobile backdrop overlay visible: ${isOverlayVisible}`);

        await page.screenshot({ path: path.join(OUT_DIR, '05-mobile-desa-open.png') });
        console.log('✓ Saved screenshot: 05-mobile-desa-open.png');

        // Click overlay to close
        console.log('- Clicking backdrop overlay to close sidebar...');
        await overlay.click({ position: { x: 320, y: 300 } });
        await page.waitForTimeout(300);

        const closedSidebarClass = await mobileSidebar.getAttribute('class');
        const isClosedAgain = closedSidebarClass?.includes('-translate-x-full');
        console.log(`- Mobile sidebar closed after overlay click: ${isClosedAgain}`);

        results.mobile = {
            hamburgerVisible: isHamburgerVisible,
            initialOffscreen: isOffscreen,
            openOnClick: isOpen,
            overlayVisible: isOverlayVisible,
            closeOnOverlayClick: isClosedAgain,
        };

        // 6. NAVIGATION TEST
        console.log('\n6. Testing Navigation Clicks on Desktop...');
        await page.setViewportSize({ width: 1280, height: 800 });
        await page.goto(`${BASE_URL}/desa`, { waitUntil: 'networkidle' });

        const createLink = page.locator('#app-sidebar a:has-text("Proyek Fisik")');
        await createLink.click();
        await page.waitForURL('**/desa#daftar-proyek', { timeout: 5000 });
        console.log('✓ Navigated to /desa#daftar-proyek via sidebar click');

        const auditLink = page.locator('#app-sidebar a:has-text("Audit & Ingesti RAB")');
        await auditLink.click();
        await page.waitForURL('**/audits/**', { timeout: 5000 });
        console.log('✓ Navigated to /audits via sidebar click');

        const dashboardLink = page.locator('#app-sidebar a:has-text("Dashboard")');
        await dashboardLink.click();
        await page.waitForURL('**/desa', { timeout: 5000 });
        console.log('✓ Navigated back to /desa via sidebar click');

    } catch (err) {
        console.error('ERROR during check:', err);
        results.issues.push(`Runtime error: ${err.message}`);
    } finally {
        await browser.close();
    }

    console.log('\n=== SUMMARY RESULTS ===');
    console.log(JSON.stringify(results, null, 2));

    fs.writeFileSync(path.join(OUT_DIR, 'results.json'), JSON.stringify(results, null, 2));
}

run();
