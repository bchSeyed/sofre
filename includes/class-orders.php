<?php
/**
 * کلاس مدیریت سفارشات
 */

if (!defined('ABSPATH')) {
    exit;
}

class Boshqab_Orders {

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
                $new_statuses['wc-bq-pending'] = 'در انتظار تایید رستوران';
                $new_statuses['wc-bq-preparing'] = 'در حال آماده‌سازی';
                $new_statuses['wc-bq-ready'] = 'آماده تحویل';
                $new_statuses['wc-bq-delivering'] = 'در حال ارسال';
                $new_statuses['wc-bq-delivered'] = 'تحویل شده';
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
            'bq-pending' => array('label' => 'در انتظار تایید', 'icon' => '📋'),
            'bq-preparing' => array('label' => 'در حال آماده‌سازی', 'icon' => '👨‍🍳'),
            'bq-ready' => array('label' => 'آماده تحویل', 'icon' => '✅'),
            'bq-delivering' => array('label' => 'در حال ارسال', 'icon' => '🛵'),
            'bq-delivered' => array('label' => 'تحویل شده', 'icon' => '🎉'),
        );
        
        if (!array_key_exists($status, $steps) && !array_key_exists(str_replace('wc-', '', $status), $steps)) {
            return;
        }
        
        $current_status = str_replace('wc-', '', $status);
        
        echo '<div class="bq-order-tracking">';
        echo '<h3>وضعیت سفارش</h3>';
        echo '<div class="bq-tracking-steps">';
        
        $found = false;
        foreach ($steps as $step_key => $step) {
            if ($step_key === $current_status) {
                $found = true;
            }
            
            $class = $found ? 'active' : 'inactive';
            echo '<div class="bq-tracking-step ' . $class . '">';
            echo '<span class="bq-step-icon">' . $step['icon'] . '</span>';
            echo '<span class="bq-step-label">' . $step['label'] . '</span>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
        ?>
        <style>
        .bq-order-tracking {
            margin: 30px 0;
            padding: 20px;
            background: #f8f8f8;
            border-radius: 12px;
        }
        .bq-order-tracking h3 {
            margin-top: 0;
            margin-bottom: 20px;
            text-align: center;
        }
        .bq-tracking-steps {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }
        .bq-tracking-step {
            text-align: center;
            flex: 1;
            position: relative;
            padding: 10px 0;
        }
        .bq-tracking-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 50%;
            right: 50%;
            left: -50%;
            height: 3px;
            background: #ddd;
            z-index: 0;
        }
        .bq-tracking-step.active:not(:last-child)::after {
            background: #4CAF50;
        }
        .bq-tracking-step .bq-step-icon {
            display: block;
            font-size: 28px;
            margin-bottom: 5px;
        }
        .bq-tracking-step.active .bq-step-icon {
            transform: scale(1.2);
        }
        .bq-tracking-step.inactive .bq-step-icon {
            opacity: 0.4;
        }
        .bq-tracking-step .bq-step-label {
            font-size: 12px;
            display: block;
        }
        .bq-tracking-step.inactive .bq-step-label {
            color: #999;
        }
        .bq-tracking-step.active .bq-step-label {
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
            'bq_order_status_box',
            'وضعیت بشقاب',
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
            <select class="bq-quick-status" data-order-id="<?php echo $order->get_id(); ?>" style="width:100%;margin-bottom:10px;padding:8px;border-radius:6px;border:1px solid #ddd;">
                <option value="">تغییر وضعیت...</option>
                <option value="bq-pending" <?php selected($current_status, 'bq-pending'); ?>>در انتظار تایید</option>
                <option value="bq-preparing" <?php selected($current_status, 'bq-preparing'); ?>>در حال آماده‌سازی</option>
                <option value="bq-ready" <?php selected($current_status, 'bq-ready'); ?>>آماده تحویل</option>
                <option value="bq-delivering" <?php selected($current_status, 'bq-delivering'); ?>>در حال ارسال</option>
                <option value="bq-delivered" <?php selected($current_status, 'bq-delivered'); ?>>تحویل شده</option>
            </select>
            <span class="bq-quick-status-msg" style="color:#4CAF50;display:none;"></span>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('.bq-quick-status').on('change', function() {
                var orderId = $(this).data('order-id');
                var status = $(this).val();
                if (!status) return;
                
                var msgEl = $(this).siblings('.bq-quick-status-msg');
                msgEl.hide();
                
                $.post(ajaxurl, {
                    action: 'bq_update_order_status',
                    order_id: orderId,
                    status: status,
                    nonce: '<?php echo wp_create_nonce("bq_nonce"); ?>'
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

Boshqab_Orders::instance();