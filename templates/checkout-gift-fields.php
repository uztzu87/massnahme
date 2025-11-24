<?php
/**
 * Checkout gift card fields template
 */
?>
<div class="mgc-checkout-fields">
    <h3><?php _e('Gift Card Details', 'massnahme-gift-cards'); ?></h3>
    
    <p class="form-row form-row-wide">
        <label for="mgc_recipient_email">
            <?php _e('Recipient Email (optional)', 'massnahme-gift-cards'); ?>
        </label>
        <input type="email" class="input-text" name="mgc_recipient_email" id="mgc_recipient_email" 
               placeholder="<?php esc_attr_e('Send directly to recipient...', 'massnahme-gift-cards'); ?>">
    </p>
    
    <p class="form-row form-row-wide">
        <label for="mgc_message">
            <?php _e('Personal Message', 'massnahme-gift-cards'); ?>
        </label>
        <textarea name="mgc_message" class="input-text" id="mgc_message" 
                  placeholder="<?php esc_attr_e('Your message...', 'massnahme-gift-cards'); ?>" 
                  rows="4"></textarea>
    </p>
    
    <p class="form-row form-row-wide">
        <label for="mgc_delivery_date">
            <?php _e('Delivery Date (optional)', 'massnahme-gift-cards'); ?>
        </label>
        <input type="date" class="input-text" name="mgc_delivery_date" id="mgc_delivery_date">
        <span class="description">
            <?php _e('Schedule delivery for a future date', 'massnahme-gift-cards'); ?>
        </span>
    </p>
</div>