const { __ } = wp.i18n;

jQuery(function ($) {
  const $form = $('form.checkout');

  let smepfowo_user_triggered_checkout = false;
  let smepfowo_payment_processing = false; // ✅ NEW: Track if payment is already in progress

  // ✅ Track when user actually clicks the place order button
  $('#place_order').on('click', function () {
    smepfowo_user_triggered_checkout = true;
  });

  $(document).on('change', 'input[name="payment_method"]', function () {
    smepfowo_user_triggered_checkout = false;
    smepfowo_payment_processing = false; // ✅ Reset when payment method changes
  });

  // ✅ Update nonce dynamically when checkout updates (new user checkbox, field changes, etc.)
  function updateNonce() {
    if (!smepfowo_data) return;
    
    const $nonceField = $form.find('input[name="smepfowo_nonce"]');
    if ($nonceField.length) {
      smepfowo_data.nonce = $nonceField.val();
      console.log('[SMEPay] Nonce updated');
    }
  }

  function startCountdownTimer(container, durationSeconds = 300) {
    // Prevent duplicate timers
    if (container.querySelector('.smepfowo-countdown-timer')) return;

    var timerEl = document.createElement('div');
    timerEl.className = 'smepfowo-countdown-timer';
    timerEl.style.marginTop = '10px';
    timerEl.style.fontSize = '14px';
    timerEl.style.fontWeight = '600';
    timerEl.style.textAlign = 'center';
    timerEl.style.color = '#d9534f'; // red color

    container.appendChild(timerEl);

    var remaining = durationSeconds;

    function updateTimer() {
      if (remaining <= 0) {
        timerEl.textContent = __('Payment window expired', 'smepay-for-woocommerce');
        clearInterval(intervalId);

        // ✅ Show and rename button to "Retry Payment"
        $('#place_order')
          .show()
          .css('display', '')
          .val(__('Retry Payment', 'smepay-for-woocommerce'));

        return;
      }


      var minutes = Math.floor(remaining / 60);
      var seconds = remaining % 60;
      timerEl.textContent = __('Time left: ', 'smepay-for-woocommerce') + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
      remaining -= 1;
    }

    updateTimer();
    var intervalId = setInterval(updateTimer, 1000);
  }


  function togglePlaceOrderButton() {
    const selectedMethod = $('input[name="payment_method"]:checked').val();
    const isSMEPay = ['smepfowo', 'smepfowo_partial_cod'].includes(selectedMethod);

    // Only do logic if SMEPay method is selected
    if (isSMEPay) {
      const $paymentBox = $(`.payment_box.payment_method_${selectedMethod}`);
      const hasQR = $paymentBox.find('.smepfowo-qr-image').length > 0;
      const hasIntent = $paymentBox.find('.smepfowo-intent-item').length > 0;


      if (hasQR || hasIntent) {
        $('#place_order').hide(); // Hide only if QR or intents present
      } else {
        $('#place_order').show().css('display', '').val(__('Retry Payment', 'woocommerce'));
      }
    } else {
      // For non-SMEPay methods, always show
      $('#place_order').show().css('display', '').val(__('Place order', 'woocommerce'));
    }
  }


  // Clear SMEPay-specific content when switching methods
  $(document).on('change', 'input[name="payment_method"]', function () {
    // Always show button immediately on any method change
    $('#place_order').show().css('display', '');

    // Slight delay to let WooCommerce update selected method
    setTimeout(() => {
      togglePlaceOrderButton();
    }, 50);
  });

  // Reliable trigger after WooCommerce re-renders payment method section
  $('body').on('updated_checkout', function () {
    console.log('[SMEPay] updated_checkout triggered');
    updateNonce(); // ✅ Update nonce on every checkout update
    togglePlaceOrderButton();

    // Optionally, you can re-render QR or intents here if needed
    const selectedMethod = $('input[name="payment_method"]:checked').val();
    if (['smepfowo', 'smepfowo_partial_cod'].includes(selectedMethod)) {
      // 🚫 Don't trigger if the user hasn't clicked Place Order yet
      if (!smepfowo_user_triggered_checkout) {
        console.log('[SMEPay] Skipping trigger – user has not initiated checkout.');
        return;
      }
      
      // ✅ DON'T re-trigger if payment is already being processed (prevents double validation)
      if (smepfowo_payment_processing) {
        console.log('[SMEPay] Payment already in progress – skipping re-trigger.');
        return;
      }
    }
  });

  $form.on('checkout_place_order_smepfowo checkout_place_order_smepfowo_partial_cod', function (e) {
    e.preventDefault();

    const selectedMethod = $('input[name="payment_method"]:checked').val();
    if (!['smepfowo', 'smepfowo_partial_cod'].includes(selectedMethod)) {
        return true;
    }

    // ✅ Ensure nonce is fresh right before AJAX call
    updateNonce();
    smepfowo_payment_processing = true; // ✅ Set flag to prevent re-triggers

    $form.block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } });

    $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message, .is-error').remove();

    // 🔐 ✅ Check for nonce/config before continuing
    if (!smepfowo_data || !smepfowo_data.nonce) {
      console.error('Missing SMEPay nonce or config');
      smepfowo_payment_processing = false; // ✅ Reset flag
      $form.unblock();
      $form.prepend(`<div class="woocommerce-error is-error">${__('Missing payment configuration. Please refresh and try again.', 'smepay-for-woocommerce')}</div>`);
      return false;
    }

    let formData = $form.serialize();
    formData += '&smepfowo_nonce=' + encodeURIComponent(smepfowo_data.nonce);

    $.ajax({
      type: 'POST',
      url: wc_checkout_params.checkout_url,
      data: formData,
      dataType: 'json',
      success: function (response) {
        $form.unblock();
        if (response.result === 'failure') {
          smepfowo_payment_processing = false; // ✅ Reset flag on failure
          if (response.messages) {
            $form.prepend(response.messages);
          } else {
            $form.prepend(`
              <div class="woocommerce-error is-error" role="alert">
                ⚠️ <strong aria-label="Warning">${__('Something went wrong.', 'smepay-for-woocommerce')}</strong> ${__('Please try again later.', 'smepay-for-woocommerce')}
              </div>
            `);
          }

          const $error = $('.woocommerce-error, .woocommerce-NoticeGroup-checkout, .is-error');
          if ($error.length) {
            $('html, body').animate({ scrollTop: $error.offset().top - 100 }, 600);
          }

          return false;
        }

        if (response.result === 'success' && (response.smepfowo_slug || response.smepfowo_partial_cod_slug)) {
          const paymentSlug = response.smepfowo_slug || response.smepfowo_partial_cod_slug;
          // Only trigger the widget popup in wizard mode
          if (smepfowo_data.display_mode === 'wizard') {
            const orderId = response.order_id;
            const redirectUrl = response.redirect_url;
            
            // ✅ ADDED: Start polling in wizard mode to catch payment completion
            let pollAttempt = 0;
            const wizardPollingInterval = setInterval(function() {
              pollAttempt++;
              
              if (pollAttempt > 180) { // 30 mins max (180 * 10 seconds)
                clearInterval(wizardPollingInterval);
                return;
              }
              
              $.ajax({
                url: smepfowo_data.ajax_url,
                type: 'POST',
                data: {
                  action: 'check_smepfowo_order_status',
                  nonce: smepfowo_data.nonce,
                  order_id: orderId
                },
                success: function(pollResponse) {
                  if (pollResponse.success && pollResponse.data && (pollResponse.data.payment_status === 'SUCCESS' || pollResponse.data.payment_status === 'TEST_SUCCESS')) {
                    clearInterval(wizardPollingInterval);
                    window.location.href = redirectUrl;
                  }
                }
              });
            }, 10000);
            
            window.smepayCheckout({
              slug: paymentSlug,
              onSuccess: function () {
                window.location.href = response.redirect_url;
              },
              onFailure: function () {
                console.warn(__('❌ SMEPay widget closed or failed.', 'smepay-for-woocommerce'));
                smepfowo_payment_processing = false; // ✅ Reset flag
                $('#place_order').show().css('display', ''); // Restore the button just in case
              }
            });
          } else if (smepfowo_data.display_mode === 'inline') {
              const $paymentBox = $(`.payment_box.payment_method_${selectedMethod}`);

              if ($paymentBox.length === 0) {
                console.warn('❌ Payment box not ready yet. Retrying...');
                smepfowo_payment_processing = false; // ✅ Reset flag
                setTimeout(() => {
                  $('form.checkout').trigger(`checkout_place_order_${selectedMethod}`);
                }, 100);
                return;
              }


              if ($paymentBox.length) {
                const isMobile = window.innerWidth < 768; // Mobile detection
                const intents = response.intents || {};
                const paymentLink = response.payment_link || '';

                const icons = {
                  gpay: 'https://typof.co/gpay.png',
                  phonepe: 'https://typof.co/phonepe.png',
                  paytm: 'https://typof.co/paytm.png',
                  bhim: 'https://typof.co/bhim.png'
                };

                let amount = '';
                const intentUrl = response.intents?.phonepe || '';
                const match = intentUrl.match(/[?&]am=(\d+)/);

                if (match && match[1]) {
                  const paise = parseInt(match[1], 10);
                  const rupees = paise / 100;

                  const locale = smepfowo_data.locale || 'en-IN';
                  const currency = wc_checkout_params.currency || smepfowo_data.currency || 'INR';

                  amount = new Intl.NumberFormat(locale, {
                    style: 'currency',
                    currency: currency,
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                  }).format(rupees);
                }


                let html = `
                  <p><strong>${__('Secure by SMEPay')}</strong></p>
                  <div id="smepfowo-qr-content" style="text-align:center; margin-top: 15px;">
                `;

                if (!isMobile && response.qr_code) {
                  // ✅ Desktop/tablet: show QR code
                  html += `
                    <h6>
                      ${__('Scan this QR to Pay', 'smepay-for-woocommerce')}
                      ${amount ? ` – ${amount}` : ''}
                    </h6>
                    <img class="smepfowo-qr-image" src="data:image/png;base64,${response.qr_code}" alt="QR Code" style="max-width: 250px; max-height: 250px;" />
                  `;
                }

                // ✅ Show intents only on mobile
                if (Object.keys(intents).length > 0) {
                  html += `
                    <div class="smepfowo-intents-mobile-only">
                      <h6>
                        ${__('Pay using your UPI app', 'smepay-for-woocommerce')}
                        ${amount ? ` – ${amount}` : ''}
                      </h6>
                      <div style="display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; margin-top: 10px;">
                  `;

                  const orderedApps = ['phonepe', 'gpay', 'paytm', 'bhim'];

                  orderedApps.forEach((app) => {
                    const link = intents[app];
                    if (!link) return;

                    const icon = icons[app] || '';
                    const labels = {
                      phonepe: 'PhonePe',
                      gpay: 'GPay',
                      paytm: 'Paytm',
                      bhim: __('Others', 'smepay-for-woocommerce')
                    };

                    const label = labels[app] || app;


                    html += `
                      <div class="smepfowo-intent-item" style="flex: 0 0 45%; max-width: 45%; text-align: center;">
                        <a href="${link}" target="_blank" rel="noopener noreferrer">
                          <img src="${icon}" alt="${app}" style="height: 28px; margin-bottom: 4px;" />
                        </a>
                        <div style="font-size: 12px;">${label}</div>
                      </div>
                    `;
                  });

                  html += `
                      </div>
                    </div>
                  `;
                } else {
                  html += `<p>${__('UPI app links are currently unavailable.', 'smepay-for-woocommerce')}</p>`;
                }

                html += `</div>`; // end #smepfowo-qr-content

                $paymentBox.html(html);
                togglePlaceOrderButton();
                startCountdownTimer($paymentBox[0]);
              } else {
                console.warn('❌ Could not find .payment_box.payment_method_smepfowo to render QR or intents.');
                smepfowo_payment_processing = false; // ✅ Reset flag
                $('#place_order').show().css('display', ''); // Restore the button just in case
              }
            }


        } else {
          console.warn(__('⚠️ Unexpected response:', 'smepay-for-woocommerce'), response);
          smepfowo_payment_processing = false; // ✅ Reset flag
        }
      },
      error: function (err) {
        console.error(__('🔥 AJAX error:', 'smepay-for-woocommerce'), err);
        smepfowo_payment_processing = false; // ✅ Reset flag
        $form.unblock();
        $form.prepend(`<div class="woocommerce-error is-error">${__('Unexpected error occurred. Please try again.', 'smepay-for-woocommerce')}</div>`);
      }
    });

    return false;
  });

  // Optional: Fallback observer if updated_checkout isn't reliable
  const observerTarget = document.querySelector('.woocommerce-checkout-payment');

  if (observerTarget) {
    let observerTimeout;
    const observer = new MutationObserver(() => {
      clearTimeout(observerTimeout);
      observerTimeout = setTimeout(togglePlaceOrderButton, 100);
    });


    observer.observe(observerTarget, {
      childList: true,
      subtree: true
    });
  }

});
