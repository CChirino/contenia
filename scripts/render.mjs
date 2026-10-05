// Renderiza frames PNG de una plantilla con Chromium headless.
// Uso: node scripts/render.mjs --url-template "http://localhost/render/123/{frame}?signature=..." \
//        --out-dir storage/app/assets/123 --width 1200 --height 1200 --frames 1 [--timeout 60000]
// La plantilla debe setear window.__RENDER_READY__ = true cuando esté lista.

import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
import { parseArgs } from 'node:util';
import path from 'node:path';

const { values: args } = parseArgs({
    options: {
        'url-template': { type: 'string' },
        url: { type: 'string' }, // alias: usa {frame} o se comporta como url-template
        'out-dir': { type: 'string' },
        width: { type: 'string', default: '1080' },
        height: { type: 'string', default: '1350' },
        frames: { type: 'string', default: '1' },
        timeout: { type: 'string', default: '60000' },
    },
});

const urlTemplate = args['url-template'] ?? args.url;
const outDir = args['out-dir'];
const width = parseInt(args.width, 10);
const height = parseInt(args.height, 10);
const frames = parseInt(args.frames, 10);
const timeout = parseInt(args.timeout, 10);

if (!urlTemplate || !outDir) {
    console.error('Uso: render.mjs --url-template <url con {frame}> --out-dir <dir> [--width --height --frames --timeout]');
    process.exit(2);
}

const urlFor = (frame) =>
    urlTemplate.includes('{frame}')
        ? urlTemplate.replace('{frame}', String(frame))
        : `${urlTemplate}&frame=${frame}`;

mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch();

try {
    const page = await browser.newPage({
        viewport: { width, height },
        deviceScaleFactor: 1,
    });

    for (let i = 1; i <= frames; i++) {
        const out = path.join(outDir, `frame-${String(i).padStart(2, '0')}.png`);

        await page.goto(urlFor(i), { waitUntil: 'networkidle', timeout });
        await page.waitForFunction(() => window.__RENDER_READY__ === true, null, { timeout });
        await page.screenshot({ path: out, clip: { x: 0, y: 0, width, height } });

        console.log(`frame ${i} -> ${out}`);
    }
} catch (error) {
    console.error(`Render fallido: ${error.message}`);
    await browser.close();
    process.exit(1);
}

await browser.close();
