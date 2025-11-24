<?php
/**
 * Admin Dashboard Template
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'mgc_gift_cards';

// Get statistics
$total_cards = $wpdb->get_var("SELECT COUNT(*) FROM $table");
$active_cards = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'active'");
$total_value = $wpdb->get_var("SELECT SUM(amount) FROM $table") ?: 0;
$remaining_value = $wpdb->get_var("SELECT SUM(balance) FROM $table WHERE status = 'active'") ?: 0;

// Get recent gift cards
$recent_cards = $wpdb->get_results(
    "SELECT * FROM $table ORDER BY created_at DESC LIMIT 20"
);
?>

<div class="wrap">
    <h1><?php _e('Gift Cards Dashboard', 'massnahme-gift-cards'); ?></h1>

    <div class="mgc-dashboard">
        <!-- Statistics Cards -->
        <div class="mgc-stats-grid">
            <div class="mgc-stat-card">
                <h3><?php echo number_format($total_cards); ?></h3>
                <p><?php _e('Total Gift Cards', 'massnahme-gift-cards'); ?></p>
            </div>

            <div class="mgc-stat-card">
                <h3><?php echo number_format($active_cards); ?></h3>
                <p><?php _e('Active Cards', 'massnahme-gift-cards'); ?></p>
            </div>

            <div class="mgc-stat-card">
                <h3><?php echo wc_price($total_value); ?></h3>
                <p><?php _e('Total Value Sold', 'massnahme-gift-cards'); ?></p>
            </div>

            <div class="mgc-stat-card">
                <h3><?php echo wc_price($remaining_value); ?></h3>
                <p><?php _e('Outstanding Balance', 'massnahme-gift-cards'); ?></p>
            </div>
        </div>

        <!-- Recent Gift Cards Table -->
        <div class="mgc-recent-cards">
            <h2><?php _e('Recent Gift Cards', 'massnahme-gift-cards'); ?></h2>

            <?php if ($recent_cards): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Code', 'massnahme-gift-cards'); ?></th>
                            <th><?php _e('Amount', 'massnahme-gift-cards'); ?></th>
                            <th><?php _e('Balance', 'massnahme-gift-cards'); ?></th>
                            <th><?php _e('Recipient', 'massnahme-gift-cards'); ?></th>
                            <th><?php _e('Status', 'massnahme-gift-cards'); ?></th>
                            <th><?php _e('Created', 'massnahme-gift-cards'); ?></th>
                            <th><?php _e('Expires', 'massnahme-gift-cards'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_cards as $card): ?>
                            <tr>
                                <td><strong><?php echo esc_html($card->code); ?></strong></td>
                                <td><?php echo wc_price($card->amount); ?></td>
                                <td><?php echo wc_price($card->balance); ?></td>
                                <td><?php echo esc_html($card->recipient_email); ?></td>
                                <td>
                                    <span class="mgc-status mgc-status-<?php echo esc_attr($card->status); ?>">
                                        <?php echo esc_html(ucfirst($card->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo date_i18n(get_option('date_format'), strtotime($card->created_at)); ?></td>
                                <td><?php echo date_i18n(get_option('date_format'), strtotime($card->expires_at)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('No gift cards found.', 'massnahme-gift-cards'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.mgc-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.mgc-stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
}

.mgc-stat-card h3 {
    margin: 0 0 10px 0;
    font-size: 32px;
    color: #2271b1;
}

.mgc-stat-card p {
    margin: 0;
    color: #646970;
}

.mgc-recent-cards {
    margin-top: 30px;
}

.mgc-status {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
}

.mgc-status-active {
    background: #d4edda;
    color: #155724;
}

.mgc-status-used {
    background: #f8d7da;
    color: #721c24;
}

.mgc-status-expired {
    background: #fff3cd;
    color: #856404;
}
</style>
