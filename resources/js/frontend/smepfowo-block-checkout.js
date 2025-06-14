// Destructure required WordPress packages
const { createElement: smepayEl, Fragment } = window.wp.element;
const { __ } = window.wp.i18n;

// Get localized logo from PHP
const smepfowoLogo = window.smepfowoCheckoutData?.imgUrl || '';

// Translatable label and description
const smepfowoLabelText = __('SMEPay for WooCommerce', 'smepay-for-woocommerce');
const smepayContent = () => __('Pay securely using SMEPay UPI.', 'smepay-for-woocommerce');

// Get URL parameters
const urlParams = new URLSearchParams(window.location.search);
const smepaySlug = urlParams.get('smepay_slug');
const smepayRedirectUrl = urlParams.get('redirect_url');

// Register SMEPay payment method for block checkout
const smepfowoBlockGateway = {
    name: 'smepfowo',
    label: smepayEl(Fragment, null,
        smepfowoLabelText,
        smepayEl('img', {
            src: smepfowoLogo,
            alt: __('SMEPay Logo', 'smepay-for-woocommerce'),
            style: {
                height: '20px',
                marginLeft: '8px',
                verticalAlign: 'middle',
                display: 'inline-block',
            }
        })
    ),
    content: smepayEl('div', null, smepayContent()),
    edit: smepayEl('div', null, smepayContent()),
    canMakePayment: () => true,
    ariaLabel: smepfowoLabelText,
    supports: {
        features: ['products'],
    },
};

window.wc.wcBlocksRegistry.registerPaymentMethod(smepfowoBlockGateway);

// Determine if order was already paid
const orderPaid = window.smepfowoCheckoutData?.orderPaid || false;

// Trigger SMEPay widget if needed
const triggerSMEPayIfSelected = () => {
    if (orderPaid) {
        console.log(__('Order is already paid. SMEPay widget will not be triggered.', 'smepay-for-woocommerce'));
        return;
    }

    if (smepaySlug) {
        window.smepayCheckout({
            slug: smepaySlug,
            onSuccess: function () {
                // Redirect after success
                window.location.href = smepayRedirectUrl + `&smepay_slug=${smepaySlug}`;
            },
            onFailure: function () {
                console.warn(__('SMEPay widget closed or failed.', 'smepay-for-woocommerce'));
            }
        });
    } else {
        console.log(__('SMEPay slug missing. Widget will not trigger.', 'smepay-for-woocommerce'));
    }
};

// On load: auto-select SMEPay if slug is present
window.addEventListener('load', () => {
    if (smepaySlug) {
        const input = document.querySelector('input[name="payment_method"][value="smepfowo"]');
        if (input) {
            input.checked = true;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
        setTimeout(triggerSMEPayIfSelected, 500);
    }
});

// Watch for user selecting SMEPay payment method
document.addEventListener('change', (e) => {
    if (e.target && e.target.matches('input[name="payment_method"][value="smepfowo"]')) {
        triggerSMEPayIfSelected();
    }
});