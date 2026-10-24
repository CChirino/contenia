import { test, expect, type Page } from '@playwright/test';
import { execSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';

const SHOTS = '/tmp/e2e-fase4';
mkdirSync(SHOTS, { recursive: true });

// ---------- helpers ----------

function artisan(code: string): string {
    const b64 = Buffer.from(code).toString('base64');
    const exec = `eval(base64_decode('${b64}'));`;
    try {
        return execSync(
            `docker compose exec -T -u sail laravel.test php artisan tinker --execute "${exec}"`,
            { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] },
        );
    } catch {
        return execSync(`php artisan tinker --execute "${exec}"`, { encoding: 'utf8' });
    }
}

function dbQuery(sql: string): any[] {
    const out = artisan(`echo json_encode(DB::select(${JSON.stringify(sql)}));`);
    const last = out.trim().split('\n').pop() ?? '[]';
    return JSON.parse(last);
}

function dbExec(sql: string): void {
    artisan(`DB::statement(${JSON.stringify(sql)});`);
}

function queueLog(): string {
    try {
        return execSync('docker compose logs queue --tail=60 2>&1', { encoding: 'utf8' });
    } catch {
        return '(docker compose logs no disponible)';
    }
}

async function login(page: Page) {
    await page.goto('/login');
    await page.fill('#email', 'admin@sabere.com');
    await page.fill('#password', 'password');
    await page.click('button:has-text("Log in")');
    await page.waitForURL((u) => !u.pathname.startsWith('/login'));
}

async function shot(page: Page, name: string) {
    await page.screenshot({ path: `${SHOTS}/${name}.png`, fullPage: false });
}

// ---------- tests ----------

const MANUAL_GUIDELINE = `E2E pauta manual ${Date.now()}: cierra siempre el copy con una pregunta`;

test.describe.serial('Fase 4 — onboarding IA, pautas, memoria y prompt', () => {
    test.beforeAll(() => {
        // Datos de apoyo para las transiciones de pautas de Saberé.
        // Se resetean en cada corrida para que el spec sea reutilizable.
        dbExec("delete from brand_guidelines where brand_id=1 and (text like 'E2E seed%' or text like 'E2E pauta manual%')");
        dbExec(`insert into brand_guidelines
            (brand_id, text, source, status, weight, evidence_count, created_at, updated_at) values
            (1, 'E2E seed: activar pendiente', 'ai_distilled', 'pending', 1, 1, now(), now()),
            (1, 'E2E seed: descartar pendiente', 'ai_distilled', 'pending', 1, 1, now(), now()),
            (1, 'E2E seed: retiro propuesto a mantener', 'ai_distilled', 'pending_retire', 2, 3, now(), now()),
            (1, 'E2E seed: historial a eliminar', 'ai_distilled', 'discarded', 1, 2, now(), now())`);
    });

    test('1. Propuesta de onboarding de Demo se aplica por secciones', async ({ page }) => {
        test.setTimeout(180_000);
        await login(page);
        page.on('dialog', (d) => d.accept());

        await page.goto('/brands/2/edit');

        // Si la propuesta ya fue aplicada por completo (p. ej. en una corrida
        // previa), el banner cambia a "configurada con ayuda de IA".
        if (!(await page.getByText('Propuesta generada').first().isVisible().catch(() => false))) {
            await expect(page.getByText('configurada con ayuda de IA')).toBeVisible();
            await shot(page, '04-onboarding-completo');
            const done = dbQuery(
                'select count(*) c, sum(weight) s from content_pillars where brand_id=2',
            )[0];
            expect(Number(done.c)).toBeGreaterThanOrEqual(2);
            expect(Number(done.s)).toBe(100);
            expect(dbQuery('select onboarded_at from brands where id=2')[0].onboarded_at).not.toBeNull();
            return;
        }
        await shot(page, '01-onboarding-propuesta');

        // --- Pilares: cambiar un peso y compensar otro para seguir en 100 ---
        const pillars = page.locator('section', { hasText: 'Pilares de contenido' }).last();
        const weights = pillars.locator('input[type="number"]');
        const count = await weights.count();
        expect(count).toBeGreaterThanOrEqual(2);
        const w0 = Number(await weights.nth(0).inputValue());
        const w1 = Number(await weights.nth(1).inputValue());
        const delta = w1 >= 10 ? 10 : -10;
        const newW0 = w0 + delta;
        const newW1 = w1 - delta;
        await weights.nth(0).fill(String(newW0));
        await weights.nth(1).fill(String(newW1));
        await expect(pillars.getByText('Suma: 100 / 100')).toBeVisible();
        await shot(page, '02-onboarding-pilares-editados');

        await pillars.getByRole('button', { name: 'Aplicar pilares' }).click();
        await expect(pillars.getByText('Aplicada ✓')).toBeVisible();
        await shot(page, '03-onboarding-pilares-aplicados');

        // --- Estrategias ---
        const strategies = page.locator('section', { hasText: 'Estrategias' }).last();
        await strategies.getByRole('button', { name: 'Aplicar estrategias' }).click();
        await expect(strategies.getByText('Aplicada ✓')).toBeVisible();

        // --- Paleta ---
        const palette = page.locator('section', { hasText: 'Paleta' }).last();
        await palette.getByRole('button', { name: 'Aplicar paleta' }).click();
        await expect(palette.getByText('Aplicada ✓')).toBeVisible();

        // --- Perfil: si no está aplicada, aplicarla ---
        const profile = page.locator('section', { hasText: 'Perfil' }).first();
        if (!(await profile.getByText('Aplicada ✓').isVisible().catch(() => false))) {
            await profile.getByRole('button', { name: 'Aplicar perfil' }).click();
        }
        await expect(profile.getByText('Aplicada ✓')).toBeVisible();

        // --- Las 4 aplicadas → banner final ---
        await expect(page.getByText('configurada con ayuda de IA')).toBeVisible();
        await shot(page, '04-onboarding-completo');

        // --- BD: pilares de brand 2 coinciden con lo editado y suman 100 ---
        const rows = dbQuery('select weight from content_pillars where brand_id=2 order by id');
        const dbWeights = rows.map((r: any) => Number(r.weight));
        expect(dbWeights[0]).toBe(newW0);
        expect(dbWeights[1]).toBe(newW1);
        expect(dbWeights.reduce((a, b) => a + b, 0)).toBe(100);
        const onboarded = dbQuery('select onboarded_at from brands where id=2')[0].onboarded_at;
        expect(onboarded).not.toBeNull();
    });

    test('2. Pautas de Saberé: CRUD y transiciones', async ({ page }) => {
        await login(page);
        page.on('dialog', (d) => d.accept());
        await page.goto('/b/sabere/settings/guidelines');
        await expect(page.getByRole('heading', { name: 'Añadir pauta' })).toBeVisible();

        const card = (title: string) =>
            page.locator(`div.bg-surface:has(> h3:text-is("${title}"))`);

        // Añadir a mano → aparece en Activas
        await page.locator('textarea').first().fill(MANUAL_GUIDELINE);
        await page.getByRole('button', { name: '+ Añadir pauta' }).click();
        const activas = card('Pautas activas');
        await expect(activas.getByText(MANUAL_GUIDELINE)).toBeVisible();
        await shot(page, '05-guidelines-manual-activa');

        // Activar una pendiente
        const pendientes = card('Pendientes de revisar');
        const pendingRow = pendientes.locator('li', { hasText: 'E2E seed: activar pendiente' }).first();
        await pendingRow.getByRole('button', { name: '✓ Activar' }).click();
        await expect(activas.getByText('E2E seed: activar pendiente')).toBeVisible();
        await shot(page, '06-guidelines-activada');

        // Descartar otra pendiente
        await pendientes
            .locator('li', { hasText: 'E2E seed: descartar pendiente' })
            .first()
            .getByRole('button', { name: '✗ Descartar' })
            .click();
        await expect(pendientes.getByText('E2E seed: descartar pendiente')).not.toBeVisible();
        await shot(page, '07-guidelines-descartada');

        // Mantener un retiro propuesto → vuelve a Activas
        await card('Retiros propuestos')
            .locator('li', { hasText: 'E2E seed: retiro propuesto a mantener' })
            .first()
            .getByRole('button', { name: 'Mantener' })
            .click();
        await expect(activas.getByText('E2E seed: retiro propuesto a mantener')).toBeVisible();

        // Editar el texto de una activa (en edición el <p> pasa a ser un <input>,
        // así que la fila se localiza por el input, no por su texto).
        const manualRow = activas.locator('li', { hasText: 'E2E pauta manual' }).first();
        await manualRow.getByRole('button', { name: 'Editar' }).click();
        const editingRow = activas.locator('li:has(input)').first();
        await editingRow.locator('input').fill(`${MANUAL_GUIDELINE} (editada)`);
        await editingRow.getByRole('button', { name: 'Guardar' }).click();
        await expect(activas.getByText(`${MANUAL_GUIDELINE} (editada)`)).toBeVisible();
        await shot(page, '08-guidelines-editada');

        // Eliminar una del historial
        const before = dbQuery(
            "select count(*) c from brand_guidelines where brand_id=1 and text='E2E seed: historial a eliminar'",
        )[0].c;
        expect(before).toBe(1);
        await page
            .locator('li', { hasText: 'E2E seed: historial a eliminar' })
            .first()
            .getByRole('button', { name: 'Eliminar' })
            .click();
        await expect(page.getByText('E2E seed: historial a eliminar')).not.toBeVisible();

        // Conteo final en BD
        const counts = dbQuery(
            'select status, count(*) c from brand_guidelines where brand_id=1 group by status',
        );
        console.log('brand_guidelines brand 1:', JSON.stringify(counts));
        const active = counts.find((r: any) => r.status === 'active');
        expect(Number(active?.c ?? 0)).toBeGreaterThanOrEqual(3);
        await shot(page, '09-guidelines-final');
    });

    test('3. Memoria: post_edits y DistillFeedback desde el portal', async ({ page, browser }) => {
        test.setTimeout(240_000);
        await login(page);

        // Editar el copy de un post rendered de Demo
        await page.goto('/b/demo/plans/2026/10');
        await page.locator('button.bg-brand-tint').first().click();
        await expect(page.getByRole('button', { name: 'Editar' })).toBeVisible();
        await shot(page, '10-drawer-post');
        await page.getByRole('button', { name: 'Editar' }).click();
        const newCopy = `E2E copy editado ${Date.now()}`;
        await page.fill('#edit_copy', newCopy);
        await page.getByRole('button', { name: 'Guardar' }).click();
        await expect(page.getByText(newCopy)).toBeVisible();
        await shot(page, '11-post-editado');
        await page.keyboard.press('Escape'); // cierra el drawer (overlay fijo)

        const lastEdit = dbQuery(
            'select id, post_id, field from post_edits order by id desc limit 1',
        )[0];
        console.log('post_edits última fila:', JSON.stringify(lastEdit));
        expect(lastEdit.field).toBe('copy');

        // Token de la revisión abierta de Demo (rotar para obtenerlo en claro).
        // Si no hay ninguna abierta, se crea desde la UI con "Enviar a aprobación"
        // y se toma la URL del modal que muestra flash.review_url.
        let path = artisan(`$r = App\\Models\\ReviewRequest::withoutGlobalScopes()
            ->where('brand_id', 2)->whereNull('submitted_at')->whereNull('revoked_at')
            ->where('expires_at', '>', now())->latest('id')->first();
            if (! $r) { echo 'NONE'; } else { $t = $r->generateToken(); $r->save(); echo "/review/$t"; }`)
            .trim()
            .split('\n')
            .pop()!;
        if (path === 'NONE') {
            await page
                .getByRole('button', { name: 'Enviar a aprobación' })
                .first()
                .click();
            const urlText = await page.getByText('/review/').first().textContent();
            const m = urlText?.match(/\/review\/[A-Za-z0-9]+/);
            path = m ? m[0] : 'NONE';
        }
        console.log('review path:', path);
        expect(path).toMatch(/^\/review\//);

        // Portal público en contexto nuevo SIN sesión
        const ctx = await browser.newContext({ baseURL: 'http://localhost:5600' });
        const portal = await ctx.newPage();
        await portal.goto(path);
        await expect(portal.getByText(/decididos/)).toBeVisible();
        await shot(portal, '12-portal');

        // Rechazar el primer post con el comentario pedido
        await portal.getByRole('button', { name: 'Rechazar' }).first().click();
        await portal
            .locator('textarea[placeholder*="obligatorio"]')
            .fill('Usar una foto de personas reales, no de laptops');
        await portal.getByRole('button', { name: 'Confirmar rechazo' }).click();
        await shot(portal, '13-portal-rechazado');

        await portal.getByRole('button', { name: 'Enviar revisión' }).click();
        await expect(portal.getByText('Gracias, revisión enviada')).toBeVisible();
        await shot(portal, '14-portal-enviado');
        await ctx.close();

        // DistillFeedback despachado → esperar procesamiento (OpenRouter puede 429)
        const pendingBefore = Number(
            dbQuery(
                "select count(*) c from brand_guidelines where brand_id=2 and status='pending'",
            )[0].c,
        );
        let pendingAfter = pendingBefore;
        const deadline = Date.now() + 90_000;
        while (Date.now() < deadline) {
            pendingAfter = Number(
                dbQuery(
                    "select count(*) c from brand_guidelines where brand_id=2 and status='pending'",
                )[0].c,
            );
            const failed = dbQuery(
                "select count(*) c from failed_jobs where payload like '%DistillFeedback%'",
            )[0].c;
            if (pendingAfter > pendingBefore || Number(failed) > 0) break;
            await new Promise((r) => setTimeout(r, 5000));
        }
        console.log('QUEUE LOG TAIL:\n' + queueLog());
        console.log(`pautas pendientes Demo: antes=${pendingBefore} después=${pendingAfter}`);
        console.log(
            'failed_jobs DistillFeedback:',
            JSON.stringify(
                dbQuery(
                    "select id, failed_at, substr(exception,1,300) err from failed_jobs where payload like '%DistillFeedback%' order by id desc limit 1",
                ),
            ),
        );

        if (pendingAfter > pendingBefore) {
            await page.goto('/b/demo/settings/guidelines');
            await expect(
                page.locator('div.bg-surface:has(> h3:text-is("Pendientes de revisar"))')
                    .locator('li')
                    .first(),
            ).toBeVisible();
            await shot(page, '15-guidelines-demo-distilladas');
        }
        // Assert mínimo: el ítem quedó rechazado con su comentario
        const item = dbQuery(
            `select ri.post_id, ri.decision, ri.comment from review_items ri
             where ri.comment = 'Usar una foto de personas reales, no de laptops'
             order by ri.id desc limit 1`,
        )[0];
        expect(item?.decision).toBe('rejected');
    });

    test('4. Las pautas activas se inyectan en el system prompt', () => {
        const prompt = artisan(`app()->instance('brand', App\\Models\\Brand::find(1));
            $pb = app(App\\Services\\Planning\\PromptBuilder::class);
            $m = new ReflectionMethod($pb, 'systemPrompt');
            $m->setAccessible(true);
            echo $m->invoke($pb);`);
        console.log('system prompt (tail):', prompt.slice(-800));
        expect(prompt).toContain('Pautas aprendidas de esta marca');
        expect(prompt).toContain('E2E pauta manual');
        expect(prompt).toContain('(editada)');
    });
});
