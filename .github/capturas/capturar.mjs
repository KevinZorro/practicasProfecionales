// Temporary: takes screenshots of the app with the development seed data.
import { chromium } from 'playwright';
import fs from 'node:fs';

const BASE = 'http://127.0.0.1:8000';
const OUT = 'docs/capturas';
fs.mkdirSync(OUT, { recursive: true });

const browser = await chromium.launch();

async function session(rolText, viewport = { width: 1440, height: 900 }) {
  const ctx = await browser.newContext({ viewport, deviceScaleFactor: 2, reducedMotion: 'reduce', locale: 'es-CO', timezoneId: 'America/Bogota' });
  const page = await ctx.newPage();
  if (rolText) {
    await page.goto(`${BASE}/desarrollo/acceso`);
    const value = await page.$$eval('#usuario option', (opts, t) => {
      const o = opts.find((x) => (x.textContent.split('—')[1] || '').split(',').map((r) => r.trim())[0] === t);
      return o ? o.value : null;
    }, rolText);
    if (!value) throw new Error(`No user with role ${rolText}`);
    await page.selectOption('#usuario', value);
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  }
  return { ctx, page };
}

async function shot(page, url, name, { full = false, wait = 800 } = {}) {
  try {
    await page.goto(`${BASE}${url}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(wait);
    await page.screenshot({ path: `${OUT}/${name}.png`, fullPage: full });
    console.log('ok', name, page.url());
  } catch (e) {
    console.log('FAIL', name, e.message);
  }
}

let s = await session(null);
await shot(s.page, '/', '01-portada-publica');
await s.ctx.close();

s = await session('coordinador');
await shot(s.page, '/panel', '02-hoy-coordinacion');
await shot(s.page, '/panel/solicitudes/2', '04-solicitud-por-aprobar');
await s.ctx.close();

s = await session('administrativo');
await shot(s.page, '/panel/calendario', '03-calendario', { wait: 2000 });
const diaConMontaje = await (async () => {
  for (let d = -10; d <= 10; d++) {
    const f = new Date(Date.now() + d * 86400000 - 5 * 3600000).toISOString().slice(0, 10);
    await s.page.goto(`${BASE}/panel/preparaciones?fecha=${f}`, { waitUntil: 'networkidle' });
    if (!(await s.page.content()).includes('No hay escenarios para esta fecha')) return f;
  }
  return '';
})();
await shot(s.page, `/panel/preparaciones?fecha=${diaConMontaje}`, '05-preparacion-de-escenarios');
await shot(s.page, '/panel/inventario', '06-inventario');
await s.ctx.close();

s = await session('admin');
await shot(s.page, '/panel/usuarios', '07-usuarios-y-roles');
await shot(s.page, '/admin/casos-clinicos', '08-administracion-casos-clinicos', { wait: 1500 });
await s.ctx.close();

s = await session('administrativo', { width: 390, height: 844 });
await shot(s.page, `/panel/preparaciones?fecha=${diaConMontaje}`, '09-celular-preparacion');
await s.ctx.close();

await browser.close();
