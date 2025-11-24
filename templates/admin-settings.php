<?php
defined('ABSPATH') || exit;

if (!current_user_can('manage_woocommerce')) {
    return;
}

$settings = get_option('mgc_settings', []);
?>
<div class="wrap">
    <h1><?php _e('Massnahme Gift Cards Settings', 'massnahme-gift-cards'); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field('mgc_settings_nonce'); ?>

        <table class="form-table">
            <tr>
                <th><label for="expiry_days"><?php _e('Expiry Days', 'massnahme-gift-cards'); ?></label></th>
                <td>
                    <input type="number" name="expiry_days" id="expiry_days" value="<?php echo esc_attr($settings['expiry_days'] ?? 730); ?>" class="small-text">
                </td>
            </tr>

            <tr>
                <th><label for="code_prefix"><?php _e('Code Prefix', 'massnahme-gift-cards'); ?></label></th>
                <td>
                    <input type="text" name="code_prefix" id="code_prefix" value="<?php echo esc_attr($settings['code_prefix'] ?? 'MASS'); ?>">
                </td>
            </tr>

            <tr>
                <th><?php _e('Enable PDF Attachment', 'massnahme-gift-cards'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="enable_pdf" value="1" <?php checked(!empty($settings['enable_pdf'])); ?>>
                        <?php _e('Attach PDF to gift card emails', 'massnahme-gift-cards'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th><?php _e('Enable QR', 'massnahme-gift-cards'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="enable_qr" value="1" <?php checked(!empty($settings['enable_qr'])); ?>>
                        <?php _e('Include QR code in PDFs/emails', 'massnahme-gift-cards'); ?>
                    </label>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" name="mgc_save_settings" class="button button-primary"><?php _e('Save Settings', 'massnahme-gift-cards'); ?></button>
        </p>
    </form>
</div>
