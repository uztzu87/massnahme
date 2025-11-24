/**
 * Admin JavaScript for Massnahme Gift Cards
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Validate gift card form
        $('#mgc-validate-form').on('submit', function(e) {
            e.preventDefault();

            var code = $('#mgc_code').val();
            if (!code) {
                alert('Please enter a gift card code');
                return;
            }

            // Show loading
            var $result = $('#mgc-validate-result');
            $result.html('<p>Validating...</p>').show();

            // AJAX validation
            $.post(mgc_admin.ajax_url, {
                action: 'mgc_validate_code',
                code: code,
                nonce: mgc_admin.nonce
            }, function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                } else {
                    $result.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            }).fail(function() {
                $result.html('<div class="notice notice-error"><p>Validation failed. Please try again.</p></div>');
            });
        });
    });

})(jQuery);
