import { test, expect, type Page } from '@playwright/test';
import { execSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';

const SHOTS = '/tmp/from-media';
mkdirSync(SHOTS, { recursive: true });

function artisan(code: string): string {
    const b64 = Buffer.from(code).toString('base64');
    const exec = `eval(base64_decode('${b64}'));`;
    return execSync(
        `docker compose exec -T -u sail laravel.test php artisan tinker --execute "${exec}"`,
        { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] },
    );
}

function dbExec(sql: string): void {
    artisan(`DB::statement(${JSON.stringify(sql)});`);
}

async function login(page: Page) {
    await page.goto('/login');
    await page.fill('#email', 'admin@sabere.com');
    await page.fill('#password', 'password');
    await page.click('button:has-text("Log in")');
    await page.waitForURL((u) => !u.pathname.startsWith('/login'));
}

async function shot(page: Page, name: string) {
    await page.screenshot({ path: `${SHOTS}/${name}.png` });
}

// PNG de 2x2 generado en el test (sin depender de archivos).
const TINY_PNG = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64',
);

test.describe.serial('Posts desde archivo', () => {
    test('subir imagen crea la tarjeta "Analizando…" y el drawer muestra el material', async ({ page }) => {
        await login(page);
        await page.goto('/b/demo/plans/2026/11');

        // El modal valida video + LinkedIn en cliente.
        await page.getByRole('button', { name: '+ Desde archivo' }).first().click();
        const modal = page.locator('.max-w-lg');
        await modal.locator('input[type=file]').setInputFiles({
            name: 'clip.mp4',
            mimeType: 'video/mp4',
            buffer: Buffer.alloc(2048),
        });
        const linkedin = modal.getByRole('button', { name: 'LinkedIn', exact: true });
        await expect(linkedin).toBeDisabled();
        await shot(page, 'e2e-modal-linkedin-deshabilitado');

        // Imagen válida → Instagram.
        await modal.locator('input[type=file]').setInputFiles({
            name: 'foto.png',
            mimeType: 'image/png',
            buffer: TINY_PNG,
        });
        await modal.getByRole('button', { name: 'Instagram' }).click();
        await shot(page, 'e2e-modal-foto');
        await modal.getByRole('button', { name: 'Crear post' }).click();

        // Tarjeta nueva con el icono de clip (media_source=upload). El texto
        // puede seguir en "Analizando…" o ya venir de la IA: se busca el clip.
        const card = page.locator('button:has(svg[title="Desde archivo"])').last();
        await expect(card).toBeVisible({ timeout: 40_000 });
        await shot(page, 'e2e-grilla-analizando');

        // Drawer: chip "Desde archivo" + vista previa del material.
        await card.click();
        await expect(page.getByText('Desde archivo ·')).toBeVisible();
        await expect(page.getByText('Material original')).toBeVisible();
        await shot(page, 'e2e-drawer-upload');

        // Limpieza: el post de prueba no queda en la grilla de Demo.
        dbExec(
            "DELETE FROM posts WHERE media_source='upload' AND source_path LIKE '%source.png'",
        );
    });
});
