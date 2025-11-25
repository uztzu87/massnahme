<?php
/**
 * Admin functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MGC_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', [$this, 'add_menu_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_filter('woocommerce_screen_ids', [$this, 'add_screen_ids']);

        // AJAX handlers
        add_action('wp_ajax_mgc_update_balance', [$this, 'ajax_update_balance']);
    }
    
    public function add_menu_pages() {
        add_menu_page(
            __('Gift Cards', 'massnahme-gift-cards'),
            __('Gift Cards', 'massnahme-gift-cards'),
            'manage_woocommerce',
            'mgc-gift-cards',
            [$this, 'dashboard_page'],
            'dashicons-tickets-alt',
            56
        );
        
        add_submenu_page(
            'mgc-gift-cards',
            __('All Gift Cards', 'massnahme-gift-cards'),
            __('All Gift Cards', 'massnahme-gift-cards'),
            'manage_woocommerce',
            'mgc-gift-cards',
            [$this, 'dashboard_page']
        );
        
        add_submenu_page(
            'mgc-gift-cards',
            __('Validate', 'massnahme-gift-cards'),
            __('Validate', 'massnahme-gift-cards'),
            'manage_woocommerce',
            'mgc-validate',
            [$this, 'validate_page']
        );
        
        add_submenu_page(
            'mgc-gift-cards',
            __('Settings', 'massnahme-gift-cards'),
            __('Settings', 'massnahme-gift-cards'),
            'manage_woocommerce',
            'mgc-settings',
            [$this, 'settings_page']
        );
    }
    
    public function dashboard_page() {
        require_once MGC_PLUGIN_DIR . 'templates/admin-dashboard.php';
    }
    
    public function validate_page() {
        require_once MGC_PLUGIN_DIR . 'templates/admin-validator.php';
    }
    
    public function settings_page() {
        // Handle form submission
        if (isset($_POST['mgc_save_settings'])) {
            check_admin_referer('mgc_settings_nonce');
            
            $settings = [
                'expiry_days' => intval($_POST['expiry_days']),
                'code_prefix' => sanitize_text_field($_POST['code_prefix']),
                'enable_pdf' => isset($_POST['enable_pdf']),
                'enable_qr' => isset($_POST['enable_qr'])
            ];
            
            update_option('mgc_settings', $settings);
            
            echo '<div class="notice notice-success"><p>' . __('Settings saved', 'massnahme-gift-cards') . '</p></div>';
        }
        
        require_once MGC_PLUGIN_DIR . 'templates/admin-settings.php';
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'mgc-') === false) {
            return;
        }
        
        wp_enqueue_style(
            'mgc-admin',
            MGC_PLUGIN_URL . 'assets/css/admin.css',
            [],
            MGC_VERSION
        );
        
        wp_enqueue_script(
            'mgc-admin',
            MGC_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            MGC_VERSION,
            true
        );
        
        wp_localize_script('mgc-admin', 'mgc_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mgc_admin_nonce'),
            'currency' => get_woocommerce_currency()
        ]);
    }
    
    public function add_screen_ids($screen_ids) {
        $screen_ids[] = 'toplevel_page_mgc-gift-cards';
        $screen_ids[] = 'gift-cards_page_mgc-validate';
        $screen_ids[] = 'gift-cards_page_mgc-settings';
        return $screen_ids;
    }

    /**
     * AJAX handler for updating gift card balance
     */
    public function ajax_update_balance() {
        // Security checks
        check_ajax_referer('mgc_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied', 'massnahme-gift-cards'));
        }

        $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
        $new_balance = isset($_POST['balance']) ? floatval($_POST['balance']) : -1;

        if (empty($code)) {
            wp_send_json_error(__('Invalid gift card code', 'massnahme-gift-cards'));
        }

        if ($new_balance < 0) {
            wp_send_json_error(__('Balance cannot be negative', 'massnahme-gift-cards'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'mgc_gift_cards';

        // Get current gift card data
        $gift_card = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE code = %s",
            $code
        ));

        if (!$gift_card) {
            wp_send_json_error(__('Gift card not found', 'massnahme-gift-cards'));
        }

        // Balance cannot exceed original amount
        if ($new_balance > floatval($gift_card->amount)) {
            wp_send_json_error(__('Balance cannot exceed original amount', 'massnahme-gift-cards'));
        }

        $old_balance = floatval($gift_card->balance);

        // Determine new status based on balance
        $new_status = $new_balance > 0 ? 'active' : 'used';

        // Update database
        $updated = $wpdb->update(
            $table,
            [
                'balance' => $new_balance,
                'status' => $new_status
            ],
            ['code' => $code],
            ['%f', '%s'],
            ['%s']
        );

        if ($updated === false) {
            wp_send_json_error(__('Failed to update balance', 'massnahme-gift-cards'));
        }

        // Update WooCommerce coupon meta
        $coupon = new WC_Coupon($code);
        if ($coupon->get_id()) {
            $coupon->update_meta_data('_mgc_balance', $new_balance);
            $coupon->save();
        }

        // Log the manual balance change
        $wpdb->insert(
            $wpdb->prefix . 'mgc_gift_card_usage',
            [
                'gift_card_code' => $code,
                'order_id' => 0, // 0 indicates manual adjustment
                'amount_used' => $old_balance - $new_balance,
                'remaining_balance' => $new_balance,
                'used_at' => current_time('mysql')
            ]
        );

        wp_send_json_success([
            'message' => __('Balance updated successfully', 'massnahme-gift-cards'),
            'new_balance' => $new_balance,
            'new_status' => $new_status,
            'formatted_balance' => wc_price($new_balance)
        ]);
    }
}