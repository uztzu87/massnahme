<?php
/**
 * Email handling for gift cards
 */
class MGC_Email {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add custom email classes
        add_filter('woocommerce_email_classes', [$this, 'add_email_classes']);
        
        // Schedule delayed emails
        add_action('mgc_send_scheduled_gift_card', [$this, 'send_scheduled_gift_card'], 10, 2);
        
        // Email actions
        add_action('mgc_gift_card_created', [$this, 'trigger_gift_card_email'], 10, 2);
    }
    
    /**
     * Send gift card email
     */
    public function send_gift_card($code, $order) {
        global $wpdb;
        $table = $wpdb->prefix . 'mgc_gift_cards';
        
        // Get gift card details
        $gift_card = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE code = %s",
            $code
        ));
        
        if (!$gift_card) {
            return false;
        }
        
        // Check for scheduled delivery
        $delivery_date = $order->get_meta('_mgc_delivery_date');
        if ($delivery_date && strtotime($delivery_date) > current_time('timestamp')) {
            // Schedule for later
            wp_schedule_single_event(
                strtotime($delivery_date),
                'mgc_send_scheduled_gift_card',
                [$code, $order->get_id()]
            );
            
            // Send confirmation to purchaser
            $this->send_purchase_confirmation($gift_card, $order, $delivery_date);
            return true;
        }
        
        // Send immediately
        return $this->send_gift_card_now($gift_card, $order);
    }
    
    /**
     * Send gift card email immediately
     */
    private function send_gift_card_now($gift_card, $order) {
        $recipient_email = $gift_card->recipient_email;
        $purchaser_email = $gift_card->purchaser_email;
        
        // Prepare email data
        $email_data = [
            'code' => $gift_card->code,
            'amount' => $gift_card->amount,
            'balance' => $gift_card->balance,
            'message' => $gift_card->message,
            'expires_at' => $gift_card->expires_at,
            'purchaser_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'order' => $order
        ];
        
        // Get email template
        $email_content = $this->get_email_template('gift-card', $email_data);
        
        // Email headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('woocommerce_email_from_address') . '>'
        ];
        
        // Send to recipient
        $sent = wp_mail(
            $recipient_email,
            $this->get_email_subject('gift-card', $email_data),
            $email_content,
            $headers,
            $this->get_attachments($gift_card)
        );
        
        // Send copy to purchaser if different
        if ($recipient_email !== $purchaser_email) {
            $this->send_purchase_confirmation($gift_card, $order);
        }
        
        // Log email sent
        if ($sent) {
            $order->add_order_note(sprintf(
                __('Gift card %s emailed to %s', 'massnahme-gift-cards'),
                $gift_card->code,
                $recipient_email
            ));
        }
        
        return $sent;
    }
    
    /**
     * Send purchase confirmation
     */
    private function send_purchase_confirmation($gift_card, $order, $scheduled_date = null) {
        $email_data = [
            'code' => $gift_card->code,
            'amount' => $gift_card->amount,
            'recipient_email' => $gift_card->recipient_email,
            'scheduled_date' => $scheduled_date,
            'order' => $order
        ];
        
        $email_content = $this->get_email_template('purchase-confirmation', $email_data);
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('woocommerce_email_from_address') . '>'
        ];
        
        wp_mail(
            $gift_card->purchaser_email,
            $this->get_email_subject('purchase-confirmation', $email_data),
            $email_content,
            $headers
        );
    }
    
    /**
     * Send scheduled gift card
     */
    public function send_scheduled_gift_card($code, $order_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'mgc_gift_cards';
        
        $gift_card = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE code = %s",
            $code
        ));
        
        if (!$gift_card) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $this->send_gift_card_now($gift_card, $order);
    }
    
    /**
     * Get email template
     */
    private function get_email_template($template_name, $data) {
        ob_start();
        
        // Look for template override in theme
        $template_path = locate_template(
            'massnahme-gift-cards/emails/' . $template_name . '.php'
        );
        
        if (!$template_path) {
            $template_path = MGC_PLUGIN_DIR . 'templates/emails/' . $template_name . '.php';
        }
        
        if (file_exists($template_path)) {
            extract($data);
            include $template_path;
        }
        
        return ob_get_clean();
    }
    
    /**
     * Get email subject
     */
    private function get_email_subject($template_name, $data) {
        $subjects = [
            'gift-card' => sprintf(
                __('Your %s Gift Card from %s', 'massnahme-gift-cards'),
                get_bloginfo('name'),
                $data['purchaser_name'] ?? get_bloginfo('name')
            ),
            'purchase-confirmation' => __('Gift Card Purchase Confirmation', 'massnahme-gift-cards'),
            'balance-update' => __('Gift Card Balance Update', 'massnahme-gift-cards')
        ];
        
        return apply_filters('mgc_email_subject', 
            $subjects[$template_name] ?? __('Gift Card', 'massnahme-gift-cards'),
            $template_name,
            $data
        );
    }
    
    /**
     * Get email attachments (PDF if enabled)
     */
    private function get_attachments($gift_card) {
        $attachments = [];
        
        $settings = get_option('mgc_settings');
        if (!empty($settings['enable_pdf'])) {
            $pdf_path = $this->generate_pdf($gift_card);
            if ($pdf_path && file_exists($pdf_path)) {
                $attachments[] = $pdf_path;
            }
        }
        
        return $attachments;
    }
    
    /**
     * Generate PDF for gift card
     */
    private function generate_pdf($gift_card) {
        // Check if PDF library is available
        if (!class_exists('TCPDF') && file_exists(MGC_PLUGIN_DIR . 'lib/tcpdf/tcpdf.php')) {
            require_once MGC_PLUGIN_DIR . 'lib/tcpdf/tcpdf.php';
        }
        
        if (!class_exists('TCPDF')) {
            // Fallback to HTML
            return $this->generate_html_pdf($gift_card);
        }
        
        // Generate actual PDF using TCPDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('Massnahme Gift Cards');
        $pdf->SetAuthor(get_bloginfo('name'));
        $pdf->SetTitle('Gift Card - ' . $gift_card->code);
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Add page
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('helvetica', '', 12);
        
        // Add content
        $html = $this->get_pdf_content($gift_card);
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Save PDF
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/mgc-gift-cards/';
        
        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
        }
        
        $pdf_filename = sanitize_file_name('gift-card-' . $gift_card->code . '.pdf');
        $pdf_path = $pdf_dir . $pdf_filename;
        $pdf->Output($pdf_path, 'F');
        
        return $pdf_path;
    }
    
    /**
     * Generate HTML-based PDF alternative
     */
    private function generate_html_pdf($gift_card) {
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/mgc-gift-cards/';
        
        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
        }
        
        $html_content = $this->get_pdf_content($gift_card);
        $filename = sanitize_file_name('gift-card-' . $gift_card->code . '.html');
        $filepath = $pdf_dir . $filename;

        file_put_contents($filepath, $html_content);

        return $filepath;
    }
    
    /**
     * Get PDF content
     */
    private function get_pdf_content($gift_card) {
        ob_start();
        include MGC_PLUGIN_DIR . 'templates/pdf/gift-card-pdf.php';
        return ob_get_clean();
    }
}