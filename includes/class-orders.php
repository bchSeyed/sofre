<?php
/**
 * کلاس مدیریت سفارشات
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reyhoon_Simple_Orders {

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
                $new_statuses['wc-ryns-pending'] = 'در انتظار تایید رستوران';
                $new_statuses['wc-ryns-preparing'] = 'در حال آماده‌سازی';
                $new_statuses['wc-ryns-ready'] = 'آماده تحویل';
                $new_statuses['wc-ryns-delivering'] = 'در حال ارسال';
                $new_statuses['wc-ryns-delivered'] = 'تحویل شده';
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
            'ryns-pending' => array('label' => 'در انتظار تایید', 'icon' => '📋'),
            'ryns-preparing' => array('label' => 'در حال آماده‌سازی', 'icon' => '👨‍🍳'),
            'ryns-ready' => array('label' => 'آماده تحویل', 'icon' => '✅'),
            'ryns-delivering' => array('label' => 'در حال ارسال', 'icon' => '🛵'),
            'ryns-delivered' => array('label' => 'تحویل شده', 'icon' => '🎉'),
        );
        
        if (!array_key_exists($status, $steps) && !array_key_exists(str_replace('wc-', '', $status), $steps)) {
            return;
        }
        
        $current_status = str_replace('wc-', '', $status);
        
        echo '<div class="ryns-order-tracking">';
        echo '<h3>وضعیت سفارش</h3>';
        echo '<div class="ryns-tracking-steps">';
        
        $found = false;
        foreach ($steps as $step_key => $step) {
            if ($step_key === $current_status) {
                $found = true;
            }
            
            $class = $found ? 'active' : 'inactive';
            echo '<div class="ryns-tracking-step ' . $class . '">';
            echo '<span class="ryns-step-icon">' . $step['icon'] . '</span>';
            echo '<span class="ryns-step-label">' . $step['label'] . '</span>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
        ?>
        <style>
        .ryns-order-tracking {
            margin: 30px 0;
            padding: 20px;
            background: #f8f8f8;
            border-radius: 12px;
        }
        .ryns-order-tracking h3 {
            margin-top: 0;
            margin-bottom: 20px;
            text-align: center;
        }
        .ryns-tracking-steps {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }
        .ryns-tracking-step {
            text-align: center;
            flex: 1;
            position: relative;
            padding: 10px 0;
        }
        .ryns-tracking-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 50%;
            right: 50%;
            left: -50%;
            height: 3px;
            background: #ddd;
            z-index: 0;
        }
        .ryns-tracking-step.active:not(:last-child)::after {
            background: #4CAF50;
        }
        .ryns-tracking-step .ryns-step-icon {
            display: block;
            font-size: 28px;
            margin-bottom: 5px;
        }
        .ryns-tracking-step.active .ryns-step-icon {
            transform: scale(1.2);
        }
        .ryns-tracking-step.inactive .ryns-step-icon {
            opacity: 0.4;
        }
        .ryns-tracking-step .ryns-step-label {
            font-size: 12px;
            display: block;
        }
        .ryns-tracking-step.inactive .ryns-step-label {
            color: #999;
        }
        .ryns-tracking-step.active .ryns-step-label {
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
            'ryns_order_status_box',
            'وضعیت ریحون ساده',
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
            <select class="ryns-quick-status" data-order-id="<?php echo $order->get_id(); ?>" style="width:100%;margin-bottom:10px;padding:8px;border-radius:6px;border:1px solid #ddd;">
                <option value="">تغییر وضعیت...</option>
                <option value="ryns-pending" <?php selected($current_status, 'ryns-pending'); ?>>در انتظار تایید</option>
                <option value="ryns-preparing" <?php selected($current_status, 'ryns-preparing'); ?>>در حال آماده‌سازی</option>
                <option value="ryns-ready" <?php selected($current_status, 'ryns-ready'); ?>>آماده تحویل</option>
                <option value="ryns-delivering" <?php selected($current_status, 'ryns-delivering'); ?>>در حال ارسال</option>
                <option value="ryns-delivered" <?php selected($current_status, 'ryns-delivered'); ?>>تحویل شده</option>
            </select>
            <span class="ryns-quick-status-msg" style="color:#4CAF50;display:none;"></span>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('.ryns-quick-status').on('change', function() {
                var orderId = $(this).data('order-id');
                var status = $(this).val();
                if (!status) return;
                
                var msgEl = $(this).siblings('.ryns-quick-status-msg');
                msgEl.hide();
                
                $.post(ajaxurl, {
                    action: 'ryns_update_order_status',
                    order_id: orderId,
                    status: status,
                    nonce: '<?php echo wp_create_nonce("ryns_nonce"); ?>'
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

Reyhoon_Simple_Orders::instance();