import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';

// Paridad PHP↔TS: con los casos fijos que también usa BrandPaletteTest
// (PHP), la tira "Así se usarán en los diseños" del editor debe calcular
// exactamente los mismos roles. No guarda nada: solo edita el formulario.
const cases: {
    name: string;
    in: { primary: string[]; secondary: string[] };
    roles: Record<string, string>;
}[] = JSON.parse(readFileSync('tests/fixtures/palette_cases.json', 'utf-8'));

test('editor de paleta calcula los mismos roles que PHP', async ({ page }) => {
    await page.goto('http://localhost:5600/login');
    await page.fill('#email', 'admin@sabere.com');
    await page.fill('#password', 'password');
    await page.click('button:has-text("Log in")');
    await page.waitForURL((u) => !u.pathname.startsWith('/login'));

    // Marca Demo (id 2 en los datos semilla).
    await page.goto('http://localhost:5600/brands/2/edit');

    const editor = page.locator('[data-palette-editor]').first();
    await expect(editor.getByText('Colores primarios')).toBeVisible();

    for (const caso of cases) {
        while (await editor.getByTitle('Quitar').count()) {
            await editor.getByTitle('Quitar').first().click();
        }
        for (const c of caso.in.secondary) {
            await editor.getByRole('button', { name: '+ Añadir' }).click();
            await editor.locator('[data-secondary] input[type=text]').last().fill(c);
        }
        for (let i = 0; i < 3; i++) {
            await editor.locator('[data-primary] input[type=text]').nth(i).fill(caso.in.primary[i]);
        }

        const strip = editor.locator('[data-role="palette-role"]');
        await expect(strip).toHaveCount(4);
        const order = ['dark', 'light', 'accent', 'secondary'];
        for (let i = 0; i < 4; i++) {
            await expect(strip.nth(i), `${caso.name} · ${order[i]}`).toHaveAttribute('data-color', caso.roles[order[i]]);
        }
    }

    await page.screenshot({ path: '/tmp/palette/editor-1280.png', fullPage: false });
});
