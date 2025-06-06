const smepaylabelText = 'SMEPay for WooCommerce';
const smepaylogo = 'https://typof.co/smepay/smepay.svg';
const { createElement: smepayEl, Fragment } = window.wp.element;

// Get slug from URL
const urlParams = new URLSearchParams(window.location.search);
const smepaySlug = urlParams.get('smepay_slug');
const smepayRedirectUrl = urlParams.get('redirect_url');

// Your payment description
const smepayContent = () => 'Pay securely using SMEPay UPI.';

// Register SMEPay payment method
const SMEBlock_Gateway = {
    name: 'smepay',
    label: smepayEl(Fragment, null,
        smepaylabelText,
        smepayEl('img', {
            src: smepaylogo,
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
    ariaLabel: smepaylabelText,
    supports: {
        features: ['products'],
    },
};

window.wc.wcBlocksRegistry.registerPaymentMethod(SMEBlock_Gateway);

const orderPaid = window.wcSmepayData?.orderPaid || false;

// Trigger SMEPay widget if smepaySlug exists
const triggerSMEPayIfSelected = () => {
    if (orderPaid) {
        console.log('Order is already paid. SMEPay widget will not be triggered.');
        return;
    }
    
    // If smepaySlug exists, trigger the SMEPay widget
    if (smepaySlug) {
        window.smepayCheckout({
            slug: smepaySlug,
            onSuccess: function () {
                // Redirect after successful payment
                window.location.href = smepayRedirectUrl + `&smepay_slug=${smepaySlug}`;
            },
            onFailure: function () {
                console.warn('❌ SMEPay widget closed or failed.');
            }
        });
    } else {
        console.log('SMEPay is not available, widget will not trigger.');
    }
};

// Run once on load in case SMEPay is available
window.addEventListener('load', () => {
    setTimeout(triggerSMEPayIfSelected, 500);
});

// Bind to radio button change event to handle any user interaction
document.addEventListener('change', (e) => {
    if (e.target && e.target.matches('input[name="payment-method"][value="smepay"]')) {
        triggerSMEPayIfSelected();
    }
});
