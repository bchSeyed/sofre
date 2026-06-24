<?php
/**
 * روش‌های حمل و نقل اختصاصی
 */
if (!defined('ABSPATH')) exit;

class Sofre_Shipping {
    private static $instance = null;
    public static function instance() {
        if (is_null(self::$instance)) self::$instance = new self();
        return self::$instance;
    }
    public function __construct() {
        add_action('woocommerce_shipping_init', array($this, 'init_methods'));
        add_filter('woocommerce_shipping_methods', array($this, 'add_methods'));
    }
    public function init_methods() {
        require_once SF_PATH . 'includes/shipping/class-shipping-motorcycle.php';
        require_once SF_PATH . 'includes/shipping/class-shipping-pickup.php';
        require_once SF_PATH . 'includes/shipping/class-shipping-serving.php';
    }
    public function add_methods($methods) {
        $methods['sf_motorcycle'] = 'Sofre_Shipping_Motorcycle';
        $methods['sf_pickup'] = 'Sofre_Shipping_Pickup';
        $methods['sf_serving'] = 'Sofre_Shipping_Serving';
        return $methods;
    }
}
Sofre_Shipping::instance();