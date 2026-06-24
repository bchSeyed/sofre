<?php
if (!defined('ABSPATH')) exit;
class Sofre_Shipping_Motorcycle extends WC_Shipping_Method {
    public function __construct($instance_id = 0) {
        $this->id = 'sf_motorcycle';
        $this->instance_id = absint($instance_id);
        $this->method_title = 'ارسال با پیک موتوری';
        $this->method_description = 'ارسال سفارش با پیک موتوری درب منزل';
        $this->supports = array('settings', 'shipping-zones', 'instance-settings');
        $this->init();
    }
    public function init() {
        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->get_option('title', 'ارسال با پیک موتوری');
        $this->cost = $this->get_option('cost', 0);
        $this->free_min = $this->get_option('free_min', 0);
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array('title' => 'فعال/غیرفعال', 'type' => 'checkbox', 'label' => 'فعال باشد', 'default' => 'yes'),
            'title' => array('title' => 'عنوان', 'type' => 'text', 'default' => 'ارسال با پیک موتوری'),
            'cost' => array('title' => 'هزینه ارسال (تومان)', 'type' => 'number', 'default' => '0', 'custom_attributes' => array('min' => '0', 'step' => '1000')),
            'free_min' => array('title' => 'ارسال رایگان از مبلغ (تومان)', 'type' => 'number', 'default' => '0', 'custom_attributes' => array('min' => '0', 'step' => '1000')),
        );
    }
    public function calculate_shipping($package = array()) {
        $cost = intval($this->cost);
        $cart_total = WC()->cart ? WC()->cart->get_subtotal() : 0;
        if ($this->free_min > 0 && $cart_total >= intval($this->free_min)) {
            $cost = 0;
        }
        $this->add_rate(array(
            'id' => $this->id,
            'label' => $this->title,
            'cost' => $cost,
            'package' => $package,
        ));
    }
}