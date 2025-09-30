(() => {
  // Exit if dependencies are missing or already initialized
  if (
    !window.wp?.element ||
    !window.wp.i18n ||
    !window.smepfowoCheckoutData ||
    !window.wc?.wcBlocksRegistry ||
    window.smepfowoBlocksInit
  ) {
    return;
  }

  // Prevent re-initialization
  window.smepfowoBlocksInit = true;

  // Destructure commonly used WP functions
  const { createElement: el, Fragment } = window.wp.element;
  const { __ } = window.wp.i18n;

  // Get SMEPay checkout data and URL parameters
  const smepfowoLogo = window.smepfowoCheckoutData.imgUrl || '';
  const urlParams = new URLSearchParams(window.location.search);
  const smepfowoSlug = urlParams.get('smepfowo_slug');
  const smepfowoRedirectUrl = urlParams.get('redirect_url') || window.location.href;
  const smepfowoOrderPaid = !!window.smepfowoCheckoutData.orderPaid;

  // Supported gateways
  const supportedMethods = ['smepfowo', 'smepfowo_partial_cod'];

  // Helpers to fetch method data
  const getLabelText = (methodId) => {
    const methodData = window.wc?.wcSettings?.getPaymentMethodData?.(methodId);
    console.log(`wcSettings data for ${methodId}:`, methodData);
    return methodData?.title || __('Pay with UPI', 'smepay-for-woocommerce');
  };

  const getDescriptionContent = (methodId) => {
    const methodData = window.wc?.wcSettings?.getPaymentMethodData?.(methodId);
    return methodData?.description || __('Securely powered by SMEPay.', 'smepay-for-woocommerce');
  };

  // Register payment methods for each gateway
  supportedMethods.forEach((methodId) => {
    const title = getLabelText(methodId);
    const description = getDescriptionContent(methodId);

    console.log(`Registering method: ${methodId}`);
    console.log(`Title: ${title}`);
    console.log(`Description: ${description}`);

    window.wc.wcBlocksRegistry.registerPaymentMethod({
      name: methodId,
      label: el(
        Fragment,
        null,
        title,
        el('img', {
          src: smepfowoLogo,
          alt: __('SMEPay Logo', 'smepay-for-woocommerce'),
          style: {
            height: '20px',
            marginLeft: '8px',
            verticalAlign: 'middle',
            display: 'inline-block',
          },
        })
      ),
      content: el('div', null, description),
      edit: el('div', null, description),
      canMakePayment: () => true,
      ariaLabel: title,
      supports: {
        features: ['products'],
      },
    });
  });

  // Handle widget triggering
  let widgetTriggerTimeout = null;

  const smepfowoTriggerPaymentWidget = () => {
    if (smepfowoOrderPaid) {
      console.log(__('Order is already paid. SMEPay widget will not be triggered.', 'smepay-for-woocommerce'));
      return;
    }

    if (smepfowoSlug && typeof window.smepayCheckout === 'function') {
      window.smepayCheckout({
        slug: smepfowoSlug,
        onSuccess: () => {
          window.location.href = smepfowoRedirectUrl.includes('?')
            ? `${smepfowoRedirectUrl}&smepfowo_slug=${smepfowoSlug}`
            : `${smepfowoRedirectUrl}?smepfowo_slug=${smepfowoSlug}`;
        },
        onFailure: () => {
          console.warn(__('SMEPay widget closed or failed.', 'smepay-for-woocommerce'));
        },
      });
    } else {
      console.log(__('SMEPay slug missing or checkout function unavailable.', 'smepay-for-woocommerce'));
    }
  };

  const debounceTrigger = () => {
    clearTimeout(widgetTriggerTimeout);
    widgetTriggerTimeout = setTimeout(smepfowoTriggerPaymentWidget, 300);
  };

  // Auto-select method and trigger widget on load
  window.addEventListener('load', () => {
    if (!smepfowoSlug) return;

    const selectedMethod = supportedMethods.find((method) =>
      document.querySelector(`input[name="payment_method"][value="${method}"]`)
    );

    if (selectedMethod) {
      const input = document.querySelector(`input[name="payment_method"][value="${selectedMethod}"]`);
      if (input) {
        input.checked = true;
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }

    debounceTrigger();
  });

  // Re-trigger widget when payment method is changed
  document.addEventListener('change', (e) => {
    const input = e.target;
    if (
      input?.matches('input[name="payment_method"]') &&
      supportedMethods.includes(input.value)
    ) {
      debounceTrigger();
    }
  });
})();