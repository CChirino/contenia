import { test, expect, type Page } from '@playwright/test';
import { execSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';

const SHOTS = '/tmp/onboarding';
mkdirSync(SHOTS, { recursive: true });

// ---------- helpers ----------

function artisan(code: string): string {
    const b64 = Buffer.from(code).toString('base64');
    const exec = `eval(base64_decode('${b64}'));`;
    return execSync(
        `docker compose exec -T -u sail laravel.test php artisan tinker --execute "${exec}"`,
        { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] },
    );
}

function dbQuery(sql: string): any[] {
    const out = artisan(`echo json_encode(DB::select(${JSON.stringify(sql)}));`);
    const last = out.trim().split('\n').pop() ?? '[]';
    return JSON.parse(last);
}

function dbExec(sql: string): void {
    artisan(`DB::statement(${JSON.stringify(sql)});`);
}

async function login(page: Page, email: string) {
    await page.goto('/login');
    await page.fill('#email', email);
    await page.fill('#password', 'password');
    await page.click('button:has-text("Log in")');
    await page.waitForURL((u) => !u.pathname.startsWith('/login'));
}

async function shot(page: Page, name: string) {
    await page.screenshot({ path: `${SHOTS}/${name}.png`, fullPage: false });
}

async function shotBoth(page: Page, name: string) {
    for (const theme of ['light', 'dark']) {
        await page.evaluate(
            (t) => (window as any).localStorage.setItem('theme', t),
            theme,
        );
        await page.reload();
        await page.waitForTimeout(400);
        await shot(page, `${name}-${theme}`);
    }
}

// ---------- datos ----------

// Misma forma que produce OnboardBrand::normalize (campos planos, estrategias en lista).
const FAKE_PROPOSAL = {
    description: 'Empresa de pruebas del asistente de inicio.',
    tone_of_voice: 'Cercano y profesional',
    audience: 'Equipo de QA',
    cta: 'Escríbenos',
    base_hashtags: ['wizard', 'testing'],
    pillars: [
        { name: 'Educativo', description: 'Contenido formativo', weight: 40 },
        { name: 'Marca', description: 'Identidad y producto', weight: 30 },
        { name: 'Comunidad', description: 'Cercanía con la audiencia', weight: 30 },
    ],
    strategies: [
        { network: 'instagram', posts_per_week: 3, formats: ['image'], focus: 'Marca visual' },
        { network: 'linkedin', posts_per_week: 2, formats: ['image'], focus: 'Autoridad' },
    ],
    palette: { dark: '#111111', light: '#FAFAFA', accent: '#FF6600', secondary: '#0066FF' },
    generated_at: new Date().toISOString(),
};

function cleanup() {
    artisan(
        "App\\Models\\User::whereIn('email',['wizard@test.local','wizard-op@test.local'])->get()->each->delete();" +
            "App\\Models\\Brand::where('slug','wizard-co')->get()->each->delete();",
    );
}

// ---------- tests ----------

test.describe.serial('Asistente de inicio', () => {
    test.beforeAll(() => {
        cleanup();
        artisan(
            "App\\Models\\User::updateOrCreate(['email'=>'wizard@test.local'],['name'=>'Wizard Admin','password'=>bcrypt('password'),'role'=>'admin','onboarded_at'=>null]);",
        );
        artisan(
            "App\\Models\\User::updateOrCreate(['email'=>'wizard-op@test.local'],['name'=>'Wizard Op','password'=>bcrypt('password'),'role'=>'operator','onboarded_at'=>null])->brands()->sync([2]);",
        );
        // La fila debe verificar la contraseña antes de intentar el login.
        const ok = artisan(
            "echo Hash::check('password', App\\Models\\User::where('email','wizard@test.local')->value('password')) ? 'OK' : 'BAD';",
        );
        if (!ok.includes('OK')) throw new Error('seed de usuario wizard falló: ' + ok);
    });

    test.afterAll(() => {
        cleanup();
    });

    test('recorrido A — admin crea una marca nueva con IA', async ({ page }) => {
        await login(page, 'wizard@test.local');

        // Existen marcas → elige camino en Choose.
        await expect(page.getByText('¿Por dónde empezamos?')).toBeVisible();
        await shotBoth(page, '00-choose');
        await page.getByText('Crear una marca nueva con IA').click();

        // Paso 1: bienvenida.
        await expect(page.getByText('en ~5 minutos')).toBeVisible();
        await shotBoth(page, '01-bienvenida');
        await page.getByRole('button', { name: 'Continuar →' }).click();

        // Paso 2: formulario de marca (shotBoth recarga: capturar antes de rellenar).
        await shotBoth(page, '02-marca');
        await page.fill('#ob_name', 'Wizard Co');
        await page.fill('#ob_web', 'https://wizard.example.com');
        await page.selectOption('#ob_plan', { index: 1 });
        await page.getByRole('button', { name: 'Crear marca' }).click();
        await expect(page.getByText('Marca «Wizard Co» lista')).toBeVisible();

        // Sembramos una propuesta fake en vez de llamar al LLM real.
        const brand = dbQuery("SELECT id FROM brands WHERE slug='wizard-co'")[0];
        dbExec(
            `UPDATE brands SET onboarding_proposal='${JSON.stringify(FAKE_PROPOSAL).replace(/'/g, "''")}' WHERE id=${brand.id}`,
        );
        // storeBrand persiste step=3: al recargar ya estamos en el paso 3.
        await page.reload();
        // Las 4 tarjetas de la propuesta deben renderizarse (no solo el botón).
        await expect(page.getByRole('heading', { name: 'Perfil de marca' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Pilares de contenido' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Estrategias por red' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Paleta' })).toBeVisible();
        await expect(page.getByText('Suma: 100 / 100')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Continuar →' })).toBeDisabled();
        await shotBoth(page, '03-propuesta');
        await page.getByRole('button', { name: 'Aplicar todo' }).click();
        await expect(page.getByText('Perfil y pilares aplicados')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Continuar →' })).toBeEnabled();
        await shotBoth(page, '03-aplicada');
        await page.getByRole('button', { name: 'Continuar →' }).click();

        // Paso 4: identidad + iframe de preview (debe responder 200).
        const previewResp = page.waitForResponse(
            (r) => r.url().includes('/onboarding/preview') && r.status() === 200,
            { timeout: 20_000 },
        );
        await expect(page.getByRole('heading', { name: 'Identidad' })).toBeVisible();
        await previewResp;
        // La paleta mostrada es la aplicada desde la propuesta, no la por defecto.
        await expect(page.getByText('#FF6600')).toBeVisible();
        await expect(page.getByText('#0066FF')).toBeVisible();
        await shotBoth(page, '04-identidad');
        await page.getByRole('button', { name: 'Continuar →' }).click();

        // Paso 5: aprobación ON → contactos.
        await expect(page.getByRole('heading', { name: '¿Quién aprueba' })).toBeVisible();
        await shotBoth(page, '05-contactos');
        await page.fill('#ob_cname', 'Cliente Wizard');
        await page.fill('#ob_cemail', 'cliente@wizard.example.com');
        await page.getByRole('button', { name: 'Añadir aprobador' }).click();
        await expect(page.getByText('Cliente Wizard')).toBeVisible();
        await shotBoth(page, '05-contacto-creado');
        await page.getByRole('button', { name: 'Continuar →' }).click();

        // Paso 6: "Lo haré después" → complete → grilla de wizard-co.
        await expect(page.getByRole('heading', { name: 'Tu primera grilla' })).toBeVisible();
        await shotBoth(page, '06-grilla');
        await page.getByText('Lo haré después').click();
        await page.waitForURL(/\/b\/wizard-co\/plans\//);

        const user = dbQuery("SELECT onboarded_at FROM users WHERE email='wizard@test.local'")[0];
        expect(user.onboarded_at).not.toBeNull();
    });

    test('recorrido B — operador asignado a Demo', async ({ page }) => {
        await login(page, 'wizard-op@test.local');

        await expect(page.getByRole('heading', { name: 'Bienvenido a Contenia' })).toBeVisible();
        await shotBoth(page, '10-op-intro');

        for (const name of ['11-op-grilla', '12-op-post', '13-op-aprobacion']) {
            await page.getByRole('button', { name: 'Continuar →' }).click();
            await page.waitForTimeout(300);
            await shotBoth(page, name);
        }

        await page.getByRole('button', { name: 'Continuar →' }).click();
        await expect(page.getByRole('heading', { name: 'Pautas' })).toBeVisible();
        await shotBoth(page, '14-op-pautas');

        await page.getByRole('button', { name: 'Empezar →' }).click();
        await page.waitForURL(/\/b\/demo\/plans\//);

        const user = dbQuery("SELECT onboarded_at FROM users WHERE email='wizard-op@test.local'")[0];
        expect(user.onboarded_at).not.toBeNull();
    });
});
