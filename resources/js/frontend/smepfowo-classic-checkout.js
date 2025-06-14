const { __ } = wp.i18n;

jQuery(function ($) {
  const $form = $('form.checkout');

  $form.on('checkout_place_order_smepfowo', function (e) {
    e.preventDefault();

    const selectedMethod = $('input[name="payment_method"]:checked').val();
    if (selectedMethod !== 'smepfowo') return true;

    $form.block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } });

    $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message, .is-error').remove();

    const formData = $form.serialize();

    $.ajax({
      type: 'POST',
      url: wc_checkout_params.checkout_url,
      data: formData,
      dataType: 'json',
      success: function (response) {
        console.log(__('✅ AJAX response:', 'smepay-for-woocommerce'), response);

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

        if (response.result === 'success' && response.smepay_slug) {
          window.smepayCheckout({
            slug: response.smepay_slug,
            onSuccess: function () {
              window.location.href = response.redirect_url;
            },
            onFailure: function () {
              console.warn(__('❌ SMEPay widget closed or failed.', 'smepay-for-woocommerce'));
            }
          });
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