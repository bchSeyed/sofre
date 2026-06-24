<?php
/**
 * کلاس خدمات و روش‌های تحویل - Delivery / Pickup / Serving
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sofre_Services {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // ثبت شورتکد انتخاب روش تحویل
        add_shortcode('sofre_services', array($this, 'render_services_selector'));
        
        // Ajax برای ذخیره روش تحویل انتخابی
        add_action('wp_ajax_sf_set_service', array($this, 'ajax_set_service'));
        add_action('wp_ajax_nopriv_sf_set_service', array($this, 'ajax_set_service'));
        
        // نمایش انتخاب روش در Checkout
        add_action('woocommerce_checkout_before_customer_details', array($this, 'checkout_service_selector'), 5);
        add_action('woocommerce_before_checkout_form', array($this, 'checkout_service_selector'), 5);
        
        // محاسبه هزینه ارسال بر اساس روش
        add_action('woocommerce_cart_calculate_fees', array($this, 'calculate_delivery_fee'), 20);
        
        // ذخیره روش تحویل در سفارش
        add_action('woocommerce_checkout_create_order', array($this, 'save_service_to_order'), 10, 2);
        
        // نمایش روش تحویل در جزئیات سفارش
        add_action('woocommerce_order_details_after_order_table', array($this, 'show_service_in_order_details'), 10, 1);
        
        // نمایش در ادمین
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'show_service_in_admin'));
        
        // استایل و اسکریپت
        add_action('wp_enqueue_scripts', array($this, 'frontend_assets'));
    }

    public function frontend_assets() {
        if (!is_checkout() && !has_shortcode(get_post()->post_content ?? '', 'sofre_services')) {
            return;
        }
        wp_add_inline_style('sf-frontend', $this->get_services_styles());
        wp_add_inline_script('sf-frontend', $this->get_services_script(), 'before');
    }

    private function get_services_styles() {
        return '
        .sf-services-section {
            margin-bottom: 24px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 16px;
            border: 1px solid #eee;
        }
        .sf-services-title {
            font-size: 16px;
            font-weight: 700;
            color: #1d1a18;
            margin: 0 0 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sf-services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
        }
        .sf-service-option {
            position: relative;
            cursor: pointer;
        }
        .sf-service-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .sf-service-card {
            padding: 16px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 14px;
            text-align: center;
            transition: all 0.2s;
            background: #fff;
        }
        .sf-service-option input:checked + .sf-service-card {
            border-color: #036666;
            background: #f0f7f7;
            box-shadow: 0 2px 8px rgba(3,102,102,0.15);
        }
        .sf-service-option input:checked + .sf-service-card .sf-service-icon {
            transform: scale(1.1);
        }
        .sf-service-icon {
            font-size: 32px;
            display: block;
            margin-bottom: 8px;
            transition: transform 0.2s;
        }
        .sf-service-name {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            display: block;
        }
        .sf-service-price {
            font-size: 12px;
            color: #888;
            margin-top: 4px;
            display: block;
        }
        .sf-service-desc {
            font-size: 11px;
            color: #aaa;
            margin-top: 2px;
        }
        ';
    }

    private function get_services_script() {
        return '
        jQuery(document).ready(function($) {
            $(document).on("change", ".sf-service-option input", function() {
                var service = $(this).val();
                $.post(sf_ajax.ajax_url, {
                    action: "sf_set_service",
                    service: service,
                    nonce: sf_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        $("body").trigger("update_checkout");
                    }
                });
            });
        });
        ';
    }

    public function get_available_services() {
        $services = array();
        $enable_delivery = get_option('sf_enable_delivery', 'yes');
        
        // Delivery - ارسال با موتور
        if ($enable_delivery === 'yes') {
            $delivery_fee = intval(get_option('sf_delivery_fee', 0));
            $free_min = intval(get_option('sf_free_delivery_min', 0));
            $cart_total = WC()->cart ? WC()->cart->get_subtotal() : 0;
            
            $price_text = '';
            if ($delivery_fee > 0) {
                if ($free_min > 0 && $cart_total >= $free_min) {
                    $price_text = 'رایگان';
                } else {
                    $price_text = number_format($delivery_fee) . ' تومان';
                }
            } else {
                $price_text = 'رایگان';
            }
            
            $services['delivery'] = array(
                'id' => 'delivery',
                'name' => 'ارسال درب منزل',
                'icon' => '🛵',
                'price' => $price_text,
                'desc' => 'تحویل توسط پیک',
                'fee' => $delivery_fee,
                'free_min' => $free_min,
            );
        }
        
        // Pickup - حضوری
        $services['pickup'] = array(
            'id' => 'pickup',
            'name' => 'تحویل حضوری',
            'icon' => '🏪',
            'price' => 'رایگان',
            'desc' => 'از رستوران تحویل بگیرید',
            'fee' => 0,
            'free_min' => 0,
        );
        
        // Serving - سرویس (صرف غذا در محل)
        $services['serving'] = array(
            'id' => 'serving',
            'name' => 'صرف در رستوران',
            'icon' => '🍽️',
            'price' => 'رایگان',
            'desc' => 'سرویس در محل رستوران',
            'fee' => 0,
            'free_min' => 0,
        );
        
        return $services;
    }

    public function render_services_selector() {
        $services = $this->get_available_services();
        $selected = WC()->session ? WC()->session->get('sf_selected_service', 'pickup') : 'pickup';
        
        ob_start();
        ?>
        <div class="sf-services-section">
            <div class="sf-services-title">🚚 روش تحویل</div>
            <div class="sf-services-grid">
                <?php foreach ($services as $key => $service): ?>
                <label class="sf-service-option">
                    <input type="radio" name="sf_service" value="<?php echo esc_attr($key); ?>" <?php checked($selected, $key); ?>>
                    <div class="sf-service-card">
                        <span class="sf-service-icon"><?php echo $service['icon']; ?></span>
                        <span class="sf-service-name"><?php echo esc_html($service['name']); ?></span>
                        <span class="sf-service-price"><?php echo esc_html($service['price']); ?></span>
                        <span class="sf-service-desc"><?php echo esc_html($service['desc']); ?></span>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function checkout_service_selector() {
        if (is_user_logged_in() || WC()->cart->is_empty()) {
            return;
        }
        echo $this->render_services_selector();
    }

    public function ajax_set_service() {
        check_ajax_referer('sf_nonce', 'nonce');
        
        $service = sanitize_text_field($_POST['service'] ?? 'pickup');
        $valid_services = array('delivery', 'pickup', 'serving');
        
        if (!in_array($service, $valid_services)) {
            $service = 'pickup';
        }
        
        if (WC()->session) {
            WC()->session->set('sf_selected_service', $service);
        }
        
        wp_send_json_success(array(
            'service' => $service,
            'message' => 'روش تحویل انتخاب شد',
        ));
    }

    public function calculate_delivery_fee($cart) {
        if (is_admin() && !is_ajax()) {
            return;
        }
        
        $service = WC()->session ? WC()->session->get('sf_selected_service', 'pickup') : 'pickup';
        
        if ($service === 'delivery') {
            $delivery_fee = intval(get_option('sf_delivery_fee', 0));
            $free_min = intval(get_option('sf_free_delivery_min', 0));
            $cart_total = $cart->get_subtotal();
            
            if ($delivery_fee > 0 && ($free_min <= 0 || $cart_total < $free_min)) {
                $cart->add_fee('هزینه ارسال با پیک', $delivery_fee);
            }
        } elseif ($service === 'serving') {
            // سرویس در رستوران - هزینه بسته‌بندی نداره
        }
    }

    public function save_service_to_order($order, $data) {
        $service = WC()->session ? WC()->session->get('sf_selected_service', 'pickup') : 'pickup';
        $services = $this->get_available_services();
        
        $service_name = isset($services[$service]) ? $services[$service]['name'] : 'تحویل حضوری';
        $order->update_meta_data('_sf_service', $service);
        $order->update_meta_data('_sf_service_name', $service_name);
    }

    public function show_service_in_order_details($order) {
        $service = $order->get_meta('_sf_service_name');
        if ($service) {
            echo '<div class="sf-order-service" style="margin-top:20px;padding:16px;background:#f8f8f8;border-radius:12px;">';
            echo '<strong>روش تحویل:</strong> ' . esc_html($service);
            echo '</div>';
        }
    }

    public function show_service_in_admin($order) {
        $service = $order->get_meta('_sf_service_name');
        if ($service) {
            echo '<p><strong>روش تحویل:</strong> ' . esc_html($service) . '</p>';
        }
    }
}

Sofre_Services::instance();