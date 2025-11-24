<?php
defined('ABSPATH') || exit;

if (!current_user_can('manage_woocommerce')) {
    return;
}

?>
<div class="wrap">
    <h1><?php _e('Validate Gift Card', 'massnahme-gift-cards'); ?></h1>

    <form id="mgc-validate-form" method="post" action="">
        <?php wp_nonce_field('mgc_admin_nonce'); ?>

        <table class="form-table">
            <tr>
                <th><label for="mgc_code"><?php _e('Gift Card Code', 'massnahme-gift-cards'); ?></label></th>
                <td>
                    <input type="text" name="mgc_code" id="mgc_code" class="regular-text">
                    <p class="description"><?php _e('Enter a gift card code to validate its balance and status.', 'massnahme-gift-cards'); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary"><?php _e('Validate', 'massnahme-gift-cards'); ?></button>
        </p>
    </form>

    <div id="mgc-validate-result" style="margin-top:20px;"></div>
</div>
