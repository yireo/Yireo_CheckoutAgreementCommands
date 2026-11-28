import {
    test,
    defineCheckoutAgreementTests,
    defineLumaCheckoutAgreementModeTests,
} from '@yireo-checkoutagreementcommands/checkout-agreement-commands';

/**
 * Creates agreements with checkout_agreement:create and checks them in the core Magento checkout of the Luma theme
 *
 * See checkout-agreements-hyva-test.spec.ts for Hyva
 */
test.describe('Checkout agreement commands (Luma)', function () {
    defineCheckoutAgreementTests('luma');
    defineLumaCheckoutAgreementModeTests();
});
