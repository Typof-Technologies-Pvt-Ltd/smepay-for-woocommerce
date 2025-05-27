const smepaylabelText = 'SMEPay for WooCommerce';
const smepaylogo = 'https://typof.co/smepay/smepay.svg';
const { createElement: el, Fragment } = window.wp.element;

// Get slug from URL
const urlParams = new URLSearchParams(window.location.search);
const smepaySlug = urlParams.get('smepay_slug');
const smepayRedirectUrl = urlParams.get('redirect_url');

// Your payment description
const smepayContent = () => 'Pay securely using SMEPay UPI.';

// Register SMEPay payment method
const SMEBlock_Gateway = {
    name: 'smepay',
    label: el(Fragment, null,
        smepaylabelText,
        el('img', {
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
    content: el('div', null, smepayContent()),
    edit: el('div', null, smepayContent()),
    canMakePayment: () => true,
    ariaLabel: smepaylabelText,
    supports: {
        features: ['products'],
    },
};

window.wc.wcBlocksRegistry.registerPaymentMethod(SMEBlock_Gateway);

// ✅ Trigger SMEPay widget only if SMEPay is selected
const triggerSMEPayIfSelected = () => {
    console.log(smepay_data.redirect_url);
    const selected = document.querySelector('input[name="payment-method"]:checked');
    if (selected && selected.value === 'smepay' && smepaySlug) {
        window.smepayCheckout({
            slug: smepaySlug,
            onSuccess: function () {
                // You can make this dynamic if needed
                // http://localhost:8888/wc-proj/checkout/order-pay/910/?pay_for_order=true&key=wc_order_jO84HwAeJJDeD&redirect_url=http://localhost:8888/wc-proj/checkout/order-received/910/?key=wc_order_jO84HwAeJJDeD&smepay_slug=XFGMSDfSU7ka
                window.location.href = smepayRedirectUrl + `&smepay_slug=${smepaySlug}`;
            },
            onFailure: function () {
                console.warn('❌ SMEPay widget closed or failed.');
            }
        });
    }
};

// 🔁 Run once on load in case SMEPay is already selected
window.addEventListener('load', () => {
    setTimeout(triggerSMEPayIfSelected, 500);
});

// 🎧 Also bind to radio change event
document.addEventListener('change', (e) => {
    if (e.target && e.target.matches('input[name="payment-method"][value="smepay"]')) {
        triggerSMEPayIfSelected();
    }
});
