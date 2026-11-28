import {test, expect, Locator, Page} from '@playwright/test';
import {spawnSync} from 'child_process';

export {test, expect};

export const HYVA_THEME = 'Hyva/default-csp';
export const LUMA_THEME = 'Magento/luma';

export type Theme = 'luma' | 'hyva';

export type AgreementOptions = Record<string, string>;

const SKU = 'checkout-agreement-commands-product';

const token = () => process.env.TEST_TOKEN;

export const uniqueText = (prefix: string): string =>
    `${prefix} ${Date.now()}-${Math.floor(Math.random() * 100000)}`;

/**
 * Run bin/magento: Through `docker exec` when PHP lives in another container (PHP_CONTAINER, GitHub Actions),
 * otherwise directly (Hersir, where the Playwright container shares the Magento volume and has PHP)
 */
export function runMagento(args: string[]): { status: number, output: string } {
    const magentoRoot = process.env.MAGENTO_ROOT || '/tmp/magento';
    const phpContainer = (process.env.PHP_CONTAINER ?? '').trim();
    const magentoArgs = ['-d', 'memory_limit=-1', magentoRoot + '/bin/magento', ...args, '--no-ansi'];

    const result = phpContainer
        ? spawnSync('docker', ['exec', '-u', 'root', '-w', magentoRoot, phpContainer, 'php', ...magentoArgs], {encoding: 'utf8'})
        : spawnSync('php', magentoArgs, {encoding: 'utf8', cwd: magentoRoot});

    if (result.error) {
        throw result.error;
    }

    return {status: result.status ?? 1, output: (result.stdout ?? '') + (result.stderr ?? '')};
}

export function createAgreement(options: AgreementOptions): number {
    const result = runMagento([
        'checkout_agreement:create',
        ...Object.entries(options).map(([name, value]) => `--${name}=${value}`),
    ]);

    expect(result.status, result.output).toBe(0);
    const match = result.output.match(/Created agreement with ID (\d+)/);
    expect(match, result.output).not.toBeNull();

    return Number(match![1]);
}

/**
 * Luma is tested with the core Magento checkout (also when LokiCheckout is installed), Hyva with LokiCheckout
 */
export async function configureStore(page: Page, theme: Theme) {
    const themeName = theme === 'hyva' ? HYVA_THEME : LUMA_THEME;
    const response = await page.request.post('/loki_functional_tests/index/configure?token=' + token(), {
        form: {
            config: JSON.stringify({
                theme: themeName,
                product: {sku: SKU, name: 'CheckoutAgreementCommands Product', price: 10, tax_class_id: 0},
                config: {
                    'checkout/options/enable_agreements': 1,
                    'carriers/flatrate/active': 1,
                    'payment/checkmo/active': 1,
                    'loki_checkout/general/enable': theme === 'hyva' ? 1 : 0,
                    'loki_checkout/general/theme': 'onestep',
                },
            }),
        },
    });

    const body = await response.text();
    let data: any;
    try {
        data = JSON.parse(body);
    } catch (error) {
        throw new Error(`Configure endpoint returned no JSON (status ${response.status()}): ${body.slice(0, 500)}`);
    }

    if (data.error) {
        throw new Error('Configure error: ' + data.error);
    }

    const themeErrors: string[] = data.result?.theme?.errors ?? [];
    test.skip(themeErrors.some(error => error.includes('not found')), `Theme "${themeName}" is not installed`);
    expect(themeErrors, 'Configure theme errors').toEqual([]);
}

async function addToCartAndOpenCheckout(page: Page, theme: Theme) {
    const config = theme === 'hyva' ? {sku: SKU, profile: 'netherlands'} : {sku: SKU};
    const response = await page.goto('/loki_functional_tests/index/addtocart?redirect=1&qty=1&token='
        + encodeURIComponent(token() ?? '') + '&config=' + encodeURIComponent(JSON.stringify(config)));

    expect(response?.ok(), 'Add to cart response').toBeTruthy();
    await expect(page).toHaveURL(/\/checkout\/?(#.*)?$/);
}

const lumaShippingForm = (page: Page): Locator => page.locator('#co-shipping-form');

async function openLumaPaymentStep(page: Page) {
    await expect(page.locator('#customer-email')).toBeVisible({timeout: 30000});
    await page.locator('#customer-email').fill('checkout-agreement-commands@example.com');

    const form = lumaShippingForm(page);
    await form.locator('[name="firstname"]').fill('Jane');
    await form.locator('[name="lastname"]').fill('Doe');
    await form.locator('[name="street[0]"]').fill('1 Congress Avenue');
    await form.locator('[name="country_id"]').selectOption('US');
    await form.locator('[name="region_id"]').selectOption({label: 'Texas'});
    await form.locator('[name="city"]').fill('Austin');
    await form.locator('[name="postcode"]').fill('78701');
    await form.locator('[name="telephone"]').fill('0123456789');

    const flatrate = page.locator('input[value="flatrate_flatrate"]');
    await expect(flatrate).toBeVisible({timeout: 30000});
    await flatrate.check();
    await page.locator('#shipping-method-buttons-container button[data-role="opc-continue"]').click();

    await expect(page).toHaveURL(/#payment$/, {timeout: 30000});
    const checkmo = page.locator('input#checkmo');
    await expect(checkmo).toBeAttached({timeout: 30000});
    if (await checkmo.isVisible()) {
        await checkmo.check();
    }

    await expect(lumaPaymentMethod(page)).toBeVisible();
}

const lumaPaymentMethod = (page: Page): Locator => page.locator('.payment-method._active');

/**
 * Open the checkout with a product in the cart, up to the point where the agreements are shown
 *
 * Hyva only ships a checkout via LokiCheckout, so the Hyva tests are skipped when LokiCheckout is missing
 */
export async function openCheckout(page: Page, theme: Theme) {
    await configureStore(page, theme);
    await addToCartAndOpenCheckout(page, theme);

    if (theme === 'luma') {
        await openLumaPaymentStep(page);
        return;
    }

    await page.waitForLoadState('domcontentloaded');
    const isLokiCheckout = await page.locator('[x-data^="LokiCheckout"]').count() > 0;
    test.skip(!isLokiCheckout, 'The Hyva checkout requires LokiCheckout, which is not installed');
}

/**
 * The container of a single agreement, identified by its checkbox text
 */
export function agreement(page: Page, theme: Theme, checkboxText: string): Locator {
    if (theme === 'luma') {
        return lumaPaymentMethod(page).locator('.checkout-agreement').filter({hasText: checkboxText});
    }

    return page.locator('[x-data="LokiCheckoutAgreementComponent"]').filter({hasText: checkboxText});
}

export async function openAgreementContent(page: Page, theme: Theme, checkboxText: string): Promise<Locator> {
    if (theme === 'luma') {
        await agreement(page, theme, checkboxText).locator('button.action-show').click();
        const content = page.locator('.modal-popup._show .checkout-agreements-item-content');
        await expect(content).toBeVisible();
        return content;
    }

    const container = agreement(page, theme, checkboxText);
    await container.getByText('View terms').click();
    const content = container.locator('dialog');
    await expect(content).toBeVisible();
    return content;
}

/**
 * Scenarios that both the core Magento checkout (Luma) and LokiCheckout (Hyva) support
 */
export function defineCheckoutAgreementTests(theme: Theme) {
    test('shows active agreements for all store views and for the default store view', async function ({page}) {
        const allStoresText = uniqueText('All stores agreement');
        const storeText = uniqueText('Store agreement');
        createAgreement({name: allStoresText, content: 'Terms', checkbox_text: allStoresText});
        createAgreement({name: storeText, content: 'Terms', checkbox_text: storeText, store_id: '1'});

        await openCheckout(page, theme);

        for (const text of [allStoresText, storeText]) {
            await expect(agreement(page, theme, text)).toBeVisible();
            await expect(agreement(page, theme, text).locator('input[type="checkbox"]')).toBeVisible();
            await expect(agreement(page, theme, text).locator('input[type="checkbox"]')).not.toBeChecked();
        }
    });

    test('does not show an inactive agreement', async function ({page}) {
        const activeText = uniqueText('Active agreement');
        const inactiveText = uniqueText('Inactive agreement');
        createAgreement({name: activeText, content: 'Terms', checkbox_text: activeText});
        createAgreement({name: inactiveText, content: 'Terms', checkbox_text: inactiveText, is_active: '0'});

        await openCheckout(page, theme);

        await expect(agreement(page, theme, activeText)).toBeVisible();
        await expect(agreement(page, theme, inactiveText)).toHaveCount(0);
    });

    test('renders plain text content escaped', async function ({page}) {
        const text = uniqueText('Plain text agreement');
        createAgreement({name: text, content: '<b>Plain terms</b>', checkbox_text: text});

        await openCheckout(page, theme);

        const content = await openAgreementContent(page, theme, text);
        await expect(content).toContainText('<b>Plain terms</b>');
        await expect(content.locator('b')).toHaveCount(0);
    });

    test('renders HTML content as HTML', async function ({page}) {
        const text = uniqueText('HTML agreement');
        createAgreement({name: text, content: '<b>HTML terms</b>', checkbox_text: text, is_html: '1'});

        await openCheckout(page, theme);

        const content = await openAgreementContent(page, theme, text);
        await expect(content.locator('b')).toHaveText('HTML terms');
        await expect(content).not.toContainText('<b>');
    });
}

/**
 * Scenarios that depend on the agreement mode, which only the core Magento checkout (Luma) supports
 */
export function defineLumaCheckoutAgreementModeTests() {
    const theme: Theme = 'luma';

    test('shows an automatic agreement without checkbox', async function ({page}) {
        const text = uniqueText('Automatic agreement');
        createAgreement({name: text, content: 'Terms', checkbox_text: text, mode: '0'});

        await openCheckout(page, theme);

        await expect(agreement(page, theme, text)).toBeVisible();
        await expect(agreement(page, theme, text).locator('input[type="checkbox"]')).toHaveCount(0);
    });

    test('requires a manual agreement to be checked before placing the order', async function ({page}) {
        const text = uniqueText('Manual agreement');
        createAgreement({name: text, content: 'Terms', checkbox_text: text});

        await openCheckout(page, theme);

        const placeOrderButton = lumaPaymentMethod(page).locator('button.action.checkout');
        await placeOrderButton.click();
        await expect(agreement(page, theme, text).locator('div.mage-error')).toContainText('This is a required field.');
        await expect(page).not.toHaveURL(/\/checkout\/onepage\/success/);

        for (const checkbox of await lumaPaymentMethod(page).locator('.checkout-agreement input[type="checkbox"]').all()) {
            await checkbox.check();
        }

        await placeOrderButton.click();
        await expect(page).toHaveURL(/\/checkout\/onepage\/success/, {timeout: 30000});
    });
}
