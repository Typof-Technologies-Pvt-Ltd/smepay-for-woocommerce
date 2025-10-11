const { __ } = wp.i18n;

jQuery(function ($) {
  const $form = $('form.checkout');

  $form.on('checkout_place_order_smepfowo checkout_place_order_smepfowo_partial_cod', function (e) {
    e.preventDefault();

    const selectedMethod = $('input[name="payment_method"]:checked').val();
    if (!['smepfowo', 'smepfowo_partial_cod'].includes(selectedMethod)) {
        return true;
    }

    $form.block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } });

    $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message, .is-error').remove();

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
            window.smepayCheckout({
              slug: paymentSlug,
              onSuccess: function () {
                window.location.href = response.redirect_url;
              },
              onFailure: function () {
                console.warn(__('❌ SMEPay widget closed or failed.', 'smepay-for-woocommerce'));
              }
            });
          } else if (smepfowo_data.display_mode === 'inline') {
              console.log(__('✅ SMEPay inline mode - render QR + intents', 'smepay-for-woocommerce'));

              const $paymentBox = $(`.payment_box.payment_method_${selectedMethod}`);

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

                let html = `
                  <p><strong>${__('Secure by SMEPay')}</strong></p>
                  <div id="smepfowo-qr-content" style="text-align:center; margin-top: 15px;">
                `;

                if (!isMobile && response.qr_code) {
                  // ✅ Desktop/tablet: show QR code
                  html += `
                    <h6>${__('Scan this QR to Pay', 'smepay-for-woocommerce')}</h6>
                    <img class="smepfowo-qr-image" src="data:image/png;base64,${response.qr_code}" alt="QR Code" style="max-width: 250px;" />
                  `;
                }

                // ✅ Show intents only on mobile
                if (Object.keys(intents).length > 0) {
                  html += `
                    <div class="smepfowo-intents-mobile-only">
                      <h6 style="margin-top:20px;">${__('Pay using your UPI app', 'smepay-for-woocommerce')}</h6>
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
              } else {
                console.warn('❌ Could not find .payment_box.payment_method_smepfowo to render QR or intents.');
              }
            }


        } else {
          console.warn(__('⚠️ Unexpected response:', 'smepay-for-woocommerce'), response);
        }
      },
      error: function (err) {
        console.error(__('🔥 AJAX error:', 'smepay-for-woocommerce'), err);
        $form.unblock();
        $form.prepend(`<div class="woocommerce-error is-error">${__('Unexpected error occurred. Please try again.', 'smepay-for-woocommerce')}</div>`);
      }
    });

    return false;
  });
});