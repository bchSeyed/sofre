<?php
/**
 * کلاس مدیریت سفارشات
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sofre_Orders {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // اضافه کردن وضعیت‌های سفارش به لیست ووکامرس
        add_filter('wc_order_statuses', array($this, 'add_order_statuses'));
        
        // نمایش وضعیت در صفحه جزئیات سفارش
        add_action('woocommerce_order_details_after_order_table', array($this, 'show_order_tracking'), 10, 1);
        
        // اضافه کردن متاباکس در صفحه ادیت سفارش
        add_action('add_meta_boxes', array($this, 'add_order_meta_box'));
    }

    /**
     * اضافه کردن وضعیت‌های سفارشی به لیست ووکامرس
     */
    public function add_order_statuses($order_statuses) {
        $new_statuses = array();
        
        foreach ($order_statuses as $key => $status) {
            $new_statuses[$key] = $status;
            if ($key === 'wc-processing') {
                $new_statuses['wc-sf-pending'] = 'در انتظار تایید رستوران';
                $new_statuses['wc-sf-preparing'] = 'در حال آماده‌سازی';
                $new_statuses['wc-sf-ready'] = 'آماده تحویل';
                $new_statuses['wc-sf-delivering'] = 'در حال ارسال';
                $new_statuses['wc-sf-delivered'] = 'تحویل شده';
            }
        }
        
        return $new_statuses;
    }

    /**
     * نمایش وضعیت سفارش در صفحه پیگیری
     */
    public function show_order_tracking($order) {
        $status = $order->get_status();
        
        $steps = array(
            'sf-pending' => array('label' => 'در انتظار تایید', 'icon' => '📋'),
            'sf-preparing' => array('label' => 'در حال آماده‌سازی', 'icon' => '👨‍🍳'),
            'sf-ready' => array('label' => 'آماده تحویل', 'icon' => '✅'),
            'sf-delivering' => array('label' => 'در حال ارسال', 'icon' => '🛵'),
            'sf-delivered' => array('label' => 'تحویل شده', 'icon' => '🎉'),
        );
        
        if (!array_key_exists($status, $steps) && !array_key_exists(str_replace('wc-', '', $status), $steps)) {
            return;
        }
        
        $current_status = str_replace('wc-', '', $status);
        
        echo '<div class="sf-order-tracking">';
        echo '<h3>وضعیت سفارش</h3>';
        echo '<div class="sf-tracking-steps">';
        
        $found = false;
        foreach ($steps as $step_key => $step) {
            if ($step_key === $current_status) {
                $found = true;
            }
            
            $class = $found ? 'active' : 'inactive';
            echo '<div class="sf-tracking-step ' . $class . '">';
            echo '<span class="sf-step-icon">' . $step['icon'] . '</span>';
            echo '<span class="sf-step-label">' . $step['label'] . '</span>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
        ?>
        <style>
        .sf-order-tracking {
            margin: 30px 0;
            padding: 20px;
            background: #f8f8f8;
            border-radius: 12px;
        }
        .sf-order-tracking h3 {
            margin-top: 0;
            margin-bottom: 20px;
            text-align: center;
        }
        .sf-tracking-steps {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }
        .sf-tracking-step {
            text-align: center;
            flex: 1;
            position: relative;
            padding: 10px 0;
        }
        .sf-tracking-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 50%;
            right: 50%;
            left: -50%;
            height: 3px;
            background: #ddd;
            z-index: 0;
        }
        .sf-tracking-step.active:not(:last-child)::after {
            background: #4CAF50;
        }
        .sf-tracking-step .sf-step-icon {
            display: block;
            font-size: 28px;
            margin-bottom: 5px;
        }
        .sf-tracking-step.active .sf-step-icon {
            transform: scale(1.2);
        }
        .sf-tracking-step.inactive .sf-step-icon {
            opacity: 0.4;
        }
        .sf-tracking-step .sf-step-label {
            font-size: 12px;
            display: block;
        }
        .sf-tracking-step.inactive .sf-step-label {
            color: #999;
        }
        .sf-tracking-step.active .sf-step-label {
            color: #4CAF50;
            font-weight: bold;
        }
        </style>
        <?php
    }

    /**
     * اضافه کردن متاباکس وضعیت سفارش در صفحه ادیت ووکامرس
     */
    public function add_order_meta_box() {
        add_meta_box(
            'sf_order_status_box',
            'وضعیت سفره',
            array($this, 'render_order_meta_box'),
            'shop_order',
            'side',
            'default'
        );
    }

    public function render_order_meta_box($post) {
        $order = wc_get_order($post->ID);
        if (!$order) return;
        
        $current_status = $order->get_status();
        ?>
        <div style="text-align:center;">
            <p style="font-size:18px;font-weight:bold;margin-bottom:15px;">
                وضعیت فعلی: <?php echo esc_html(wc_get_order_status_name($current_status)); ?>
            </p>
            <select class="sf-quick-status" data-order-id="<?php echo $order->get_id(); ?>" style="width:100%;margin-bottom:10px;padding:8px;border-radius:6px;border:1px solid #ddd;">
                <option value="">تغییر وضعیت...</option>
                <option value="sf-pending" <?php selected($current_status, 'sf-pending'); ?>>در انتظار تایید</option>
                <option value="sf-preparing" <?php selected($current_status, 'sf-preparing'); ?>>در حال آماده‌سازی</option>
                <option value="sf-ready" <?php selected($current_status, 'sf-ready'); ?>>آماده تحویل</option>
                <option value="sf-delivering" <?php selected($current_status, 'sf-delivering'); ?>>در حال ارسال</option>
                <option value="sf-delivered" <?php selected($current_status, 'sf-delivered'); ?>>تحویل شده</option>
            </select>
            <span class="sf-quick-status-msg" style="color:#4CAF50;display:none;"></span>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('.sf-quick-status').on('change', function() {
                var orderId = $(this).data('order-id');
                var status = $(this).val();
                if (!status) return;
                
                var msgEl = $(this).siblings('.sf-quick-status-msg');
                msgEl.hide();
                
                $.post(ajaxurl, {
                    action: 'sf_update_order_status',
                    order_id: orderId,
                    status: status,
                    nonce: '<?php echo wp_create_nonce("sf_nonce"); ?>'
                }, function(response) {
                    if (response.success) {
                        msgEl.text('✅ وضعیت بروزرسانی شد').show().delay(2000).fadeOut();
                        location.reload();
                    }
                });
            });
        });
        </script>
        <?php
    }
}

Sofre_Orders::instance();