(() => {
  // Exit if dependencies are missing or already initialized
  if (
    !window.wp?.element ||
    !window.wp.i18n ||
    (!window.smepfowoCheckoutData && !window.smepfowoPartialCODCheckoutData) ||
    !window.wc?.wcBlocksRegistry ||
    window.smepfowoBlocksInit
  ) {
    return;
  }

  // Prevent re-initialization
  window.smepfowoBlocksInit = true;

  const { createElement: el, Fragment } = window.wp.element;
  const { __ } = window.wp.i18n;

  function showSMEPFOWORedirectOverlay(messageText = 'Please wait, redirecting...', delay = 800, redirectUrl = '') {
    // Prevent multiple overlays
    if (document.querySelector('.smepfowo-redirect-overlay')) return;

    const overlay = document.createElement('div');
    overlay.className = 'smepfowo-redirect-overlay';

    const message = document.createElement('div');
    message.className = 'smepfowo-redirect-message';
    message.innerText = messageText;

    const spinner = document.createElement('div');
    spinner.className = 'smepfowo-spinner';

    overlay.appendChild(message);
    overlay.appendChild(spinner);
    document.body.appendChild(overlay);

    // Trigger fade-in
    requestAnimationFrame(() => {
      overlay.classList.add('active');
    });

    // Redirect after delay
    setTimeout(() => {
      if (redirectUrl) {
        window.location.href = redirectUrl;
      }
    }, delay);
  }


  // Dynamically detect and use available checkout data
  const checkoutData =
    window.smepfowoCheckoutData ||
    window.smepfowoPartialCODCheckoutData;

  const {
    qrCode = '',
    imgUrl: smepfowoLogo = '',
    orderPaid: smepfowoOrderPaid = false,
    orderId = 0,
    paymentLink = '',
    intents = '',
  } = checkoutData;

  const urlParams = new URLSearchParams(window.location.search);
  const smepfowoSlug = urlParams.get('smepfowo_slug');
  const smepfowoPartialCODSlug = urlParams.get('smepfowo_partial_cod_slug'); // New
  const paymentSlug = smepfowoSlug || smepfowoPartialCODSlug;
  const smepfowoRedirectUrl = urlParams.get('redirect_url') || window.location.href;

  if (smepfowoSlug) {
    const smepfowoLi = document.querySelector('li.payment_method_smepfowo_partial_cod');
    if (smepfowoLi) smepfowoLi.style.display = 'none';
  }

  if (smepfowoPartialCODSlug) {
    const smepfowoLi = document.querySelector('li.payment_method_smepfowo');
    if (smepfowoLi) smepfowoLi.style.display = 'none';
  }

  const supportedMethods = ['smepfowo', 'smepfowo_partial_cod'];

  // Helpers to fetch method data
  const getLabelText = (methodId) => {
    const methodData = window.wc?.wcSettings?.getPaymentMethodData?.(methodId);
    return methodData?.title || __('Pay with UPI', 'smepay-for-woocommerce');
  };

  const getDescriptionContent = (methodId) => {
    const methodData = window.wc?.wcSettings?.getPaymentMethodData?.(methodId);
    return methodData?.description || __('Securely powered by SMEPay.', 'smepay-for-woocommerce');
  };

  // Register payment methods
  supportedMethods.forEach((methodId) => {
    const methodData = window.wc?.wcSettings?.getPaymentMethodData?.(methodId);

    const title = getLabelText(methodId);
    const description = getDescriptionContent(methodId);

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
      return;
    }

    if (orderId && typeof smepfowo_data !== 'undefined' && smepfowo_data?.nonce) {
      const nonce = smepfowo_data.nonce;
      const ajaxUrl = smepfowo_data.ajax_url;

      const checkPaymentStatus = () => {

        jQuery.ajax({
          url: ajaxUrl,
          method: 'POST',
          data: {
            action: 'check_smepfowo_order_status',
            nonce: nonce,
            order_id: orderId,
          },
          success: function (response) {

            if (response.success) {
              const status = response.data.status;

              if (status === 'SUCCESS' || status === 'TEST_SUCCESS') {
                clearInterval(pollingInterval);
                showSMEPFOWORedirectOverlay('Please wait, redirecting...', 800, smepfowoRedirectUrl);
              }

            } else {
              console.warn('[SMEPFOWO] Payment status error:', response.data?.message || 'Unknown error');
            }
          },
          error: function (xhr, status, error) {
            console.error('[SMEPFOWO] AJAX error:', error);
          }
        });
      };

      const pollingInterval = setInterval(checkPaymentStatus, 10000);
      checkPaymentStatus();
    } else {
      console.warn('[SMEPFOWO] Polling not started. Missing order ID or nonce.');
    }

    // Check if either smepfowoSlug or smepfowoPartialCODSlug exists
    if (paymentSlug && typeof window.smepayCheckout === 'function') {
      window.smepayCheckout({
        slug: paymentSlug,
        onSuccess: () => {
          // Use paymentSlug consistently
          window.location.href = smepfowoRedirectUrl.includes('?')
            ? `${smepfowoRedirectUrl}&${paymentSlug ? 'smepfowo_slug' : 'smepfowo_partial_cod_slug'}=${paymentSlug}`
            : `${smepfowoRedirectUrl}?${paymentSlug ? 'smepfowo_slug' : 'smepfowo_partial_cod_slug'}=${paymentSlug}`;
        },
        onFailure: () => {
          console.warn(__('SMEPay widget closed or failed.', 'smepay-for-woocommerce'));
        },
      });
    } else {
      // If condition fails, log the reason
      if (!paymentSlug) {
        console.log('[SMEPFOWO] Payment slug is missing!');
      }
      console.log(__('SMEPay slug missing or checkout function unavailable.', 'smepay-for-woocommerce'));
    }
  };

  const debounceTrigger = () => {
    clearTimeout(widgetTriggerTimeout);
    widgetTriggerTimeout = setTimeout(smepfowoTriggerPaymentWidget, 300);
  };

  // Auto-select method and trigger widget on load
  window.addEventListener('load', () => {
    if (!paymentSlug) return;

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

  // Insert QR code into classic checkout payment box
  const insertQRCodeIntoClassicPaymentBox = () => {
    const selectedInput = document.querySelector('input[name="payment_method"]:checked');
    if (!selectedInput) return;

    const selectedMethod = selectedInput.value;
    if (!supportedMethods.includes(selectedMethod)) return;

    if (!qrCode) {
      console.warn('[SMEPFOWO] No QR code found.');
      return;
    }

    const container = document.querySelector(`.payment_box.payment_method_${selectedMethod}`);
    if (!container) {
      console.warn(`[SMEPFOWO] Could not find .payment_box.payment_method_${selectedMethod}`);
      return;
    }

    // Prevent duplicate insertion
    if (container.querySelector('.smepfowo-qr-code')) return;

    const img = document.createElement('img');
    img.src = qrCode.startsWith('data:image') ? qrCode : `data:image/png;base64,${qrCode}`;
    img.alt = 'SMEPay QR Code';
    img.className = 'smepfowo-qr-code smepfowo-qr-image fade-in pulse';
    img.style = 'margin: 10px auto 0 auto; max-width: 200px; display: block;';


    // ✅ Icons container (UPI app logos)
    const iconsContainer = document.createElement('div');
    iconsContainer.className = 'smepfowo-qr-app-icons';
    iconsContainer.innerHTML = `
      <div style="display: flex; justify-content: center; gap: 16px; margin-top: 12px;">
        <img alt="gpay" src="https://typof.co/gpay.png" style="height: 24px;">
        <img alt="phonepe" src="https://typof.co/phonepe.png" style="height: 24px;">
        <img alt="paytm" src="https://typof.co/paytm.png" style="height: 24px;">
        <img alt="bhim" src="https://typof.co/bhim.png" style="height: 24px;">
      </div>
    `;


    container.appendChild(img);
    // container.appendChild(iconsContainer);
  };

  const insertIntentsIntoClassicPaymentBox = () => {
    const selectedInput = document.querySelector('input[name="payment_method"]:checked');
    if (!selectedInput) return;

    const selectedMethod = selectedInput.value;
    if (!supportedMethods.includes(selectedMethod)) return;

    const container = document.querySelector(`.payment_box.payment_method_${selectedMethod}`);
    if (!container) {
      console.warn(`[SMEPFOWO] Could not find .payment_box.payment_method_${selectedMethod}`);
      return;
    }

    // Prevent duplicate insertion
    if (container.querySelector('.smepfowo-intents-mobile-only')) return;

    const isMobile = window.innerWidth < 768;
    if (!isMobile) {
      return;
    }

    if (!intents || typeof intents !== 'object') {
      console.warn('[SMEPFOWO] No intents found.');
      return;
    }

    const icons = {
      gpay: 'https://typof.co/gpay.png',
      phonepe: 'https://typof.co/phonepe.png',
      paytm: 'https://typof.co/paytm.png',
      bhim: 'https://typof.co/bhim.png'
    };

    const labels = {
      phonepe: 'PhonePe',
      gpay: 'GPay',
      paytm: 'Paytm',
      bhim: __('Others', 'smepay-for-woocommerce')
    };

    const orderedApps = ['phonepe', 'gpay', 'paytm', 'bhim'];

    const wrapper = document.createElement('div');
    wrapper.className = 'smepfowo-intents-mobile-only';
    wrapper.style = 'margin-top: 20px; text-align: center;';

    const heading = document.createElement('h6');
    heading.textContent = __('Pay using your UPI app', 'smepay-for-woocommerce');
    wrapper.appendChild(heading);

    const grid = document.createElement('div');
    grid.style = 'display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; margin-top: 10px;';

    orderedApps.forEach((app) => {
      const link = intents[app];
      if (!link) return;

      const icon = icons[app];
      const label = labels[app] || app;

      const item = document.createElement('div');
      item.className = 'smepfowo-intent-item';
      item.style = 'flex: 0 0 45%; max-width: 45%; text-align: center;';

      const anchor = document.createElement('a');
      anchor.href = link;
      anchor.target = '_blank';
      anchor.rel = 'noopener noreferrer';

      const img = document.createElement('img');
      img.src = icon;
      img.alt = label;
      img.style = 'height: 28px; margin-bottom: 4px;';

      const text = document.createElement('div');
      text.style = 'font-size: 12px;';
      text.textContent = label;

      anchor.appendChild(img);
      item.appendChild(anchor);
      item.appendChild(text);

      grid.appendChild(item);
    });

    wrapper.appendChild(grid);
    container.appendChild(wrapper);
  };



  // Re-trigger widget when payment method is changed
  document.addEventListener('change', (e) => {
    const input = e.target;
    const supported = ['smepfowo', 'smepfowo_partial_cod'];

    if (input?.matches('input[name="payment_method"]') && supported.includes(input.value)) {
      debounceTrigger(); // Your existing payment status polling logic
      insertQRCodeIntoClassicPaymentBox();
      insertIntentsIntoClassicPaymentBox();
    }
  });

  setTimeout(() => {
    let preferredMethod = null;

    if (smepfowoSlug) {
      preferredMethod = 'smepfowo';
    }

    if (smepfowoPartialCODSlug) {
      preferredMethod = 'smepfowo_partial_cod';
    }

    if (!preferredMethod) {
      console.warn('[SMEPFOWO] No valid slug found to select payment method.');
      return;
    }

    const input = document.querySelector(`input[name="payment_method"][value="${preferredMethod}"]`);

    if (input) {
      input.click();
      if (typeof insertQRCodeIntoClassicPaymentBox === 'function') {
        insertQRCodeIntoClassicPaymentBox();
      }
      if (typeof insertIntentsIntoClassicPaymentBox === 'function') {
        insertIntentsIntoClassicPaymentBox();
      }
      if (typeof debounceTrigger === 'function') {
        debounceTrigger();
      }
    } else {
      console.warn(`[SMEPFOWO] Payment method input not found: ${preferredMethod}`);
    }
  }, 10000);
})();