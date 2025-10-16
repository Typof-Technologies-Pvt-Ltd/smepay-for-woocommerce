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

  function togglePlaceOrderButton() {
    const selectedInput = document.querySelector('input[name="payment_method"]:checked');
    const selectedMethod = selectedInput?.value;
    const supported = ['smepfowo', 'smepfowo_partial_cod'];
    const isSMEPay = supported.includes(selectedMethod);

    // Get the correct "Place Order" button (classic or block checkout)
    let $btn = document.querySelector('#place_order') ||
               document.querySelector('button[name="wc-block-components-checkout-place-order-button"]');

    if (!$btn) return;

    // Classic: input[type="submit"], Block: button
    const setButtonText = (text) => {
      if ($btn.tagName === 'INPUT') {
        $btn.value = text;
      } else {
        $btn.textContent = text;
      }
    };

    if (isSMEPay) {
      const container = document.querySelector(`.payment_box.payment_method_${selectedMethod}`);
      const hasQR = container?.querySelector('.smepfowo-qr-image');
      const hasIntent = container?.querySelector('.smepfowo-intent-item');

      if (hasQR || hasIntent) {
        $btn.style.display = 'none'; // Hide if QR or intent exists
      } else {
        $btn.style.display = ''; // Show button
        setButtonText(__('Retry Payment', 'woocommerce'));
      }
    } else {
      // If other method is selected, always show
      $btn.style.display = '';
      setButtonText(__('Place order', 'woocommerce'));
    }
  }

  
  function smepfowoToggleLoader(show = true, methodId = '', useGlobalContainer = false) {
    const supported = ['smepfowo', 'smepfowo_partial_cod'];
    let container;

    if (useGlobalContainer) {
      container = document.getElementById('payment');
    } else {
      if (!methodId) {
        const selectedInput = document.querySelector('input[name="payment_method"]:checked');
        methodId = selectedInput?.value;
      }

      if (!methodId) return;

      // Only proceed if methodId is supported
      if (!supported.includes(methodId)) {
        console.warn(`[SMEPFOWO] Loader: Payment method "${methodId}" is not supported.`);
        return;
      }

      container = document.querySelector(`.payment_box.payment_method_${methodId}`);
    }

    if (!container) {
      console.warn(`[SMEPFOWO] Loader: Could not find container for ${methodId || 'global'}`);
      return;
    }

    let existingLoader = container.querySelector('.smepfowo-payment-loader');

    if (show) {
      if (!existingLoader) {
        const loader = document.createElement('div');
        loader.className = 'smepfowo-payment-loader';
        loader.style.cssText = useGlobalContainer
          ? `
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px 0;
          `
          : 'margin: 20px auto; text-align: center;';

        loader.innerHTML = `
          <div class="smepfowo-spinner" style="
            width: 40px;
            height: 40px;
            border: 4px solid rgba(0,0,0,0.1);
            border-top: 4px solid #000;
            border-radius: 50%;
            animation: smepfowo-spin 1s linear infinite;
          "></div>
        `;

        // Insert at top if global
        if (useGlobalContainer) {
          container.insertBefore(loader, container.firstChild);
        } else {
          container.appendChild(loader);
        }

        console.log(`[SMEPFOWO] Loader shown in ${useGlobalContainer ? '#payment' : methodId}`);
      }
    } else {
      if (existingLoader) {
        existingLoader.remove();
        console.log(`[SMEPFOWO] Loader removed from ${useGlobalContainer ? '#payment' : methodId}`);
      }
    }
  }





  const startCountdownTimer = (container, durationSeconds = 300) => {
    // Check if timer already exists to prevent duplicates
    if (container.querySelector('.smepfowo-countdown-timer')) return;

    const timerEl = document.createElement('div');
    timerEl.className = 'smepfowo-countdown-timer';
    timerEl.style.marginTop = '10px';
    timerEl.style.fontSize = '14px';
    timerEl.style.fontWeight = '600';
    timerEl.style.textAlign = 'center';
    timerEl.style.color = '#d9534f'; // red-ish color, adjust as needed

    container.appendChild(timerEl);

    let remaining = durationSeconds;

    const updateTimer = () => {
      if (remaining <= 0) {
        timerEl.textContent = __('Payment window expired', 'smepay-for-woocommerce');
        clearInterval(intervalId);

        // Force show the "Retry Payment" button
        const $btn = document.querySelector('#place_order') ||
                     document.querySelector('button[name="wc-block-components-checkout-place-order-button"]');
        if ($btn) {
          $btn.style.display = '';
          if ($btn.tagName === 'INPUT') {
            $btn.value = __('Retry Payment', 'woocommerce');
          } else {
            $btn.textContent = __('Retry Payment', 'woocommerce');
          }
        }

        return;
      }


      const minutes = Math.floor(remaining / 60);
      const seconds = remaining % 60;
      timerEl.textContent = __('Time left: ', 'smepay-for-woocommerce') + `${minutes}:${seconds.toString().padStart(2, '0')}`;
      remaining -= 1;
    };

    updateTimer(); // initial call
    const intervalId = setInterval(updateTimer, 1000);
  };



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

  let formattedAmount = '';
  try {
    // Try extracting from PhonePe or any other intent URL
    const sampleIntentUrl = intents?.phonepe || intents?.gpay || intents?.paytm || intents?.bhim || '';
    const match = sampleIntentUrl.match(/[?&]am=(\d+)/);

    if (match && match[1]) {
      const paise = parseInt(match[1], 10);
      const rupees = paise / 100;

      const locale = smepfowo_data?.locale || 'en-IN';
      const currency = wc_checkout_params?.currency || smepfowo_data?.currency || 'INR';

      formattedAmount = new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }).format(rupees);
    }
  } catch (err) {
    console.warn('[SMEPFOWO] Amount formatting failed:', err);
  }


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

    const container = document.querySelector(`.payment_box.payment_method_${selectedMethod}`);
    if (!container) {
      console.warn(`[SMEPFOWO] Could not find .payment_box.payment_method_${selectedMethod}`);
      return;
    }

    // Check if QR code or heading already exists
    if (container.querySelector('.smepfowo-qr-code') || container.querySelector('h6.smepfowo-qr-heading')) {
      smepfowoToggleLoader(false, '', true); // Hide
      console.log('[SMEPFOWO] QR code already present, skipping insertion.');
      return;
    }

    // Show loader before inserting
    smepfowoToggleLoader(true, '', true); // Show

    if (!qrCode) {
      console.warn('[SMEPFOWO] No QR code found.');
      smepfowoToggleLoader(false, '', true); // Hide
      return;
    }

    // Create heading
    const heading = document.createElement('h6');
    heading.className = 'smepfowo-qr-heading smepfowo-qr-image';
    heading.style.textAlign = 'center';
    heading.style.marginTop = '10px';
    heading.style.fontWeight = '600';
    heading.style.fontSize = '16px';
    heading.innerText = `${__('Scan this QR to Pay', 'smepay-for-woocommerce')}${formattedAmount ? ` – ${formattedAmount}` : ''}`;

    // Create QR code image
    const img = document.createElement('img');
    img.src = qrCode.startsWith('data:image') ? qrCode : `data:image/png;base64,${qrCode}`;
    img.alt = 'SMEPay QR Code';
    img.className = 'smepfowo-qr-code smepfowo-qr-image fade-in pulse';
    img.style = 'margin: 10px auto 0 auto; max-width: 200px; display: block;';

    container.appendChild(heading);
    container.appendChild(img);

    // Start the 5-min countdown timer inside the same container
    startCountdownTimer(container, 300);
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

    // Only show on mobile devices (screen width < 768px)
    if (window.innerWidth >= 768) return;

    if (!intents || typeof intents !== 'object') {
      console.warn('[SMEPFOWO] No intents found.');
      return;
    }

    // Icons for supported UPI apps
    const icons = {
      gpay: 'https://typof.co/gpay.png',
      phonepe: 'https://typof.co/phonepe.png',
      paytm: 'https://typof.co/paytm.png',
      bhim: 'https://typof.co/bhim.png',
    };

    // Labels for display
    const labels = {
      phonepe: 'PhonePe',
      gpay: 'GPay',
      paytm: 'Paytm',
      bhim: __('Others', 'smepay-for-woocommerce'),
    };

    // Preferred order of apps
    const orderedApps = ['phonepe', 'gpay', 'paytm', 'bhim'];

    // Container wrapper
    const wrapper = document.createElement('div');
    wrapper.className = 'smepfowo-intents-mobile-only';
    wrapper.style.marginTop = '20px';
    wrapper.style.textAlign = 'center';

    // Heading with dynamic amount (if any)
    const heading = document.createElement('h6');
    heading.style.marginBottom = '12px';
    heading.textContent = `${__('Pay using your UPI app', 'smepay-for-woocommerce')}${formattedAmount ? ` – ${formattedAmount}` : ''}`;
    wrapper.appendChild(heading);

    // Grid container for app icons
    const grid = document.createElement('div');
    grid.className = 'smepfowo-intents-grid'; // Style in CSS as needed

    orderedApps.forEach((app) => {
      const link = intents[app];
      if (!link) return;

      const icon = icons[app];
      const label = labels[app] || app;

      const item = document.createElement('div');
      item.className = 'smepfowo-intent-item';

      const anchor = document.createElement('a');
      anchor.href = link;
      anchor.target = '_blank';
      anchor.rel = 'noopener noreferrer';

      const img = document.createElement('img');
      img.src = icon;
      img.alt = label;
      img.style.height = '28px';
      img.style.marginBottom = '4px';

      const text = document.createElement('div');
      text.style.fontSize = '12px';
      text.textContent = label;

      anchor.appendChild(img);
      item.appendChild(anchor);
      item.appendChild(text);

      grid.appendChild(item);
    });

    wrapper.appendChild(grid);
    container.appendChild(wrapper);

    // Start countdown timer inside this container for 5 minutes
    startCountdownTimer(container, 300);
  };




  // Re-trigger widget when payment method is changed
  document.addEventListener('change', (e) => {
    const input = e.target;
    const supported = ['smepfowo', 'smepfowo_partial_cod'];

    if (input?.matches('input[name="payment_method"]') && supported.includes(input.value)) {
      debounceTrigger(); // Your existing payment status polling logic
      insertQRCodeIntoClassicPaymentBox();
      insertIntentsIntoClassicPaymentBox();
      togglePlaceOrderButton();
    } else {
      togglePlaceOrderButton(); // Handles showing button again when switching away
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