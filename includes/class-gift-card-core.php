<?php
/**
 * Core functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MGC_Core {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Product creation
        add_action('init', [$this, 'create_gift_products']);

        // Order processing
        add_action('woocommerce_order_status_processing', [$this, 'process_gift_card_order']);
        add_action('woocommerce_order_status_completed', [$this, 'process_gift_card_order']);

        // Checkout fields
        add_action('woocommerce_after_order_notes', [$this, 'add_checkout_fields']);
        add_action('woocommerce_checkout_create_order', [$this, 'save_checkout_fields'], 10, 2);

        // Enqueue scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);

        // AJAX handlers
        add_action('wp_ajax_mgc_validate_code', [$this, 'ajax_validate_code']);
        add_action('wp_ajax_nopriv_mgc_validate_code', [$this, 'ajax_validate_code']);
        add_action('wp_ajax_mgc_set_delivery_method', [$this, 'ajax_set_delivery_method']);
        add_action('wp_ajax_nopriv_mgc_set_delivery_method', [$this, 'ajax_set_delivery_method']);

        // Shortcodes
        add_shortcode('massnahme_gift_balance', [$this, 'balance_checker_shortcode']);

        // Add shipping fee for gift card delivery
        add_action('woocommerce_cart_calculate_fees', [$this, 'add_gift_card_shipping_fee']);

        // Start session for delivery method tracking
        add_action('init', [$this, 'start_session'], 1);
    }

    public function start_session() {
        if (!session_id() && !headers_sent()) {
            session_start();
        }
    }
    
    public function create_gift_products() {
        // Only run once
        if (get_option('mgc_products_created') === 'yes') {
            return;
        }
        
        $products = [
            500 => __('Gift Card €500', 'massnahme-gift-cards'),
            1000 => __('Gift Card €1,000', 'massnahme-gift-cards'),
            1500 => __('Gift Card €1,500', 'massnahme-gift-cards'),
            2000 => __('Gift Card €2,000', 'massnahme-gift-cards'),
            3000 => __('Gift Card €3,000', 'massnahme-gift-cards')
        ];
        
        foreach ($products as $amount => $title) {
            $this->create_single_product($amount, $title);
        }
        
        update_option('mgc_products_created', 'yes');
    }
    
    private function create_single_product($amount, $title) {
        // Check if exists
        $existing = wc_get_products([
            'sku' => 'MGC-' . $amount,
            'limit' => 1
        ]);

        if (!empty($existing)) {
            return;
        }

        $product = new WC_Product_Simple();
        $product->set_name($title);
        $product->set_sku('MGC-' . $amount);
        $product->set_regular_price($amount);
        $product->set_tax_status('taxable');
        $product->set_tax_class('standard');
        $product->set_catalog_visibility('visible');
        $product->set_virtual(true);
        $product->set_sold_individually(false);
        $product->set_manage_stock(false);
        $product->set_stock_status('instock');
        
        // Add meta to identify as gift card
        $product->add_meta_data('_mgc_gift_card', 'yes', true);
        $product->add_meta_data('_mgc_amount', $amount, true);
        
        $product->save();
    }
    
    public function process_gift_card_order($order_id) {
        $order = wc_get_order($order_id);
        
        // Check if already processed
        if ($order->get_meta('_mgc_processed') === 'yes') {
            return;
        }
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            
            if (!$product || $product->get_meta('_mgc_gift_card') !== 'yes') {
                continue;
            }
            
            $this->create_gift_card($order, $item);
        }
        
        $order->update_meta_data('_mgc_processed', 'yes');
        $order->save();
    }
    
    private function create_gift_card($order, $item) {
        global $wpdb;

        $code = $this->generate_unique_code();
        $amount = $item->get_total();
        $settings = get_option('mgc_settings');

        // Get delivery method and related data
        $delivery_method = $order->get_meta('_mgc_delivery_method') ?: 'digital';
        $pickup_location = $order->get_meta('_mgc_pickup_location');
        $recipient_name = $order->get_meta('_mgc_recipient_name');

        // Insert into database
        $wpdb->insert(
            $wpdb->prefix . 'mgc_gift_cards',
            [
                'code' => $code,
                'amount' => $amount,
                'balance' => $amount,
                'order_id' => $order->get_id(),
                'purchaser_email' => $order->get_billing_email(),
                'recipient_email' => $order->get_meta('_mgc_recipient_email') ?: $order->get_billing_email(),
                'recipient_name' => $recipient_name,
                'message' => $order->get_meta('_mgc_message'),
                'delivery_method' => $delivery_method,
                'pickup_location' => $pickup_location,
                'expires_at' => date('Y-m-d H:i:s', strtotime('+' . $settings['expiry_days'] . ' days')),
                'status' => 'active'
            ]
        );

        // Create WooCommerce coupon
        MGC_Coupon::get_instance()->create_coupon($code, $amount, $order->get_id());

        // Handle based on delivery method
        switch ($delivery_method) {
            case 'digital':
                // Send email immediately or schedule
                MGC_Email::get_instance()->send_gift_card($code, $order);
                break;

            case 'pickup':
                // Send store notification
                MGC_Email::get_instance()->send_store_pickup_notification($code, $order);
                // Send confirmation to purchaser
                MGC_Email::get_instance()->send_pickup_confirmation($code, $order);
                break;

            case 'shipping':
                // Send shipping confirmation to purchaser
                MGC_Email::get_instance()->send_shipping_confirmation($code, $order);
                break;
        }

        // Log the creation with delivery method
        $delivery_labels = [
            'digital' => __('Digital', 'massnahme-gift-cards'),
            'pickup' => __('Store Pickup', 'massnahme-gift-cards'),
            'shipping' => __('Shipping', 'massnahme-gift-cards')
        ];

        $order->add_order_note(
            sprintf(__('Gift card created: %s (Amount: %s, Delivery: %s)', 'massnahme-gift-cards'),
                $code,
                wc_price($amount),
                $delivery_labels[$delivery_method] ?? $delivery_method
            )
        );
    }
    
    private function generate_unique_code() {
        $settings = get_option('mgc_settings');
        $prefix = $settings['code_prefix'] ?: 'MASS';
        
        do {
            $code = sprintf(
                '%s-%d-%s',
                $prefix,
                date('Y'),
                strtoupper(wp_generate_password(6, false))
            );
        } while ($this->code_exists($code));
        
        return $code;
    }
    
    private function code_exists($code) {
        global $wpdb;
        $table = $wpdb->prefix . 'mgc_gift_cards';
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE code = %s",
            $code
        ));
        return $exists > 0;
    }
    
    public function add_checkout_fields($checkout) {
        // Check if cart contains gift card
        if (!$this->cart_has_gift_card()) {
            return;
        }
        
        wc_get_template('checkout-gift-fields.php', [], '', MGC_PLUGIN_DIR . 'templates/');
    }
    
    public function save_checkout_fields($order, $data) {
        // Delivery method
        if (isset($_POST['mgc_delivery_method'])) {
            $order->update_meta_data('_mgc_delivery_method', sanitize_text_field($_POST['mgc_delivery_method']));
        }

        // Digital delivery fields
        if (isset($_POST['mgc_recipient_email'])) {
            $order->update_meta_data('_mgc_recipient_email', sanitize_email($_POST['mgc_recipient_email']));
        }
        if (isset($_POST['mgc_message'])) {
            $order->update_meta_data('_mgc_message', sanitize_textarea_field($_POST['mgc_message']));
        }
        if (isset($_POST['mgc_delivery_date'])) {
            $order->update_meta_data('_mgc_delivery_date', sanitize_text_field($_POST['mgc_delivery_date']));
        }

        // Pickup fields
        if (isset($_POST['mgc_pickup_location'])) {
            $order->update_meta_data('_mgc_pickup_location', sanitize_text_field($_POST['mgc_pickup_location']));
        }

        // Recipient name (for pickup/shipping)
        if (isset($_POST['mgc_recipient_name'])) {
            $order->update_meta_data('_mgc_recipient_name', sanitize_text_field($_POST['mgc_recipient_name']));
        }

        // Clear the session delivery method
        if (isset($_SESSION['mgc_delivery_method'])) {
            unset($_SESSION['mgc_delivery_method']);
        }
    }
    
    private function cart_has_gift_card() {
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            if ($product->get_meta('_mgc_gift_card') === 'yes') {
                return true;
            }
        }
        return false;
    }
    
    public function enqueue_frontend_scripts() {
        $post = get_post();
        $should_enqueue = is_checkout() || ($post && has_shortcode($post->post_content, 'massnahme_gift_balance'));

        if ($should_enqueue) {
            wp_enqueue_style(
                'mgc-frontend',
                MGC_PLUGIN_URL . 'assets/css/frontend.css',
                [],
                MGC_VERSION
            );

            wp_enqueue_script(
                'mgc-frontend',
                MGC_PLUGIN_URL . 'assets/js/frontend.js',
                ['jquery'],
                MGC_VERSION,
                true
            );

            $settings = get_option('mgc_settings', []);
            wp_localize_script('mgc-frontend', 'mgc_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mgc_nonce'),
                'shipping_cost' => floatval($settings['shipping_cost'] ?? 9.95),
                'shipping_enabled' => !empty($settings['enable_shipping'])
            ]);
        }
    }

    /**
     * AJAX handler for setting delivery method in session
     */
    public function ajax_set_delivery_method() {
        check_ajax_referer('mgc_nonce', 'nonce');

        $method = sanitize_text_field($_POST['method'] ?? 'digital');

        if (in_array($method, ['digital', 'pickup', 'shipping'])) {
            $_SESSION['mgc_delivery_method'] = $method;
            wp_send_json_success(['method' => $method]);
        } else {
            wp_send_json_error(__('Invalid delivery method', 'massnahme-gift-cards'));
        }
    }

    /**
     * Add shipping fee for gift card when shipping delivery is selected
     */
    public function add_gift_card_shipping_fee($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        // Check if cart has gift card
        if (!$this->cart_has_gift_card()) {
            return;
        }

        // Check if shipping method is selected
        $delivery_method = $_SESSION['mgc_delivery_method'] ?? 'digital';

        if ($delivery_method !== 'shipping') {
            return;
        }

        $settings = get_option('mgc_settings', []);
        $shipping_cost = floatval($settings['shipping_cost'] ?? 9.95);

        if ($shipping_cost > 0) {
            $cart->add_fee(
                __('Gift Card Luxury Shipping', 'massnahme-gift-cards'),
                $shipping_cost,
                true // taxable
            );
        }
    }
    
    public function ajax_validate_code() {
        check_ajax_referer('mgc_nonce', 'nonce');
        
        $code = sanitize_text_field($_POST['code']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'mgc_gift_cards';
        
        $gift_card = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE code = %s",
            $code
        ));
        
        if (!$gift_card) {
            wp_send_json_error(__('Invalid gift card code', 'massnahme-gift-cards'));
        }
        
        if ($gift_card->status !== 'active') {
            wp_send_json_error(__('This gift card has been used', 'massnahme-gift-cards'));
        }
        
        if (strtotime($gift_card->expires_at) < time()) {
            wp_send_json_error(__('This gift card has expired', 'massnahme-gift-cards'));
        }
        
        wp_send_json_success([
            'balance' => $gift_card->balance,
            'expires' => date_i18n(get_option('date_format'), strtotime($gift_card->expires_at)),
            'message' => sprintf(__('Balance: %s', 'massnahme-gift-cards'), wc_price($gift_card->balance))
        ]);
    }
    
    public function balance_checker_shortcode() {
        ob_start();
        wc_get_template('balance-checker.php', [], '', MGC_PLUGIN_DIR . 'templates/');
        return ob_get_clean();
    }
}