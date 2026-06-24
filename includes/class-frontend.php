<?php
/**
 * کلاس فرانت‌اند - مدیریت خروجی و توابع نمایشی
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sofre_Frontend {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // نمایش ستون وضعیت سفارش در حساب کاربری
        add_filter('woocommerce_my_account_my_orders_columns', array($this, 'add_status_column'));
        add_action('woocommerce_my_account_my_orders_column_sf-status', array($this, 'render_status_column'));
        
        // افزودن اطلاعات تحویل در صفحه تسویه حساب
        add_action('woocommerce_checkout_before_customer_details', array($this, 'checkout_delivery_info'));
    }

    public function add_status_column($columns) {
        $new_columns = array();
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            if ($key === 'order-total') {
                $new_columns['sf-status'] = 'وضعیت';
            }
        }
        return $new_columns;
    }

    public function render_status_column($order) {
        $status = $order->get_status();
        $labels = array(
            'sf-pending' => 'در انتظار تایید',
            'sf-preparing' => 'در حال آماده‌سازی',
            'sf-ready' => 'آماده تحویل',
            'sf-delivering' => 'در حال ارسال',
            'sf-delivered' => 'تحویل شده',
            'processing' => 'در حال پردازش',
            'on-hold' => 'در انتظار',
            'completed' => 'تکمیل شده',
            'cancelled' => 'لغو شده',
        );
        
        $label = isset($labels[$status]) ? $labels[$status] : $status;
        $class = str_replace('sf-', '', $status);
        echo '<span class="sf-status-badge sf-badge-' . esc_attr($class) . '">' . esc_html($label) . '</span>';
    }

    public function checkout_delivery_info() {
        $min_order = get_option('sf_min_order_amount', 0);
        $delivery_fee = get_option('sf_delivery_fee', 0);
        $free_delivery = get_option('sf_free_delivery_min', 0);
        $restaurant_name = get_option('sf_restaurant_name', get_bloginfo('name'));
        
        if ($min_order || $delivery_fee || $free_delivery) {
            echo '<div class="sf-checkout-info">';
            echo '<h3>' . esc_html($restaurant_name) . ' - اطلاعات ارسال</h3>';
            echo '<ul>';
            if ($min_order > 0) {
                echo '<li>حداقل سفارش: ' . wc_price($min_order) . '</li>';
            }
            if ($delivery_fee > 0) {
                echo '<li>هزینه ارسال: ' . wc_price($delivery_fee) . '</li>';
            }
            if ($free_delivery > 0) {
                echo '<li>ارسال رایگان برای سفارشات بالای ' . wc_price($free_delivery) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
    }
}

Sofre_Frontend::instance();