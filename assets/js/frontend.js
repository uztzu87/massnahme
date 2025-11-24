/**
 * Gift Card Frontend JS
 */
(function($) {
    'use strict';

    // Balance checker
    $(document).on('click', '.mgc-check-balance', function(e) {
        e.preventDefault();
        
        var code = $('#mgc_code').val();
        var $result = $('.mgc-balance-result');
        var $button = $(this);
        
        if (!code) {
            $result.removeClass('success').addClass('error').text('Please enter a gift card code').show();
            return;
        }
        
        $button.prop('disabled', true).text('Checking...');
        
        $.ajax({
            url: mgc_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'mgc_validate_code',
                code: code,
                nonce: mgc_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.removeClass('error').addClass('success')
                        .html(response.data.message + '<br>Expires: ' + response.data.expires)
                        .show();
                } else {
                    $result.removeClass('success').addClass('error')
                        .text(response.data)
                        .show();
                }
            },
            complete: function() {
                $button.prop('disabled', false).text('Check Balance');
            }
        });
    });

    // Delivery date picker enhancement
    if ($('#mgc_delivery_date').length) {
        $('#mgc_delivery_date').attr('min', new Date().toISOString().split('T')[0]);
    }

})(jQuery);