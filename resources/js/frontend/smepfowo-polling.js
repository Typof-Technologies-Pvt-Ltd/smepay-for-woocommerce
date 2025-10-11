jQuery(function($) {
    // Log localized data
    console.log('[SMEPFOWO Polling] Localized smepfowo_data:', smepfowo_data);
    $(document).ajaxSuccess(function(event, xhr, settings) {
        if (
            settings.url.includes('wc-ajax=checkout') &&
            xhr.responseJSON &&
            xhr.responseJSON.result === 'success' &&
            xhr.responseJSON.order_id
        ) {
            const orderId = xhr.responseJSON.order_id;
            const nonce = smepfowo_data.nonce;

            console.log('Detected SMEPay order ID:', orderId);

            console.log('[SMEPFOWO Polling] Detected Order ID:', orderId);
            console.log('[SMEPFOWO Polling] Nonce:', nonce);
            console.log('[SMEPFOWO Polling] AJAX URL:', smepfowo_data.ajax_url);
            console.log(smepfowo_data.intents); // Object with keys like 'gpay', 'phonepe', etc.


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
                        console.log('response status:', response);
                        if (response.success) {
                            const status = response.data.status;
                            console.log('Payment status:', status);

                            if (status === 'SUCCESS' || status === 'TEST_SUCCESS') {
                                clearInterval(pollingInterval);

                                const redirectURL = response.data.redirect_url || '/';
                                console.log('poll js Redirecting to:', redirectURL);
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
