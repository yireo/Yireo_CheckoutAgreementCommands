import {test, defineCheckoutAgreementTests} from '@yireo-checkoutagreementcommands/checkout-agreement-commands';

/**
 * Creates agreements with checkout_agreement:create and checks them in the LokiCheckout of the Hyva theme
 *
 * See checkout-agreements-test.spec.ts for Luma
 */
test.describe('Checkout agreement commands (Hyva)', function () {
    defineCheckoutAgreementTests('hyva');
});
