const smepfowoLabelText = 'SMEPay for WooCommerce';
const smepfowoLogo = window.smepfowoCheckoutData?.imgUrl || '';
const { createElement: smepayEl, Fragment } = window.wp.element;

// Get slug from URL
const urlParams = new URLSearchParams(window.location.search);
const smepaySlug = urlParams.get('smepay_slug');
const smepayRedirectUrl = urlParams.get('redirect_url');

// Your payment description
const smepayContent = () => 'Pay securely using SMEPay UPI.';

// Register SMEPay payment method
const smepfowoBlockGateway = {
    name: 'smepfowo',
    label: smepayEl(Fragment, null,
        smepfowoLabelText,
        smepayEl('img', {
            src: smepfowoLogo,
            alt: 'SMEPay Logo',
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

const orderPaid = window.smepfowoCheckoutData?.orderPaid || false;

// Trigger SMEPay widget if smepaySlug exists
const triggerSMEPayIfSelected = () => {
    if (orderPaid) {
        console.log('Order is already paid. SMEPay widget will not be triggered.');
        return;
    }
    
    if (smepaySlug) {
        console.log('Triggering SMEPay widget with slug:', smepaySlug);
        window.smepayCheckout({
            slug: smepaySlug,
            onSuccess: function () {
                // Redirect after successful payment
                window.location.href = smepayRedirectUrl + `&smepay_slug=${smepaySlug}`;
            },
            onFailure: function () {
                console.warn('SMEPay widget closed or failed.');
            }
        });
    } else {
        console.log('SMEPay slug missing. Widget will not trigger.');
    }
};

// Run once on load in case SMEPay is available
window.addEventListener('load', () => {
    const selectedPayment = document.querySelector('input[name="payment_method"]:checked');
    if (selectedPayment?.value === 'smepfowo') {
        setTimeout(triggerSMEPayIfSelected, 500);
    }
});

// Bind to radio button change event to handle user interaction
document.addEventListener('change', (e) => {
    if (e.target && e.target.matches('input[name="payment_method"][value="smepfowo"]')) {
        triggerSMEPayIfSelected();
    }
});
