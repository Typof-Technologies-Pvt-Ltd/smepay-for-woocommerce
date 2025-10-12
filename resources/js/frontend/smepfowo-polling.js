jQuery(function($) {
    $(document).ajaxSuccess(function(event, xhr, settings) {
        if (
            settings.url.includes('wc-ajax=checkout') &&
            xhr.responseJSON &&
            xhr.responseJSON.result === 'success' &&
            xhr.responseJSON.order_id
        ) {
            const orderId = xhr.responseJSON.order_id;
            const nonce = smepfowo_data.nonce;

            function checkPaymentStatus() {
                $.ajax({
                    url: smepfowo_data.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'check_smepfowo_order_status',
                        nonce: nonce,
                        order_id: orderId,
                    },
                    success: function(response) {
                        if (response.success) {
                            const status = response.data.status;

                            if (status === 'SUCCESS' || status === 'TEST_SUCCESS') {
                                clearInterval(pollingInterval);

                                const redirectURL = response.data.redirect_url || '/';
                                window.location.href = redirectURL;
                            }
                        } else {
                            console.warn('Payment status error:', response.data.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('SMEPay AJAX error:', error);
                    }
                });
            }

            const pollingInterval = setInterval(checkPaymentStatus, 10000);
            checkPaymentStatus(); // Call once immediately
        }
    });

});
