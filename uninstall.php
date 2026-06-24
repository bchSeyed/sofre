<?php
/**
 * حذف کامل داده‌های سفره هنگام حذف افزونه از وردپرس
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$options = array(
    'sf_restaurant_name',
    'sf_restaurant_address',
    'sf_restaurant_phone',
    'sf_restaurant_logo',
    'sf_primary_color',
    'sf_secondary_color',
    'sf_bg_color',
    'sf_card_bg_color',
    'sf_text_color',
    'sf_enable_delivery',
    'sf_delivery_fee',
    'sf_free_delivery_min',
    'sf_min_order_amount',
    'sf_is_open',
    'sf_business_hours',
    'sf_notification_sound',
    'sf_enable_ordering',
    'sf_menu_page_id',
);

$page_id = (int) get_option('sf_menu_page_id');
if ($page_id) {
    wp_delete_post($page_id, true);
}

foreach ($options as $option) {
    delete_option($option);
}

$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'sf_otp_%'");