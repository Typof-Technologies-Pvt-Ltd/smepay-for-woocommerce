jQuery(function ($) {
  const $form = $('form.checkout');

  $form.on('checkout_place_order_smepfowo', function (e) {
    e.preventDefault();

    const selectedMethod = $('input[name="payment_method"]:checked').val();
    if (selectedMethod !== 'smepfowo') return true;

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
                <div class="woocommerce-error is-error" role="alert">
                  ⚠️ <strong aria-label="Warning">Something went wrong.</strong> Please try again later.
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