// Destructure required WordPress packages
const { createElement: smepfowoEl, Fragment } = window.wp.element;
const { __ } = window.wp.i18n;

// Get localized logo from PHP
const smepfowoLogo = window.smepfowoCheckoutData?.imgUrl || '';

// Translatable label and description
const smepfowoLabelText = __('SMEPay for WooCommerce', 'smepay-for-woocommerce');
const smepfowoContent = () => __('Pay securely using SMEPay UPI.', 'smepay-for-woocommerce');

// Get URL parameters
const urlParams = new URLSearchParams(window.location.search);
const smepfowoSlug = urlParams.get('smepay_slug');
const smepfowoRedirectUrl = urlParams.get('redirect_url');

// Register SMEPay payment method for block-based checkout
const smepfowoBlockGateway = {
    name: 'smepfowo',
    label: smepfowoEl(Fragment, null,
        smepfowoLabelText,
        smepfowoEl('img', {
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
    content: smepfowoEl('div', null, smepfowoContent()),
    edit: smepfowoEl('div', null, smepfowoContent()),
    canMakePayment: () => true,
    ariaLabel: smepfowoLabelText,
    supports: {
        features: ['products'],
    },
};

// Register payment method with WooCommerce Blocks
window.wc.wcBlocksRegistry.registerPaymentMethod(smepfowoBlockGateway);

// Determine if order was already paid
const smepfowoOrderPaid = window.smepfowoCheckoutData?.orderPaid || false;

// Trigger SMEPay widget
const smepfowoTriggerPaymentWidget = () => {
    if (smepfowoOrderPaid) {
        console.log(__('Order is already paid. SMEPay widget will not be triggered.', 'smepay-for-woocommerce'));
        return;
    }

    if (smepfowoSlug) {
        window.smepayCheckout({
            slug: smepfowoSlug,
            onSuccess: function () {
                window.location.href = smepfowoRedirectUrl + `&smepay_slug=${smepfowoSlug}`;
            },
            onFailure: function () {
                console.warn(__('SMEPay widget closed or failed.', 'smepay-for-woocommerce'));
            }
        });
    } else {
        console.log(__('SMEPay slug missing. Widget will not trigger.', 'smepay-for-woocommerce'));
    }
};

// Auto-select payment method and trigger widget on page load
window.addEventListener('load', () => {
    if (smepfowoSlug) {
        const input = document.querySelector('input[name="payment_method"][value="smepfowo"]');
        if (input) {
            input.checked = true;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
        setTimeout(smepfowoTriggerPaymentWidget, 500);
    }
});

// Watch for user selection of SMEPay in the payment options
document.addEventListener('change', (e) => {
    if (
        e.target &&
        e.target.matches('input[name="payment_method"][value="smepfowo"]')
    ) {
        smepfowoTriggerPaymentWidget();
    }
});