<?php
/**
 * Frontend Admin Dashboard Template
 * Secure admin interface with login requirement and activity tracking
 */
defined('ABSPATH') || exit;

// Check if user is logged in
if (!is_user_logged_in()) {
    ?>
    <div class="mgc-admin-login-required">
        <div class="mgc-login-box">
            <div class="mgc-login-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
            </div>
            <h2><?php _e('Admin Access Required', 'massnahme-gift-cards'); ?></h2>
            <p><?php _e('Please log in to access the admin dashboard.', 'massnahme-gift-cards'); ?></p>
            <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="mgc-admin-login-btn">
                <?php _e('Log In', 'massnahme-gift-cards'); ?>
            </a>
        </div>
    </div>
    <style>
    .mgc-admin-login-required {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 400px;
        padding: 40px 20px;
    }
    .mgc-login-box {
        text-align: center;
        background: #fff;
        padding: 50px 40px;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        max-width: 400px;
    }
    .mgc-login-icon {
        color: #2271b1;
        margin-bottom: 20px;
    }
    .mgc-login-box h2 {
        margin: 0 0 15px 0;
        color: #1d2327;
    }
    .mgc-login-box p {
        color: #646970;
        margin-bottom: 25px;
    }
    .mgc-admin-login-btn {
        display: inline-block;
        background: #2271b1;
        color: #fff !important;
        padding: 12px 30px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 600;
        transition: background 0.2s;
    }
    .mgc-admin-login-btn:hover {
        background: #135e96;
    }
    </style>
    <?php
    return;
}

// Check if user has permission
if (!current_user_can('manage_woocommerce')) {
    ?>
    <div class="mgc-admin-no-permission">
        <div class="mgc-permission-box">
            <div class="mgc-permission-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                </svg>
            </div>
            <h2><?php _e('Access Denied', 'massnahme-gift-cards'); ?></h2>
            <p><?php _e('You do not have permission to access this page.', 'massnahme-gift-cards'); ?></p>
        </div>
    </div>
    <style>
    .mgc-admin-no-permission {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 400px;
        padding: 40px 20px;
    }
    .mgc-permission-box {
        text-align: center;
        background: #fff;
        padding: 50px 40px;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        max-width: 400px;
    }
    .mgc-permission-icon {
        color: #dc3545;
        margin-bottom: 20px;
    }
    .mgc-permission-box h2 {
        margin: 0 0 15px 0;
        color: #721c24;
    }
    .mgc-permission-box p {
        color: #646970;
    }
    </style>
    <?php
    return;
}

// User is logged in and has permission - log the page view
$current_user = wp_get_current_user();
MGC_Core::get_instance()->log_admin_activity('page_view', __('Viewed frontend admin dashboard', 'massnahme-gift-cards'));

// Get data
global $wpdb;
$table = $wpdb->prefix . 'mgc_gift_cards';
$activity_table = $wpdb->prefix . 'mgc_admin_activity';

// Statistics
$total_cards = $wpdb->get_var("SELECT COUNT(*) FROM $table");
$active_cards = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'active'");
$total_value = $wpdb->get_var("SELECT SUM(amount) FROM $table") ?: 0;
$remaining_value = $wpdb->get_var("SELECT SUM(balance) FROM $table WHERE status = 'active'") ?: 0;

// Recent gift cards
$recent_cards = $wpdb->get_results(
    "SELECT * FROM $table ORDER BY created_at DESC LIMIT 10"
);

// Recent activity (current user)
$my_activity = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $activity_table WHERE user_id = %d ORDER BY created_at DESC LIMIT 20",
    $current_user->ID
));

// All recent activity (for admins)
$all_activity = $wpdb->get_results(
    "SELECT * FROM $activity_table ORDER BY created_at DESC LIMIT 50"
);

$currency_symbol = html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8');
?>

<div class="mgc-frontend-admin">
    <!-- Header with User Info -->
    <div class="mgc-admin-header">
        <div class="mgc-admin-title">
            <h1><?php _e('Gift Cards Admin', 'massnahme-gift-cards'); ?></h1>
            <p class="mgc-admin-subtitle"><?php echo esc_html(get_bloginfo('name')); ?></p>
        </div>
        <div class="mgc-user-info">
            <div class="mgc-user-avatar">
                <?php echo get_avatar($current_user->ID, 48); ?>
            </div>
            <div class="mgc-user-details">
                <span class="mgc-user-name"><?php echo esc_html($current_user->display_name); ?></span>
                <span class="mgc-user-id">ID: <?php echo esc_html($current_user->ID); ?></span>
                <span class="mgc-user-role"><?php echo esc_html(implode(', ', $current_user->roles)); ?></span>
            </div>
            <a href="<?php echo esc_url(wp_logout_url(get_permalink())); ?>" class="mgc-logout-btn">
                <?php _e('Logout', 'massnahme-gift-cards'); ?>
            </a>
        </div>
    </div>

    <!-- Statistics -->
    <div class="mgc-stats-grid">
        <div class="mgc-stat-card">
            <div class="mgc-stat-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                    <line x1="1" y1="10" x2="23" y2="10"></line>
                </svg>
            </div>
            <div class="mgc-stat-content">
                <span class="mgc-stat-value"><?php echo number_format($total_cards); ?></span>
                <span class="mgc-stat-label"><?php _e('Total Gift Cards', 'massnahme-gift-cards'); ?></span>
            </div>
        </div>
        <div class="mgc-stat-card">
            <div class="mgc-stat-icon mgc-stat-icon-success">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
            <div class="mgc-stat-content">
                <span class="mgc-stat-value"><?php echo number_format($active_cards); ?></span>
                <span class="mgc-stat-label"><?php _e('Active Cards', 'massnahme-gift-cards'); ?></span>
            </div>
        </div>
        <div class="mgc-stat-card">
            <div class="mgc-stat-icon mgc-stat-icon-info">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
            </div>
            <div class="mgc-stat-content">
                <span class="mgc-stat-value"><?php echo esc_html($currency_symbol . number_format($total_value, 2, ',', '.')); ?></span>
                <span class="mgc-stat-label"><?php _e('Total Value Sold', 'massnahme-gift-cards'); ?></span>
            </div>
        </div>
        <div class="mgc-stat-card">
            <div class="mgc-stat-icon mgc-stat-icon-warning">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </div>
            <div class="mgc-stat-content">
                <span class="mgc-stat-value"><?php echo esc_html($currency_symbol . number_format($remaining_value, 2, ',', '.')); ?></span>
                <span class="mgc-stat-label"><?php _e('Outstanding Balance', 'massnahme-gift-cards'); ?></span>
            </div>
        </div>
    </div>

    <!-- Main Content Tabs -->
    <div class="mgc-admin-tabs">
        <button class="mgc-tab-btn active" data-tab="gift-cards"><?php _e('Gift Cards', 'massnahme-gift-cards'); ?></button>
        <button class="mgc-tab-btn" data-tab="my-activity"><?php _e('My Activity', 'massnahme-gift-cards'); ?></button>
        <button class="mgc-tab-btn" data-tab="all-activity"><?php _e('All Activity', 'massnahme-gift-cards'); ?></button>
    </div>

    <!-- Gift Cards Tab -->
    <div class="mgc-tab-content active" id="tab-gift-cards">
        <div class="mgc-card-list">
            <h3><?php _e('Recent Gift Cards', 'massnahme-gift-cards'); ?></h3>
            <?php if ($recent_cards): ?>
                <table class="mgc-admin-table">
                    <thead>
                        <tr>
                            <th><?php _e('Code', 'massnahme-gift-cards'); ?></th>
                            <th><?php _e('Amount', 'massnahme-gift-cards'); ?></th>
                            <th><?php _e('Balance', 'massnahme-gift-cards'); ?></th>
                            <th><?php _e('Status', 'massnahme-gift-cards'); ?></th>
                            <th><?php _e('Created', 'massnahme-gift-cards'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_cards as $card): ?>
                            <tr>
                                <td><strong><?php echo esc_html($card->code); ?></strong></td>
                                <td><?php echo esc_html($currency_symbol . number_format($card->amount, 2, ',', '.')); ?></td>
                                <td><?php echo esc_html($currency_symbol . number_format($card->balance, 2, ',', '.')); ?></td>
                                <td>
                                    <span class="mgc-status mgc-status-<?php echo esc_attr($card->status); ?>">
                                        <?php echo esc_html(ucfirst($card->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($card->created_at)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="mgc-no-data"><?php _e('No gift cards found.', 'massnahme-gift-cards'); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- My Activity Tab -->
    <div class="mgc-tab-content" id="tab-my-activity">
        <h3><?php _e('My Recent Activity', 'massnahme-gift-cards'); ?></h3>
        <?php if ($my_activity): ?>
            <div class="mgc-activity-list">
                <?php foreach ($my_activity as $activity): ?>
                    <div class="mgc-activity-item">
                        <div class="mgc-activity-icon mgc-activity-<?php echo esc_attr($activity->action_type); ?>">
                            <?php echo mgc_get_activity_icon($activity->action_type); ?>
                        </div>
                        <div class="mgc-activity-content">
                            <div class="mgc-activity-action"><?php echo esc_html($activity->action_details); ?></div>
                            <?php if ($activity->gift_card_code): ?>
                                <div class="mgc-activity-code">
                                    <?php _e('Gift Card:', 'massnahme-gift-cards'); ?>
                                    <strong><?php echo esc_html($activity->gift_card_code); ?></strong>
                                </div>
                            <?php endif; ?>
                            <div class="mgc-activity-meta">
                                <span class="mgc-activity-time">
                                    <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($activity->created_at)); ?>
                                </span>
                                <span class="mgc-activity-ip">IP: <?php echo esc_html($activity->ip_address); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="mgc-no-data"><?php _e('No activity recorded yet.', 'massnahme-gift-cards'); ?></p>
        <?php endif; ?>
    </div>

    <!-- All Activity Tab -->
    <div class="mgc-tab-content" id="tab-all-activity">
        <h3><?php _e('All Admin Activity', 'massnahme-gift-cards'); ?></h3>
        <?php if ($all_activity): ?>
            <div class="mgc-activity-list">
                <?php foreach ($all_activity as $activity): ?>
                    <div class="mgc-activity-item">
                        <div class="mgc-activity-user">
                            <?php echo get_avatar($activity->user_id, 36); ?>
                        </div>
                        <div class="mgc-activity-content">
                            <div class="mgc-activity-header">
                                <span class="mgc-activity-username"><?php echo esc_html($activity->user_display_name); ?></span>
                                <span class="mgc-activity-userid">(ID: <?php echo esc_html($activity->user_id); ?>)</span>
                            </div>
                            <div class="mgc-activity-action"><?php echo esc_html($activity->action_details); ?></div>
                            <?php if ($activity->gift_card_code): ?>
                                <div class="mgc-activity-code">
                                    <?php _e('Gift Card:', 'massnahme-gift-cards'); ?>
                                    <strong><?php echo esc_html($activity->gift_card_code); ?></strong>
                                </div>
                            <?php endif; ?>
                            <div class="mgc-activity-meta">
                                <span class="mgc-activity-time">
                                    <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($activity->created_at)); ?>
                                </span>
                                <span class="mgc-activity-ip">IP: <?php echo esc_html($activity->ip_address); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="mgc-no-data"><?php _e('No activity recorded yet.', 'massnahme-gift-cards'); ?></p>
        <?php endif; ?>
    </div>

    <!-- Session Info Footer -->
    <div class="mgc-session-info">
        <div class="mgc-session-item">
            <span class="mgc-session-label"><?php _e('Session Started:', 'massnahme-gift-cards'); ?></span>
            <span class="mgc-session-value"><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format')); ?></span>
        </div>
        <div class="mgc-session-item">
            <span class="mgc-session-label"><?php _e('Your IP:', 'massnahme-gift-cards'); ?></span>
            <span class="mgc-session-value"><?php echo esc_html(MGC_Core::get_instance()->get_client_ip_public()); ?></span>
        </div>
    </div>
</div>

<?php
// Helper function for activity icons
function mgc_get_activity_icon($type) {
    $icons = [
        'page_view' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>',
        'lookup' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
        'redemption' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>',
        'balance_update' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>',
        'status_change' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>',
        'create_gift_card' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>',
    ];
    return $icons[$type] ?? $icons['page_view'];
}
?>

<style>
.mgc-frontend-admin {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

/* Header */
.mgc-admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: linear-gradient(135deg, #1e3a5f 0%, #2271b1 100%);
    border-radius: 12px;
    margin-bottom: 25px;
    color: #fff;
}

.mgc-admin-title h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
}

.mgc-admin-subtitle {
    margin: 5px 0 0 0;
    opacity: 0.8;
}

.mgc-user-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.mgc-user-avatar img {
    border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.3);
}

.mgc-user-details {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.mgc-user-name {
    font-weight: 600;
    font-size: 16px;
}

.mgc-user-id {
    font-size: 12px;
    opacity: 0.8;
    background: rgba(255,255,255,0.2);
    padding: 2px 8px;
    border-radius: 10px;
}

.mgc-user-role {
    font-size: 12px;
    opacity: 0.7;
    text-transform: capitalize;
}

.mgc-logout-btn {
    background: rgba(255,255,255,0.2);
    color: #fff !important;
    padding: 8px 16px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
    transition: background 0.2s;
}

.mgc-logout-btn:hover {
    background: rgba(255,255,255,0.3);
}

/* Stats Grid */
.mgc-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.mgc-stat-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.mgc-stat-icon {
    width: 60px;
    height: 60px;
    background: #e8f4fc;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2271b1;
}

.mgc-stat-icon-success {
    background: #d4edda;
    color: #155724;
}

.mgc-stat-icon-info {
    background: #cce5ff;
    color: #004085;
}

.mgc-stat-icon-warning {
    background: #fff3cd;
    color: #856404;
}

.mgc-stat-content {
    display: flex;
    flex-direction: column;
}

.mgc-stat-value {
    font-size: 24px;
    font-weight: 700;
    color: #1d2327;
}

.mgc-stat-label {
    font-size: 14px;
    color: #646970;
}

/* Tabs */
.mgc-admin-tabs {
    display: flex;
    gap: 5px;
    margin-bottom: 20px;
    background: #f0f0f0;
    padding: 5px;
    border-radius: 10px;
}

.mgc-tab-btn {
    flex: 1;
    padding: 12px 20px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 15px;
    font-weight: 600;
    color: #646970;
    border-radius: 8px;
    transition: all 0.2s;
}

.mgc-tab-btn:hover {
    background: rgba(255,255,255,0.5);
}

.mgc-tab-btn.active {
    background: #fff;
    color: #2271b1;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.mgc-tab-content {
    display: none;
    background: #fff;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.mgc-tab-content.active {
    display: block;
}

.mgc-tab-content h3 {
    margin: 0 0 20px 0;
    color: #1d2327;
}

/* Table */
.mgc-admin-table {
    width: 100%;
    border-collapse: collapse;
}

.mgc-admin-table th,
.mgc-admin-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.mgc-admin-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #1d2327;
}

.mgc-admin-table tr:hover {
    background: #f8f9fa;
}

/* Status badges */
.mgc-status {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
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

/* Activity List */
.mgc-activity-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.mgc-activity-item {
    display: flex;
    gap: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    transition: background 0.2s;
}

.mgc-activity-item:hover {
    background: #f0f0f0;
}

.mgc-activity-icon,
.mgc-activity-user {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.mgc-activity-icon {
    background: #e8f4fc;
    color: #2271b1;
}

.mgc-activity-user img {
    border-radius: 50%;
}

.mgc-activity-lookup {
    background: #e3f2fd;
    color: #1565c0;
}

.mgc-activity-redemption {
    background: #e8f5e9;
    color: #2e7d32;
}

.mgc-activity-balance_update {
    background: #fff3e0;
    color: #e65100;
}

.mgc-activity-status_change {
    background: #f3e5f5;
    color: #7b1fa2;
}

.mgc-activity-create_gift_card {
    background: #e0f2f1;
    color: #00695c;
}

.mgc-activity-content {
    flex: 1;
}

.mgc-activity-header {
    margin-bottom: 4px;
}

.mgc-activity-username {
    font-weight: 600;
    color: #1d2327;
}

.mgc-activity-userid {
    font-size: 12px;
    color: #646970;
}

.mgc-activity-action {
    color: #1d2327;
    margin-bottom: 4px;
}

.mgc-activity-code {
    font-size: 13px;
    color: #2271b1;
    margin-bottom: 4px;
}

.mgc-activity-meta {
    display: flex;
    gap: 15px;
    font-size: 12px;
    color: #646970;
}

/* Session Info */
.mgc-session-info {
    display: flex;
    justify-content: center;
    gap: 30px;
    margin-top: 25px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
}

.mgc-session-item {
    display: flex;
    gap: 8px;
    font-size: 13px;
}

.mgc-session-label {
    color: #646970;
}

.mgc-session-value {
    font-weight: 600;
    color: #1d2327;
}

/* No data */
.mgc-no-data {
    text-align: center;
    color: #646970;
    padding: 30px;
}

/* Responsive */
@media (max-width: 768px) {
    .mgc-admin-header {
        flex-direction: column;
        text-align: center;
        gap: 20px;
    }

    .mgc-user-info {
        flex-direction: column;
    }

    .mgc-stats-grid {
        grid-template-columns: 1fr;
    }

    .mgc-admin-tabs {
        flex-direction: column;
    }

    .mgc-admin-table {
        font-size: 14px;
    }

    .mgc-admin-table th,
    .mgc-admin-table td {
        padding: 8px 10px;
    }

    .mgc-session-info {
        flex-direction: column;
        gap: 10px;
        align-items: center;
    }

    .mgc-activity-meta {
        flex-direction: column;
        gap: 5px;
    }
}
</style>

<script>
(function($) {
    'use strict';

    // Tab switching
    $('.mgc-tab-btn').on('click', function() {
        var tab = $(this).data('tab');

        // Update button states
        $('.mgc-tab-btn').removeClass('active');
        $(this).addClass('active');

        // Show corresponding content
        $('.mgc-tab-content').removeClass('active');
        $('#tab-' + tab).addClass('active');
    });

})(jQuery);
</script>
