jQuery(function ($) {
  const $form = $('form.checkout');

  $form.on('checkout_place_order_smepay', function (e) {
    e.preventDefault();

    const selectedMethod = $('input[name="payment_method"]:checked').val();
    if (selectedMethod !== 'smepay') return true;

    // Block form while processing
    $form.block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } });

    // Clear previous errors
    $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message, .is-error').remove();

    const formData = $form.serialize();

    $.ajax({
      type: 'POST',
      url: wc_checkout_params.checkout_url,
      data: formData,
      dataType: 'json',
      success: function (response) {
        console.log('✅ AJAX response:', response);

        $form.unblock();

        if (response.result === 'failure') {
          // Append server error messages if present
          if (response.messages) {
            $form.prepend(response.messages);
          } else {
            $form.prepend(`
                <div class="wc-block-components-notice-banner is-error" role="alert" data-id="billing_last_name">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
                        <path d="M12 3.2c-4.8 0-8.8 3.9-8.8 8.8 0 4.8 3.9 8.8 8.8 8.8 4.8 0 8.8-3.9 8.8-8.8 0-4.8-4-8.8-8.8-8.8zm0 16c-4 0-7.2-3.3-7.2-7.2C4.8 8 8 4.8 12 4.8s7.2 3.3 7.2 7.2c0 4-3.2 7.2-7.2 7.2zM11 17h2v-6h-2v6zm0-8h2V7h-2v2z"></path>
                    </svg>
                    <div class="wc-block-components-notice-banner__content">
                        Something went wrong. Please try after sometimes.
                    </div>
                </div>
            `);

          }

          // Scroll to error
          const $error = $('.woocommerce-error, .woocommerce-NoticeGroup-checkout, .is-error');
          if ($error.length) {
            $('html, body').animate({ scrollTop: $error.offset().top - 100 }, 600);
          }

          return false;
        }

        if (response.result === 'success' && response.smepay_slug) {
          // Launch SMEPay widget
          window.smepayCheckout({
            slug: response.smepay_slug,
            onSuccess: function () {
              window.location.href = response.redirect_url;
            },
            onFailure: function () {
              console.warn('❌ SMEPay widget closed or failed.');
            }
          });
        } else {
          console.warn('⚠️ Unexpected response:', response);
        }
      },
      error: function (err) {
        console.error('🔥 AJAX error:', err);
        $form.unblock();
        $form.prepend('<div class="woocommerce-error is-error">Unexpected error occurred. Please try again.</div>');
      }
    });

    return false;
  });
});