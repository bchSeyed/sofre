<?php if (!defined('ABSPATH')) exit;
class Sofre_Shipping_Pickup extends WC_Shipping_Method {
    public function __construct($instance_id = 0) {
        $this->id = 'sf_pickup';
        $this->instance_id = absint($instance_id);
        $this->method_title = 'تحویل حضوری';
        $this->method_description = 'مشتری سفارش را از رستوران تحویل می‌گیرد';
        $this->supports = array('settings', 'shipping-zones', 'instance-settings');
        $this->init();
    }
    public function init() {
        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->get_option('title', 'تحویل حضوری');
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array('title' => 'فعال/غیرفعال', 'type' => 'checkbox', 'label' => 'فعال باشد', 'default' => 'yes'),
            'title' => array('title' => 'عنوان', 'type' => 'text', 'default' => 'تحویل حضوری'),
        );
    }
    public function calculate_shipping($package = array()) {
        $this->add_rate(array('id' => $this->id, 'label' => $this->title, 'cost' => 0, 'package' => $package));
    }
}