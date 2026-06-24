<?php
/**
 * Plugin Name: سفره - مدیریت سفارشات رستوران
 * Plugin URI: https://github.com/bchSeyed/sofre
 * Description: افزونه مستقل مدیریت منو و سفارشات رستوران — سفره
 * Version: 1.1.1
 * Author: Sofre
 * Text Domain: sofre
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 5.0
 * WC tested up to: 9.6
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SF_VERSION', '1.1.1');
define('SF_PATH', plugin_dir_path(__FILE__));
define('SF_URL', plugin_dir_url(__FILE__));
define('SF_BASENAME', plugin_basename(__FILE__));

class Sofre_Plugin {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('init', array($this, 'migrate_legacy_entries'), 1);
        add_action('init', array($this, 'init'));
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * حذف ورودی‌های افزونه‌های قدیمی (boshqab/reyhoon) از لیست فعال‌ها
     */
    public function migrate_legacy_entries() {
        $active = get_option('active_plugins', array());
        if (!is_array($active)) {
            return;
        }

        $legacy = array('boshqab/boshqab.php', 'reyhoon/reyhoon.php');
        $cleaned = array_values(array_diff($active, $legacy));

        if ($cleaned !== $active) {
            update_option('active_plugins', $cleaned);
        }
    }

    public function activate() {
        $this->create_default_pages();
        
        $defaults = array(
            'restaurant_name' => get_bloginfo('name'),
            'restaurant_address' => '',
            'restaurant_phone' => '',
            'restaurant_logo' => '',
            'primary_color' => '#036666',
            'secondary_color' => '#ad8b4c',
            'bg_color' => '#1d1a18',
            'card_bg_color' => '#26211f',
            'text_color' => '#ffffff',
            'enable_delivery' => 'yes',
            'delivery_fee' => 0,
            'free_delivery_min' => 0,
            'min_order_amount' => 0,
            'is_open' => 'yes',
            'business_hours' => '',
            'notification_sound' => '',
            'enable_ordering' => 'yes',
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option('sf_' . $key) === false) {
                add_option('sf_' . $key, $value);
            }
        }

        // ذخیره پیشفرض ساعت کاری هفتگی
        if (!get_option('sf_business_hours')) {
            $week_hours = array();
            $days = array('saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday');
            foreach ($days as $day) {
                $week_hours[$day] = array(
                    'is_open' => ($day !== 'friday') ? 'yes' : 'no',
                    'open_time' => '09:00',
                    'close_time' => '23:00',
                );
            }
            update_option('sf_business_hours', $week_hours);
        }
    }

    public function init() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $this->includes();
        
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_assets'));
        add_action('init', array($this, 'register_order_statuses'));
        
        // Ajax handlers
        add_action('wp_ajax_sf_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_sf_update_order_status', array($this, 'ajax_update_order_status'));
        add_action('wp_ajax_sf_toggle_restaurant', array($this, 'ajax_toggle_restaurant'));
        add_action('wp_ajax_sf_check_new_orders', array($this, 'ajax_check_new_orders'));
        add_action('wp_ajax_sf_get_order_detail', array($this, 'ajax_get_order_detail'));
        add_action('wp_ajax_nopriv_sf_get_categories', array($this, 'ajax_get_categories'));
        add_action('wp_ajax_sf_get_categories', array($this, 'ajax_get_categories'));
        add_action('wp_ajax_nopriv_sf_check_restaurant_status', array($this, 'ajax_check_restaurant_status'));
        add_action('wp_ajax_sf_check_restaurant_status', array($this, 'ajax_check_restaurant_status'));
    }

    private function includes() {
        require_once SF_PATH . 'includes/class-template.php';
        require_once SF_PATH . 'includes/class-menu.php';
        require_once SF_PATH . 'includes/class-orders.php';
        require_once SF_PATH . 'includes/class-frontend.php';
        require_once SF_PATH . 'includes/class-otp-login.php';
        require_once SF_PATH . 'includes/class-drawer.php';
        require_once SF_PATH . 'includes/class-services.php';
        require_once SF_PATH . 'includes/class-shipping.php';
    }

    public static function is_menu_context() {
        if (!is_singular('page')) {
            return false;
        }

        $menu_page_id = (int) get_option('sf_menu_page_id');
        if ($menu_page_id && is_page($menu_page_id)) {
            return true;
        }

        global $post;
        return ($post && has_shortcode($post->post_content, 'sofre_menu'));
    }

    public static function is_plugin_frontend_context() {
        if (self::is_menu_context()) {
            return true;
        }

        if (function_exists('is_checkout') && (is_checkout() || is_account_page())) {
            return true;
        }

        global $post;
        if ($post && (has_shortcode($post->post_content, 'sofre_otp') || has_shortcode($post->post_content, 'sofre_services'))) {
            return true;
        }

        return false;
    }

    private function create_default_pages() {
        if (!get_option('sf_menu_page_id')) {
            $page_id = wp_insert_post(array(
                'post_title' => 'منوی رستوران',
                'post_content' => '[sofre_menu]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'comment_status' => 'closed',
            ));
            if ($page_id && !is_wp_error($page_id)) {
                update_option('sf_menu_page_id', $page_id);
            }
        }
    }

    public function admin_menu() {
        $is_open = get_option('sf_is_open', 'yes');
        $open_icon = ($is_open === 'yes') ? '<span style="color:#4CAF50;">●</span>' : '<span style="color:#f44336;">○</span>';
        
        add_menu_page(
            'سفره',
            'سفره ' . $open_icon,
            'manage_options',
            'sofre',
            array($this, 'dashboard_page'),
            'dashicons-food',
            55
        );

        add_submenu_page(
            'sofre',
            'سفارشات',
            'سفارشات',
            'manage_woocommerce',
            'sofre-orders',
            array($this, 'orders_page')
        );

        add_submenu_page(
            'sofre',
            'تنظیمات',
            'تنظیمات',
            'manage_options',
            'sofre-settings',
            array($this, 'settings_page')
        );
    }

    public function admin_assets($hook) {
        if (strpos($hook, 'sofre') === false && $hook !== 'post.php') {
            return;
        }
        
        wp_enqueue_style('sf-admin', SF_URL . 'assets/admin.css', array(), SF_VERSION);
        wp_enqueue_script('sf-admin', SF_URL . 'assets/admin.js', array('jquery'), SF_VERSION, true);
        wp_localize_script('sf-admin', 'sf_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sf_nonce'),
            'is_restaurant_open' => get_option('sf_is_open', 'yes'),
            'restaurant_name' => get_option('sf_restaurant_name', get_bloginfo('name')),
        ));

        // انکود کردن صدای پیشفرض اعلان (Base64 Beep)
        $sound = get_option('sf_notification_sound', '');
        if (empty($sound)) {
            // یک صدای بوق ساده به صورت base64
            $sound = base64_encode('beep');
        }
        wp_localize_script('sf-admin', 'sf_sound', array(
            'data' => $sound,
        ));
    }

    public function frontend_assets() {
        if (!self::is_plugin_frontend_context()) {
            return;
        }

        $is_menu = self::is_menu_context();

        wp_enqueue_style('sf-frontend', SF_URL . 'assets/frontend.css', array(), SF_VERSION);
        wp_enqueue_script('sf-frontend', SF_URL . 'assets/frontend.js', array('jquery'), SF_VERSION, true);

        if ($is_menu) {
            wp_enqueue_script('wc-cart-fragments');
        }

        wp_localize_script('sf-frontend', 'sf_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sf_nonce'),
            'cart_url' => function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/'),
            'checkout_url' => function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/checkout/'),
            'restaurant_open' => $is_menu ? $this->is_restaurant_open() : true,
            'is_menu' => $is_menu,
        ));
    }

    // ============ STATUSES ============

    public function register_order_statuses() {
        register_post_status('wc-sf-pending', array(
            'label' => 'در انتظار تایید رستوران',
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'label_count' => _n_noop('در انتظار تایید (%s)', 'در انتظار تایید (%s)'),
        ));
        
        register_post_status('wc-sf-preparing', array(
            'label' => 'در حال آماده‌سازی',
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'label_count' => _n_noop('در حال آماده‌سازی (%s)', 'در حال آماده‌سازی (%s)'),
        ));
        
        register_post_status('wc-sf-ready', array(
            'label' => 'آماده تحویل',
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'label_count' => _n_noop('آماده تحویل (%s)', 'آماده تحویل (%s)'),
        ));
        
        register_post_status('wc-sf-delivering', array(
            'label' => 'در حال ارسال',
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'label_count' => _n_noop('در حال ارسال (%s)', 'در حال ارسال (%s)'),
        ));
        
        register_post_status('wc-sf-delivered', array(
            'label' => 'تحویل شده',
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'label_count' => _n_noop('تحویل شده (%s)', 'تحویل شده (%s)'),
        ));
    }

    // ============ RESTAURANT STATUS ============

    public function is_restaurant_open() {
        $is_open_setting = get_option('sf_is_open', 'yes');
        if ($is_open_setting === 'no') {
            return false;
        }

        $enable_ordering = get_option('sf_enable_ordering', 'yes');
        if ($enable_ordering === 'no') {
            return false;
        }

        // بررسی ساعت کاری هفتگی
        $business_hours = get_option('sf_business_hours', array());
        if (!empty($business_hours)) {
            $today = $this->get_persian_day_name();
            if (isset($business_hours[$today])) {
                $day_hours = $business_hours[$today];
                if ($day_hours['is_open'] === 'no') {
                    return false;
                }
                
                $current_time = current_time('H:i');
                if ($current_time < $day_hours['open_time'] || $current_time > $day_hours['close_time']) {
                    return false;
                }
            }
        }

        return true;
    }

    private function get_persian_day_name() {
        $days_map = array(
            'Saturday' => 'saturday',
            'Sunday' => 'sunday',
            'Monday' => 'monday',
            'Tuesday' => 'tuesday',
            'Wednesday' => 'wednesday',
            'Thursday' => 'thursday',
            'Friday' => 'friday',
        );
        
        $english_day = date('l', current_time('timestamp'));
        $day_key = isset($days_map[$english_day]) ? $days_map[$english_day] : strtolower($english_day);
        
        return $day_key;
    }

    public function get_restaurant_status_text() {
        $business_hours = get_option('sf_business_hours', array());
        $today = $this->get_persian_day_name();
        
        $day_names = array(
            'saturday' => 'شنبه',
            'sunday' => 'یکشنبه',
            'monday' => 'دوشنبه',
            'tuesday' => 'سه‌شنبه',
            'wednesday' => 'چهارشنبه',
            'thursday' => 'پنجشنبه',
            'friday' => 'جمعه',
        );
        
        if (!empty($business_hours) && isset($business_hours[$today])) {
            $day = $business_hours[$today];
            if ($day['is_open'] === 'yes') {
                return 'باز از ' . $day['open_time'] . ' تا ' . $day['close_time'];
            } else {
                return 'امروز تعطیل هستیم';
            }
        }
        
        return '';
    }

    // ============ PAGES ============

    public function dashboard_page() {
        $orders_count = $this->count_orders_by_status(array('sf-pending', 'processing'));
        $menu_items = wp_count_posts('product')->publish;
        $today_orders = $this->get_today_orders_count();
        $is_open = $this->is_restaurant_open();
        ?>
        <div class="sf-dashboard">
            <div class="sf-dashboard-header">
                <h1>داشبورد سفره</h1>
                <div class="sf-restaurant-toggle">
                    <span class="sf-toggle-label">وضعیت رستوران:</span>
                    <button class="sf-toggle-btn <?php echo $is_open ? 'open' : 'closed'; ?>" 
                            id="sf-toggle-restaurant"
                            data-current="<?php echo $is_open ? 'open' : 'closed'; ?>">
                        <span class="sf-toggle-dot"></span>
                        <span class="sf-toggle-text"><?php echo $is_open ? 'باز' : 'بسته'; ?></span>
                    </button>
                </div>
            </div>

            <div class="sf-status-banner <?php echo $is_open ? 'sf-status-open' : 'sf-status-closed'; ?>" id="sf-status-banner">
                <span class="sf-status-icon"><?php echo $is_open ? '🟢' : '🔴'; ?></span>
                <span class="sf-status-msg">
                    <?php 
                    if ($is_open) {
                        echo 'رستوران باز است و سفارش پذیرفته می‌شود';
                    } else {
                        echo 'رستوران بسته است - سفارشات پذیرفته نمی‌شوند';
                    }
                    ?>
                </span>
                <span class="sf-hours-text"><?php echo $this->get_restaurant_status_text(); ?></span>
            </div>

            <div class="sf-stats-grid">
                <div class="sf-stat-card">
                    <span class="sf-stat-icon">📋</span>
                    <span class="sf-stat-number"><?php echo $orders_count; ?></span>
                    <span class="sf-stat-label">سفارشات فعال</span>
                </div>
                <div class="sf-stat-card">
                    <span class="sf-stat-icon">🍽️</span>
                    <span class="sf-stat-number"><?php echo $menu_items; ?></span>
                    <span class="sf-stat-label">آیتم‌های منو</span>
                </div>
                <div class="sf-stat-card">
                    <span class="sf-stat-icon">📅</span>
                    <span class="sf-stat-number"><?php echo $today_orders; ?></span>
                    <span class="sf-stat-label">سفارشات امروز</span>
                </div>
                <div class="sf-stat-card">
                    <span class="sf-stat-icon">👁️</span>
                    <span class="sf-stat-number"><a href="<?php echo get_permalink(get_option('sf_menu_page_id')); ?>" target="_blank">مشاهده</a></span>
                    <span class="sf-stat-label">نمایش منو</span>
                </div>
            </div>
            
            <div class="sf-section">
                <h2>سفارشات جدید <span class="sf-live-badge" id="sf-live-badge" style="display:none;">🔔 جدید</span></h2>
                <div id="sf-orders-container">
                    <?php $this->recent_orders_table(10); ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function settings_page() {
        if (isset($_POST['sf_save_settings']) && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'sf_settings')) {
            $this->save_settings();
        }
        ?>
        <div class="sf-settings-wrap">
            <h1>تنظیمات سفره</h1>
            <form method="post" action="">
                <?php wp_nonce_field('sf_settings'); ?>
                <input type="hidden" name="sf_save_settings" value="1">
                
                <div class="sf-settings-section">
                    <h2>اطلاعات رستوران</h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="sf_restaurant_name">نام رستوران</label></th>
                            <td><input type="text" id="sf_restaurant_name" name="sf_restaurant_name" value="<?php echo esc_attr(get_option('sf_restaurant_name')); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="sf_restaurant_logo">لوگوی رستوران</label></th>
                            <td>
                                <div class="sf-logo-upload">
                                    <input type="hidden" id="sf_restaurant_logo" name="sf_restaurant_logo" value="<?php echo esc_attr(get_option('sf_restaurant_logo')); ?>">
                                    <div class="sf-logo-preview" id="sf-logo-preview">
                                        <?php $logo = get_option('sf_restaurant_logo'); ?>
                                        <?php if ($logo): ?>
                                            <img src="<?php echo esc_url($logo); ?>" style="max-width:150px;max-height:80px;">
                                        <?php else: ?>
                                            <span class="sf-logo-placeholder">لوگو انتخاب نشده</span>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="button sf-upload-logo-btn" id="sf-upload-logo">انتخاب لوگو</button>
                                    <button type="button" class="button sf-remove-logo-btn" id="sf-remove-logo" <?php echo $logo ? '' : 'style="display:none;"'; ?>>حذف لوگو</button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="sf_restaurant_address">آدرس</label></th>
                            <td><textarea id="sf_restaurant_address" name="sf_restaurant_address" rows="2" class="regular-text"><?php echo esc_textarea(get_option('sf_restaurant_address')); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="sf_restaurant_phone">تلفن تماس</label></th>
                            <td><input type="text" id="sf_restaurant_phone" name="sf_restaurant_phone" value="<?php echo esc_attr(get_option('sf_restaurant_phone')); ?>" class="regular-text"></td>
                        </tr>
                    </table>
                </div>

                <div class="sf-settings-section">
                    <h2>تنظیمات سفارش و ارسال</h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="sf_enable_ordering">فعال بودن سفارش‌گیری</label></th>
                            <td>
                                <select id="sf_enable_ordering" name="sf_enable_ordering">
                                    <option value="yes" <?php selected(get_option('sf_enable_ordering', 'yes'), 'yes'); ?>>فعال</option>
                                    <option value="no" <?php selected(get_option('sf_enable_ordering', 'yes'), 'no'); ?>>غیرفعال</option>
                                </select>
                                <p class="description">با غیرفعال کردن، کاربران نمی‌توانند سفارش ثبت کنند</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="sf_enable_delivery">ارسال درب منزل</label></th>
                            <td>
                                <select id="sf_enable_delivery" name="sf_enable_delivery">
                                    <option value="yes" <?php selected(get_option('sf_enable_delivery'), 'yes'); ?>>فعال</option>
                                    <option value="no" <?php selected(get_option('sf_enable_delivery'), 'no'); ?>>غیرفعال</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="sf_delivery_fee">هزینه ارسال (تومان)</label></th>
                            <td><input type="number" id="sf_delivery_fee" name="sf_delivery_fee" value="<?php echo esc_attr(get_option('sf_delivery_fee', 0)); ?>" class="small-text"></td>
                        </tr>
                        <tr>
                            <th><label for="sf_free_delivery_min">ارسال رایگان برای سفارشات بالای (تومان)</label></th>
                            <td><input type="number" id="sf_free_delivery_min" name="sf_free_delivery_min" value="<?php echo esc_attr(get_option('sf_free_delivery_min', 0)); ?>" class="small-text"></td>
                        </tr>
                        <tr>
                            <th><label for="sf_min_order_amount">حداقل مبلغ سفارش (تومان)</label></th>
                            <td><input type="number" id="sf_min_order_amount" name="sf_min_order_amount" value="<?php echo esc_attr(get_option('sf_min_order_amount', 0)); ?>" class="small-text"></td>
                        </tr>
                    </table>
                </div>

                <div class="sf-settings-section">
                    <h2>ساعت کاری هفتگی</h2>
                    <p class="description">ساعت‌های باز بودن رستوران در هر روز هفته</p>
                    <?php 
                    $business_hours = get_option('sf_business_hours', array());
                    $days_list = array(
                        'saturday' => 'شنبه',
                        'sunday' => 'یکشنبه',
                        'monday' => 'دوشنبه',
                        'tuesday' => 'سه‌شنبه',
                        'wednesday' => 'چهارشنبه',
                        'thursday' => 'پنجشنبه',
                        'friday' => 'جمعه',
                    );
                    ?>
                    <table class="form-table sf-hours-table">
                        <thead>
                            <tr>
                                <th>روز</th>
                                <th>وضعیت</th>
                                <th>ساعت بازگشایی</th>
                                <th>ساعت بسته شدن</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($days_list as $day_key => $day_name): 
                                $day_data = isset($business_hours[$day_key]) ? $business_hours[$day_key] : array('is_open' => 'yes', 'open_time' => '09:00', 'close_time' => '23:00');
                                if ($day_key === 'friday' && !isset($business_hours[$day_key])) {
                                    $day_data = array('is_open' => 'no', 'open_time' => '09:00', 'close_time' => '23:00');
                                }
                                $is_open_day = isset($day_data['is_open']) ? $day_data['is_open'] : 'yes';
                            ?>
                            <tr>
                                <td><strong><?php echo $day_name; ?></strong></td>
                                <td>
                                    <select name="sf_hours[<?php echo $day_key; ?>][is_open]">
                                        <option value="yes" <?php selected($is_open_day, 'yes'); ?>>باز</option>
                                        <option value="no" <?php selected($is_open_day, 'no'); ?>>تعطیل</option>
                                    </select>
                                </td>
                                <td><input type="time" name="sf_hours[<?php echo $day_key; ?>][open_time]" value="<?php echo esc_attr($day_data['open_time'] ?? '09:00'); ?>"></td>
                                <td><input type="time" name="sf_hours[<?php echo $day_key; ?>][close_time]" value="<?php echo esc_attr($day_data['close_time'] ?? '23:00'); ?>"></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="sf-settings-section">
                    <h2>رنگ‌بندی منو</h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="sf_primary_color">رنگ اصلی</label></th>
                            <td><input type="color" id="sf_primary_color" name="sf_primary_color" value="<?php echo esc_attr(get_option('sf_primary_color', '#036666')); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="sf_secondary_color">رنگ ثانویه</label></th>
                            <td><input type="color" id="sf_secondary_color" name="sf_secondary_color" value="<?php echo esc_attr(get_option('sf_secondary_color', '#ad8b4c')); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="sf_bg_color">رنگ پس‌زمینه</label></th>
                            <td><input type="color" id="sf_bg_color" name="sf_bg_color" value="<?php echo esc_attr(get_option('sf_bg_color', '#1d1a18')); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="sf_card_bg_color">رنگ کارت آیتم‌ها</label></th>
                            <td><input type="color" id="sf_card_bg_color" name="sf_card_bg_color" value="<?php echo esc_attr(get_option('sf_card_bg_color', '#26211f')); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="sf_text_color">رنگ متن</label></th>
                            <td><input type="color" id="sf_text_color" name="sf_text_color" value="<?php echo esc_attr(get_option('sf_text_color', '#ffffff')); ?>"></td>
                        </tr>
                    </table>
                </div>

                <p class="submit">
                    <input type="submit" class="button button-primary" value="ذخیره تنظیمات">
                </p>
            </form>
        </div>
        <?php
    }

    private function save_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $fields = array(
            'sf_restaurant_name', 'sf_restaurant_address', 'sf_restaurant_phone',
            'sf_restaurant_logo', 'sf_primary_color', 'sf_secondary_color',
            'sf_bg_color', 'sf_card_bg_color', 'sf_text_color',
            'sf_enable_delivery', 'sf_delivery_fee', 'sf_free_delivery_min',
            'sf_min_order_amount', 'sf_enable_ordering',
        );
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_option($field, sanitize_text_field($_POST[$field]));
            }
        }

        // ذخیره ساعت کاری
        if (isset($_POST['sf_hours']) && is_array($_POST['sf_hours'])) {
            $hours = array();
            foreach ($_POST['sf_hours'] as $day => $data) {
                $hours[sanitize_key($day)] = array(
                    'is_open' => isset($data['is_open']) ? sanitize_text_field($data['is_open']) : 'yes',
                    'open_time' => isset($data['open_time']) ? sanitize_text_field($data['open_time']) : '09:00',
                    'close_time' => isset($data['close_time']) ? sanitize_text_field($data['close_time']) : '23:00',
                );
            }
            update_option('sf_business_hours', $hours);
        }
        
        echo '<div class="notice notice-success"><p>✅ تنظیمات با موفقیت ذخیره شد.</p></div>';
    }

    public function orders_page() {
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        ?>
        <div class="sf-orders-wrap">
            <div class="sf-orders-header">
                <h1>مدیریت سفارشات</h1>
                <div class="sf-live-indicator" id="sf-live-indicator">
                    <span class="sf-live-dot"></span>
                    <span class="sf-live-text">بررسی خودکار سفارشات جدید...</span>
                </div>
            </div>

            <!-- فیلتر وضعیت -->
            <div class="sf-order-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="sofre-orders">
                    <select name="status" onchange="this.form.submit()">
                        <option value="">همه سفارشات فعال</option>
                        <option value="sf-pending" <?php selected($status_filter, 'sf-pending'); ?>>در انتظار تایید</option>
                        <option value="sf-preparing" <?php selected($status_filter, 'sf-preparing'); ?>>در حال آماده‌سازی</option>
                        <option value="sf-ready" <?php selected($status_filter, 'sf-ready'); ?>>آماده تحویل</option>
                        <option value="sf-delivering" <?php selected($status_filter, 'sf-delivering'); ?>>در حال ارسال</option>
                        <option value="delivered" <?php selected($status_filter, 'delivered'); ?>>تحویل شده</option>
                        <option value="cancelled" <?php selected($status_filter, 'cancelled'); ?>>لغو شده</option>
                    </select>
                    <button type="submit" class="button">فیلتر</button>
                </form>
            </div>

            <div id="sf-orders-table-container">
                <?php $this->recent_orders_table(100, $status_filter); ?>
            </div>
        </div>

        <!-- مودال جزئیات سفارش -->
        <div id="sf-order-modal" class="sf-modal" style="display:none;">
            <div class="sf-modal-overlay"></div>
            <div class="sf-modal-content">
                <div class="sf-modal-header">
                    <h2>جزئیات سفارش <span id="sf-modal-order-number"></span></h2>
                    <button class="sf-modal-close">&times;</button>
                </div>
                <div class="sf-modal-body" id="sf-modal-body">
                    <div class="sf-loading" style="text-align:center;padding:40px;">
                        <div class="sf-spinner"></div>
                        <p>در حال بارگذاری...</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function recent_orders_table($limit = 10, $status_filter = '') {
        $statuses = array('sf-pending', 'sf-preparing', 'sf-ready', 'sf-delivering', 'processing', 'on-hold');
        if (!empty($status_filter)) {
            $statuses = array($status_filter);
        }
        if ($status_filter === 'delivered') {
            $statuses = array('sf-delivered');
        }
        if ($status_filter === 'cancelled') {
            $statuses = array('cancelled');
        }

        $orders = wc_get_orders(array(
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => $statuses,
        ));

        if (empty($orders)) {
            echo '<p>هیچ سفارشی یافت نشد.</p>';
            return;
        }
        ?>
        <div class="sf-orders-table-wrap">
            <table class="sf-orders-table">
                <thead>
                    <tr>
                        <th>شماره سفارش</th>
                        <th>مشتری</th>
                        <th>تاریخ</th>
                        <th>مبلغ</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): 
                        $status = $order->get_status();
                        $status_labels = array(
                            'sf-pending' => 'در انتظار تایید',
                            'sf-preparing' => 'در حال آماده‌سازی',
                            'sf-ready' => 'آماده تحویل',
                            'sf-delivering' => 'در حال ارسال',
                            'sf-delivered' => 'تحویل شده',
                            'processing' => 'در حال پردازش',
                            'on-hold' => 'در انتظار',
                            'cancelled' => 'لغو شده',
                        );
                        $status_class = str_replace('sf-', '', $status);
                    ?>
                    <tr class="sf-order-row <?php echo ($status === 'sf-pending') ? 'sf-row-new' : ''; ?>" data-order-id="<?php echo $order->get_id(); ?>">
                        <td>#<?php echo $order->get_order_number(); ?></td>
                        <td>
                            <?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?>
                            <br><small><?php echo esc_html($order->get_billing_phone()); ?></small>
                        </td>
                        <td><?php echo wc_format_datetime($order->get_date_created()); ?></td>
                        <td><?php echo wc_price($order->get_total()); ?></td>
                        <td><span class="sf-status sf-status-<?php echo $status_class; ?>"><?php echo isset($status_labels[$status]) ? $status_labels[$status] : $status; ?></span></td>
                        <td>
                            <select class="sf-order-status" data-order-id="<?php echo $order->get_id(); ?>">
                                <option value="">تغییر وضعیت...</option>
                                <option value="sf-pending" <?php selected($status, 'sf-pending'); ?>>در انتظار تایید</option>
                                <option value="sf-preparing" <?php selected($status, 'sf-preparing'); ?>>در حال آماده‌سازی</option>
                                <option value="sf-ready" <?php selected($status, 'sf-ready'); ?>>آماده تحویل</option>
                                <option value="sf-delivering" <?php selected($status, 'sf-delivering'); ?>>در حال ارسال</option>
                                <option value="sf-delivered" <?php selected($status, 'sf-delivered'); ?>>تحویل شده</option>
                                <option value="cancelled" <?php selected($status, 'cancelled'); ?>>لغو شده</option>
                            </select>
                            <button class="button button-small sf-view-order" data-order-id="<?php echo $order->get_id(); ?>">جزئیات</button>
                            <a href="<?php echo admin_url('post.php?post=' . $order->get_id() . '&action=edit'); ?>" class="button button-small" target="_blank">ووکامرس</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function count_orders_by_status($statuses) {
        if (!function_exists('wc_get_orders')) {
            return 0;
        }

        $orders = wc_get_orders(array(
            'status' => (array) $statuses,
            'limit' => -1,
            'return' => 'ids',
        ));

        return is_array($orders) ? count($orders) : 0;
    }

    private function get_today_orders_count() {
        $orders = wc_get_orders(array(
            'date_created' => date('Y-m-d', current_time('timestamp')),
            'limit' => -1,
            'return' => 'ids',
        ));
        return count($orders);
    }

    // ============ AJAX HANDLERS ============

    public function ajax_save_settings() {
        check_ajax_referer('sf_nonce', 'nonce');
        $this->save_settings();
        wp_send_json_success();
    }

    public function ajax_update_order_status() {
        check_ajax_referer('sf_nonce', 'nonce');
        
        $order_id = intval($_POST['order_id']);
        $status = sanitize_text_field($_POST['status']);
        
        $order = wc_get_order($order_id);
        if ($order) {
            $order->update_status($status);
            wp_send_json_success(array('message' => '✅ وضعیت سفارش بروزرسانی شد.'));
        }
        
        wp_send_json_error(array('message' => '❌ خطا در بروزرسانی وضعیت.'));
    }

    public function ajax_toggle_restaurant() {
        check_ajax_referer('sf_nonce', 'nonce');
        
        $current = get_option('sf_is_open', 'yes');
        $new = ($current === 'yes') ? 'no' : 'yes';
        update_option('sf_is_open', $new);
        
        $message = ($new === 'yes') ? '✅ رستوران باز شد' : '🔴 رستوران بسته شد';
        
        wp_send_json_success(array(
            'status' => $new,
            'message' => $message,
        ));
    }

    public function ajax_check_new_orders() {
        check_ajax_referer('sf_nonce', 'nonce');
        
        $last_check = isset($_POST['last_order_id']) ? intval($_POST['last_order_id']) : 0;
        
        $orders = wc_get_orders(array(
            'limit' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => array('sf-pending', 'processing'),
            'type' => 'shop_order',
        ));

        $new_orders = array();
        $new_count = 0;
        $latest_id = $last_check;
        
        foreach ($orders as $order) {
            $order_id = $order->get_id();
            if ($order_id > $last_check) {
                $new_count++;
                $status = $order->get_status();
                if ($order_id > $latest_id) {
                    $latest_id = $order_id;
                }
                $new_orders[] = array(
                    'id' => $order_id,
                    'number' => $order->get_order_number(),
                    'customer' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'phone' => $order->get_billing_phone(),
                    'total' => wc_price($order->get_total()),
                    'date' => wc_format_datetime($order->get_date_created()),
                    'status' => $status,
                    'status_label' => ($status === 'sf-pending') ? 'در انتظار تایید' : 'در حال پردازش',
                    'items_count' => $order->get_item_count(),
                );
            }
            if ($order_id > $latest_id) {
                $latest_id = $order_id;
            }
        }
        
        wp_send_json_success(array(
            'new_count' => $new_count,
            'last_order_id' => $latest_id,
            'orders' => $new_orders,
        ));
    }

    public function ajax_get_order_detail() {
        check_ajax_referer('sf_nonce', 'nonce');
        
        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(array('message' => 'سفارش یافت نشد.'));
            return;
        }
        
        $items_html = '';
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $items_html .= '<tr>';
            $items_html .= '<td>' . esc_html($item->get_name()) . '</td>';
            $items_html .= '<td>' . $item->get_quantity() . '</td>';
            $items_html .= '<td>' . wc_price($item->get_subtotal()) . '</td>';
            $items_html .= '</tr>';
        }
        
        $data = array(
            'id' => $order_id,
            'number' => $order->get_order_number(),
            'date' => wc_format_datetime($order->get_date_created()),
            'customer' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'phone' => $order->get_billing_phone(),
            'email' => $order->get_billing_email(),
            'address' => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
            'total' => wc_price($order->get_total()),
            'subtotal' => wc_price($order->get_subtotal()),
            'shipping_total' => wc_price($order->get_shipping_total()),
            'payment_method' => $order->get_payment_method_title(),
            'status' => wc_get_order_status_name($order->get_status()),
            'items_html' => $items_html,
            'items_count' => $order->get_item_count(),
            'notes' => $order->get_customer_note(),
        );
        
        wp_send_json_success($data);
    }

    public function ajax_get_categories() {
        check_ajax_referer('sf_nonce', 'nonce');
        
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ));
        
        $data = array();
        foreach ($categories as $cat) {
            $thumbnail_id = get_term_meta($cat->term_id, 'thumbnail_id', true);
            $image = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '';
            
            $products = wc_get_products(array(
                'category' => array($cat->slug),
                'limit' => -1,
                'status' => 'publish',
            ));
            
            $items = array();
            foreach ($products as $product) {
                $image_id = $product->get_image_id();
                $items[] = array(
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'price' => $product->get_price(),
                    'description' => $product->get_short_description(),
                    'image' => $image_id ? wp_get_attachment_url($image_id) : '',
                    'permalink' => get_permalink($product->get_id()),
                    'in_stock' => $product->is_in_stock(),
                );
            }
            
            if (!empty($items)) {
                $data[] = array(
                    'id' => $cat->term_id,
                    'name' => $cat->name,
                    'description' => $cat->description,
                    'image' => $image,
                    'items' => $items,
                );
            }
        }
        
        wp_send_json_success($data);
    }

    public function ajax_check_restaurant_status() {
        check_ajax_referer('sf_nonce', 'nonce');
        $is_open = $this->is_restaurant_open();
        wp_send_json_success(array(
            'is_open' => $is_open,
            'status_text' => $this->get_restaurant_status_text(),
        ));
    }
}

function sofre_wc_missing_notice() {
    if (!current_user_can('activate_plugins')) {
        return;
    }
    echo '<div class="notice notice-error"><p><strong>سفره:</strong> افزونه ووکامرس باید نصب و فعال باشد.</p></div>';
}

function sofre_bootstrap() {
    Sofre_Plugin::instance();

    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'sofre_wc_missing_notice');
    }
}

add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

add_action('plugins_loaded', 'sofre_bootstrap', 20);