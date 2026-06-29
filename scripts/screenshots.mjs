// Capture app screenshots into docs/screenshots/ (mobile viewport).
//   php artisan migrate:fresh --force && php artisan db:seed --class=DemoSeeder
//   php artisan serve &           (QUEUE_CONNECTION=sync AI_DRIVER=fake)
//   SLUG=<demo-slug> node scripts/screenshots.mjs
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';

const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const SLUG = process.env.SLUG;
const OUT = 'docs/screenshots';
const viewport = { width: 390, height: 844 };

if (!SLUG) throw new Error('SLUG env var is required');
mkdirSync(OUT, { recursive: true });

const browser = await chromium.launch();

// Organizer (authenticated) screens.
const ctx = await browser.newContext({ viewport, deviceScaleFactor: 2 });
const page = await ctx.newPage();

await page.goto(`${BASE}/login`);
await page.fill('#email', 'demo@cuentaclara.test');
await page.fill('#password', 'password');
await page.click('button[type=submit]');
await page.waitForURL('**/events');
await page.screenshot({ path: `${OUT}/dashboard.png`, fullPage: true });

await page.goto(`${BASE}/events/create`);
await page.screenshot({ path: `${OUT}/create.png`, fullPage: true });

await page.goto(`${BASE}/events/${SLUG}/review`, { waitUntil: 'networkidle' });
await page.screenshot({ path: `${OUT}/review.png`, fullPage: true });

await page.goto(`${BASE}/events/${SLUG}/created`);
await page.screenshot({ path: `${OUT}/created.png`, fullPage: true });

// Public participant landing (no auth).
const pub = await browser.newContext({ viewport, deviceScaleFactor: 2 });
const pp = await pub.newPage();
await pp.goto(`${BASE}/e/${SLUG}`, { waitUntil: 'networkidle' });
await pp.screenshot({ path: `${OUT}/public-event.png`, fullPage: true });

await browser.close();
console.log('screenshots written to', OUT);
