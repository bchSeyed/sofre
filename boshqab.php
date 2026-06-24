<?php
/**
 * Plugin Name: بشقاب - مدیریت سفارشات رستوران
 * Plugin URI: https://example.com/boshqab
 * Description: افزونه مدیریت منو و سفارشات رستوران — بشقاب
 * Version: 1.1.0
 * Author: Boshqab
 * Text Domain: boshqab
 * Domain Path: /languages
 * Requires WooCommerce: true
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BQ_VERSION', '1.1.0');
define('BQ_PATH', plugin_dir_path(__FILE__));
define('BQ_URL', plugin_dir_url(__FILE__));

class Boshqab_Plugin {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
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
            if (get_option('bq_' . $key) === false) {
                add_option('bq_' . $key, $value);
            }
        }

        // ذخیره پیشفرض ساعت کاری هفتگی
        if (!get_option('bq_business_hours')) {
            $week_hours = array();
            $days = array('saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday');
            foreach ($days as $day) {
                $week_hours[$day] = array(
                    'is_open' => ($day !== 'friday') ? 'yes' : 'no',
                    'open_time' => '09:00',
                    'close_time' => '23:00',
                );
            }
            update_option('bq_business_hours', $week_hours);
        }
    }

    public function init() {
        $this->includes();
        
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_assets'));
        add_action('init', array($this, 'register_order_statuses'));
        
        // Ajax handlers
        add_action('wp_ajax_bq_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_bq_update_order_status', array($this, 'ajax_update_order_status'));
        add_action('wp_ajax_bq_toggle_restaurant', array($this, 'ajax_toggle_restaurant'));
        add_action('wp_ajax_bq_check_new_orders', array($this, 'ajax_check_new_orders'));
        add_action('wp_ajax_bq_get_order_detail', array($this, 'ajax_get_order_detail'));
        add_action('wp_ajax_nopriv_bq_get_categories', array($this, 'ajax_get_categories'));
        add_action('wp_ajax_bq_get_categories', array($this, 'ajax_get_categories'));
        add_action('wp_ajax_nopriv_bq_check_restaurant_status', array($this, 'ajax_check_restaurant_status'));
        add_action('wp_ajax_bq_check_restaurant_status', array($this, 'ajax_check_restaurant_status'));
    }

    private function includes() {
        require_once BQ_PATH . 'includes/class-menu.php';
        require_once BQ_PATH . 'includes/class-orders.php';
        require_once BQ_PATH . 'includes/class-frontend.php';
        require_once BQ_PATH . 'includes/class-otp-login.php';
        require_once BQ_PATH . 'includes/class-drawer.php';
        require_once BQ_PATH . 'includes/class-services.php';
        require_once BQ_PATH . 'includes/class-shipping.php';
    }

    private function create_default_pages() {
        if (!get_option('bq_menu_page_id')) {
            $page_id = wp_insert_post(array(
                'post_title' => 'منوی رستوران',
                'post_content' => '[boshqab_menu]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'comment_status' => 'closed',
            ));
            if ($page_id && !is_wp_error($page_id)) {
                update_option('bq_menu_page_id', $page_id);
            }
        }
    }

    public function admin_menu() {
        $is_open = get_option('bq_is_open', 'yes');
        $open_icon = ($is_open === 'yes') ? '<span style="color:#4CAF50;">●</span>' : '<span style="color:#f44336;">○</span>';
        
        add_menu_page(
            'بشقاب',
            'بشقاب ' . $open_icon,
            'manage_options',
            'boshqab',
            array($this, 'dashboard_page'),
            'dashicons-food',
            55
        );

        add_submenu_page(
            'boshqab',
            'سفارشات',
            'سفارشات',
            'manage_woocommerce',
            'boshqab-orders',
            array($this, 'orders_page')
        );

        add_submenu_page(
            'boshqab',
            'تنظیمات',
            'تنظیمات',
            'manage_options',
            'boshqab-settings',
            array($this, 'settings_page')
        );
    }

    public function admin_assets($hook) {
        if (strpos($hook, 'boshqab') === false && $hook !== 'post.php') {
            return;
        }
        
        wp_enqueue_style('bq-admin', BQ_URL . 'assets/admin.css', array(), BQ_VERSION);
        wp_enqueue_script('bq-admin', BQ_URL . 'assets/admin.js', array('jquery'), BQ_VERSION, true);
        wp_localize_script('bq-admin', 'bq_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bq_nonce'),
            'is_restaurant_open' => get_option('bq_is_open', 'yes'),
            'restaurant_name' => get_option('bq_restaurant_name', get_bloginfo('name')),
        ));

        // انکود کردن صدای پیشفرض اعلان (Base64 Beep)
        $sound = get_option('bq_notification_sound', '');
        if (empty($sound)) {
            // یک صدای بوق ساده به صورت base64
            $sound = base64_encode('beep');
        }
        wp_localize_script('bq-admin', 'bq_sound', array(
            'data' => $sound,
        ));
    }

    public function frontend_assets() {
        global $post;
        $menu_page_id = get_option('bq_menu_page_id');
        $is_menu_page = ($menu_page_id && is_page($menu_page_id));
        $has_shortcode = ($post && has_shortcode($post->post_content, 'boshqab_menu'));
        
        if (!$is_menu_page && !$has_shortcode) {
            return;
        }
        
        wp_enqueue_style('bq-frontend', BQ_URL . 'assets/frontend.css', array(), BQ_VERSION);
        wp_enqueue_script('bq-frontend', BQ_URL . 'assets/frontend.js', array('jquery'), BQ_VERSION, true);
        wp_localize_script('bq-frontend', 'bq_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bq_nonce'),
            'cart_url' => wc_get_cart_url(),
            'restaurant_open' => $this->is_restaurant_open(),
        ));
    }

    // ============ STATUSES ============

    public function register_order_statuses() {
        register_post_status('wc-bq-pending', array(
            'label' => 'در انتظار تایید رستوران',
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'label_count' => _n_noop('در انتظار تایید (%s)', 'در انتظار تایید (%s)'),
        ));
        
        register_post_status('wc-bq-preparing', array(
            'label' => 'در حال آماده‌سازی',
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'label_count' => _n_noop('در حال آماده‌سازی (%s)', 'در حال آماده‌سازی (%s)'),
        ));
        
        register_post_status('wc-bq-ready', array(
            'label' => 'آماده تحویل',
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'label_count' => _n_noop('آماده تحویل (%s)', 'آماده تحویل (%s)'),
        ));
        
        register_post_status('wc-bq-delivering', array(
            'label' => 'در حال ارسال',
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'label_count' => _n_noop('در حال ارسال (%s)', 'در حال ارسال (%s)'),
        ));
        
        register_post_status('wc-bq-delivered', array(
            'label' => 'تحویل شده',
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'label_count' => _n_noop('تحویل شده (%s)', 'تحویل شده (%s)'),
        ));
    }

    // ============ RESTAURANT STATUS ============

    public function is_restaurant_open() {
        $is_open_setting = get_option('bq_is_open', 'yes');
        if ($is_open_setting === 'no') {
            return false;
        }

        $enable_ordering = get_option('bq_enable_ordering', 'yes');
        if ($enable_ordering === 'no') {
            return false;
        }

        // بررسی ساعت کاری هفتگی
        $business_hours = get_option('bq_business_hours', array());
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
        $business_hours = get_option('bq_business_hours', array());
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
        $orders_count = wc_orders_count('bq-pending') + wc_orders_count('processing');
        $menu_items = wp_count_posts('product')->publish;
        $today_orders = $this->get_today_orders_count();
        $is_open = $this->is_restaurant_open();
        ?>
        <div class="bq-dashboard">
            <div class="bq-dashboard-header">
                <h1>داشبورد بشقاب</h1>
                <div class="bq-restaurant-toggle">
                    <span class="bq-toggle-label">وضعیت رستوران:</span>
                    <button class="bq-toggle-btn <?php echo $is_open ? 'open' : 'closed'; ?>" 
                            id="bq-toggle-restaurant"
                            data-current="<?php echo $is_open ? 'open' : 'closed'; ?>">
                        <span class="bq-toggle-dot"></span>
                        <span class="bq-toggle-text"><?php echo $is_open ? 'باز' : 'بسته'; ?></span>
                    </button>
                </div>
            </div>

            <div class="bq-status-banner <?php echo $is_open ? 'bq-status-open' : 'bq-status-closed'; ?>" id="bq-status-banner">
                <span class="bq-status-icon"><?php echo $is_open ? '🟢' : '🔴'; ?></span>
                <span class="bq-status-msg">
                    <?php 
                    if ($is_open) {
                        echo 'رستوران باز است و سفارش پذیرفته می‌شود';
                    } else {
                        echo 'رستوران بسته است - سفارشات پذیرفته نمی‌شوند';
                    }
                    ?>
                </span>
                <span class="bq-hours-text"><?php echo $this->get_restaurant_status_text(); ?></span>
            </div>

            <div class="bq-stats-grid">
                <div class="bq-stat-card">
                    <span class="bq-stat-icon">📋</span>
                    <span class="bq-stat-number"><?php echo $orders_count; ?></span>
                    <span class="bq-stat-label">سفارشات فعال</span>
                </div>
                <div class="bq-stat-card">
                    <span class="bq-stat-icon">🍽️</span>
                    <span class="bq-stat-number"><?php echo $menu_items; ?></span>
                    <span class="bq-stat-label">آیتم‌های منو</span>
                </div>
                <div class="bq-stat-card">
                    <span class="bq-stat-icon">📅</span>
                    <span class="bq-stat-number"><?php echo $today_orders; ?></span>
                    <span class="bq-stat-label">سفارشات امروز</span>
                </div>
                <div class="bq-stat-card">
                    <span class="bq-stat-icon">👁️</span>
                    <span class="bq-stat-number"><a href="<?php echo get_permalink(get_option('bq_menu_page_id')); ?>" target="_blank">مشاهده</a></span>
                    <span class="bq-stat-label">نمایش منو</span>
                </div>
            </div>
            
            <div class="bq-section">
                <h2>سفارشات جدید <span class="bq-live-badge" id="bq-live-badge" style="display:none;">🔔 جدید</span></h2>
                <div id="bq-orders-container">
                    <?php $this->recent_orders_table(10); ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function settings_page() {
        if (isset($_POST['bq_save_settings']) && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'bq_settings')) {
            $this->save_settings();
        }
        ?>
        <div class="bq-settings-wrap">
            <h1>تنظیمات بشقاب</h1>
            <form method="post" action="">
                <?php wp_nonce_field('bq_settings'); ?>
                <input type="hidden" name="bq_save_settings" value="1">
                
                <div class="bq-settings-section">
                    <h2>اطلاعات رستوران</h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="bq_restaurant_name">نام رستوران</label></th>
                            <td><input type="text" id="bq_restaurant_name" name="bq_restaurant_name" value="<?php echo esc_attr(get_option('bq_restaurant_name')); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="bq_restaurant_logo">لوگوی رستوران</label></th>
                            <td>
                                <div class="bq-logo-upload">
                                    <input type="hidden" id="bq_restaurant_logo" name="bq_restaurant_logo" value="<?php echo esc_attr(get_option('bq_restaurant_logo')); ?>">
                                    <div class="bq-logo-preview" id="bq-logo-preview">
                                        <?php $logo = get_option('bq_restaurant_logo'); ?>
                                        <?php if ($logo): ?>
                                            <img src="<?php echo esc_url($logo); ?>" style="max-width:150px;max-height:80px;">
                                        <?php else: ?>
                                            <span class="bq-logo-placeholder">لوگو انتخاب نشده</span>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="button bq-upload-logo-btn" id="bq-upload-logo">انتخاب لوگو</button>
                                    <button type="button" class="button bq-remove-logo-btn" id="bq-remove-logo" <?php echo $logo ? '' : 'style="display:none;"'; ?>>حذف لوگو</button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="bq_restaurant_address">آدرس</label></th>
                            <td><textarea id="bq_restaurant_address" name="bq_restaurant_address" rows="2" class="regular-text"><?php echo esc_textarea(get_option('bq_restaurant_address')); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="bq_restaurant_phone">تلفن تماس</label></th>
                            <td><input type="text" id="bq_restaurant_phone" name="bq_restaurant_phone" value="<?php echo esc_attr(get_option('bq_restaurant_phone')); ?>" class="regular-text"></td>
                        </tr>
                    </table>
                </div>

                <div class="bq-settings-section">
                    <h2>تنظیمات سفارش و ارسال</h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="bq_enable_ordering">فعال بودن سفارش‌گیری</label></th>
                            <td>
                                <select id="bq_enable_ordering" name="bq_enable_ordering">
                                    <option value="yes" <?php selected(get_option('bq_enable_ordering', 'yes'), 'yes'); ?>>فعال</option>
                                    <option value="no" <?php selected(get_option('bq_enable_ordering', 'yes'), 'no'); ?>>غیرفعال</option>
                                </select>
                                <p class="description">با غیرفعال کردن، کاربران نمی‌توانند سفارش ثبت کنند</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="bq_enable_delivery">ارسال درب منزل</label></th>
                            <td>
                                <select id="bq_enable_delivery" name="bq_enable_delivery">
                                    <option value="yes" <?php selected(get_option('bq_enable_delivery'), 'yes'); ?>>فعال</option>
                                    <option value="no" <?php selected(get_option('bq_enable_delivery'), 'no'); ?>>غیرفعال</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="bq_delivery_fee">هزینه ارسال (تومان)</label></th>
                            <td><input type="number" id="bq_delivery_fee" name="bq_delivery_fee" value="<?php echo esc_attr(get_option('bq_delivery_fee', 0)); ?>" class="small-text"></td>
                        </tr>
                        <tr>
                            <th><label for="bq_free_delivery_min">ارسال رایگان برای سفارشات بالای (تومان)</label></th>
                            <td><input type="number" id="bq_free_delivery_min" name="bq_free_delivery_min" value="<?php echo esc_attr(get_option('bq_free_delivery_min', 0)); ?>" class="small-text"></td>
                        </tr>
                        <tr>
                            <th><label for="bq_min_order_amount">حداقل مبلغ سفارش (تومان)</label></th>
                            <td><input type="number" id="bq_min_order_amount" name="bq_min_order_amount" value="<?php echo esc_attr(get_option('bq_min_order_amount', 0)); ?>" class="small-text"></td>
                        </tr>
                    </table>
                </div>

                <div class="bq-settings-section">
                    <h2>ساعت کاری هفتگی</h2>
                    <p class="description">ساعت‌های باز بودن رستوران در هر روز هفته</p>
                    <?php 
                    $business_hours = get_option('bq_business_hours', array());
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
                    <table class="form-table bq-hours-table">
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
                                    <select name="bq_hours[<?php echo $day_key; ?>][is_open]">
                                        <option value="yes" <?php selected($is_open_day, 'yes'); ?>>باز</option>
                                        <option value="no" <?php selected($is_open_day, 'no'); ?>>تعطیل</option>
                                    </select>
                                </td>
                                <td><input type="time" name="bq_hours[<?php echo $day_key; ?>][open_time]" value="<?php echo esc_attr($day_data['open_time'] ?? '09:00'); ?>"></td>
                                <td><input type="time" name="bq_hours[<?php echo $day_key; ?>][close_time]" value="<?php echo esc_attr($day_data['close_time'] ?? '23:00'); ?>"></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="bq-settings-section">
                    <h2>رنگ‌بندی منو</h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="bq_primary_color">رنگ اصلی</label></th>
                            <td><input type="color" id="bq_primary_color" name="bq_primary_color" value="<?php echo esc_attr(get_option('bq_primary_color', '#036666')); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="bq_secondary_color">رنگ ثانویه</label></th>
                            <td><input type="color" id="bq_secondary_color" name="bq_secondary_color" value="<?php echo esc_attr(get_option('bq_secondary_color', '#ad8b4c')); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="bq_bg_color">رنگ پس‌زمینه</label></th>
                            <td><input type="color" id="bq_bg_color" name="bq_bg_color" value="<?php echo esc_attr(get_option('bq_bg_color', '#1d1a18')); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="bq_card_bg_color">رنگ کارت آیتم‌ها</label></th>
                            <td><input type="color" id="bq_card_bg_color" name="bq_card_bg_color" value="<?php echo esc_attr(get_option('bq_card_bg_color', '#26211f')); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="bq_text_color">رنگ متن</label></th>
                            <td><input type="color" id="bq_text_color" name="bq_text_color" value="<?php echo esc_attr(get_option('bq_text_color', '#ffffff')); ?>"></td>
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
            'bq_restaurant_name', 'bq_restaurant_address', 'bq_restaurant_phone',
            'bq_restaurant_logo', 'bq_primary_color', 'bq_secondary_color',
            'bq_bg_color', 'bq_card_bg_color', 'bq_text_color',
            'bq_enable_delivery', 'bq_delivery_fee', 'bq_free_delivery_min',
            'bq_min_order_amount', 'bq_enable_ordering',
        );
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_option($field, sanitize_text_field($_POST[$field]));
            }
        }

        // ذخیره ساعت کاری
        if (isset($_POST['bq_hours']) && is_array($_POST['bq_hours'])) {
            $hours = array();
            foreach ($_POST['bq_hours'] as $day => $data) {
                $hours[sanitize_key($day)] = array(
                    'is_open' => isset($data['is_open']) ? sanitize_text_field($data['is_open']) : 'yes',
                    'open_time' => isset($data['open_time']) ? sanitize_text_field($data['open_time']) : '09:00',
                    'close_time' => isset($data['close_time']) ? sanitize_text_field($data['close_time']) : '23:00',
                );
            }
            update_option('bq_business_hours', $hours);
        }
        
        echo '<div class="notice notice-success"><p>✅ تنظیمات با موفقیت ذخیره شد.</p></div>';
    }

    public function orders_page() {
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        ?>
        <div class="bq-orders-wrap">
            <div class="bq-orders-header">
                <h1>مدیریت سفارشات</h1>
                <div class="bq-live-indicator" id="bq-live-indicator">
                    <span class="bq-live-dot"></span>
                    <span class="bq-live-text">بررسی خودکار سفارشات جدید...</span>
                </div>
            </div>

            <!-- فیلتر وضعیت -->
            <div class="bq-order-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="boshqab-orders">
                    <select name="status" onchange="this.form.submit()">
                        <option value="">همه سفارشات فعال</option>
                        <option value="bq-pending" <?php selected($status_filter, 'bq-pending'); ?>>در انتظار تایید</option>
                        <option value="bq-preparing" <?php selected($status_filter, 'bq-preparing'); ?>>در حال آماده‌سازی</option>
                        <option value="bq-ready" <?php selected($status_filter, 'bq-ready'); ?>>آماده تحویل</option>
                        <option value="bq-delivering" <?php selected($status_filter, 'bq-delivering'); ?>>در حال ارسال</option>
                        <option value="delivered" <?php selected($status_filter, 'delivered'); ?>>تحویل شده</option>
                        <option value="cancelled" <?php selected($status_filter, 'cancelled'); ?>>لغو شده</option>
                    </select>
                    <button type="submit" class="button">فیلتر</button>
                </form>
            </div>

            <div id="bq-orders-table-container">
                <?php $this->recent_orders_table(100, $status_filter); ?>
            </div>
        </div>

        <!-- مودال جزئیات سفارش -->
        <div id="bq-order-modal" class="bq-modal" style="display:none;">
            <div class="bq-modal-overlay"></div>
            <div class="bq-modal-content">
                <div class="bq-modal-header">
                    <h2>جزئیات سفارش <span id="bq-modal-order-number"></span></h2>
                    <button class="bq-modal-close">&times;</button>
                </div>
                <div class="bq-modal-body" id="bq-modal-body">
                    <div class="bq-loading" style="text-align:center;padding:40px;">
                        <div class="bq-spinner"></div>
                        <p>در حال بارگذاری...</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function recent_orders_table($limit = 10, $status_filter = '') {
        $statuses = array('bq-pending', 'bq-preparing', 'bq-ready', 'bq-delivering', 'processing', 'on-hold');
        if (!empty($status_filter)) {
            $statuses = array($status_filter);
        }
        if ($status_filter === 'delivered') {
            $statuses = array('bq-delivered');
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
        <div class="bq-orders-table-wrap">
            <table class="bq-orders-table">
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
                            'bq-pending' => 'در انتظار تایید',
                            'bq-preparing' => 'در حال آماده‌سازی',
                            'bq-ready' => 'آماده تحویل',
                            'bq-delivering' => 'در حال ارسال',
                            'bq-delivered' => 'تحویل شده',
                            'processing' => 'در حال پردازش',
                            'on-hold' => 'در انتظار',
                            'cancelled' => 'لغو شده',
                        );
                        $status_class = str_replace('bq-', '', $status);
                    ?>
                    <tr class="bq-order-row <?php echo ($status === 'bq-pending') ? 'bq-row-new' : ''; ?>" data-order-id="<?php echo $order->get_id(); ?>">
                        <td>#<?php echo $order->get_order_number(); ?></td>
                        <td>
                            <?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?>
                            <br><small><?php echo esc_html($order->get_billing_phone()); ?></small>
                        </td>
                        <td><?php echo wc_format_datetime($order->get_date_created()); ?></td>
                        <td><?php echo wc_price($order->get_total()); ?></td>
                        <td><span class="bq-status bq-status-<?php echo $status_class; ?>"><?php echo isset($status_labels[$status]) ? $status_labels[$status] : $status; ?></span></td>
                        <td>
                            <select class="bq-order-status" data-order-id="<?php echo $order->get_id(); ?>">
                                <option value="">تغییر وضعیت...</option>
                                <option value="bq-pending" <?php selected($status, 'bq-pending'); ?>>در انتظار تایید</option>
                                <option value="bq-preparing" <?php selected($status, 'bq-preparing'); ?>>در حال آماده‌سازی</option>
                                <option value="bq-ready" <?php selected($status, 'bq-ready'); ?>>آماده تحویل</option>
                                <option value="bq-delivering" <?php selected($status, 'bq-delivering'); ?>>در حال ارسال</option>
                                <option value="bq-delivered" <?php selected($status, 'bq-delivered'); ?>>تحویل شده</option>
                                <option value="cancelled" <?php selected($status, 'cancelled'); ?>>لغو شده</option>
                            </select>
                            <button class="button button-small bq-view-order" data-order-id="<?php echo $order->get_id(); ?>">جزئیات</button>
                            <a href="<?php echo admin_url('post.php?post=' . $order->get_id() . '&action=edit'); ?>" class="button button-small" target="_blank">ووکامرس</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
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
        check_ajax_referer('bq_nonce', 'nonce');
        $this->save_settings();
        wp_send_json_success();
    }

    public function ajax_update_order_status() {
        check_ajax_referer('bq_nonce', 'nonce');
        
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
        check_ajax_referer('bq_nonce', 'nonce');
        
        $current = get_option('bq_is_open', 'yes');
        $new = ($current === 'yes') ? 'no' : 'yes';
        update_option('bq_is_open', $new);
        
        $message = ($new === 'yes') ? '✅ رستوران باز شد' : '🔴 رستوران بسته شد';
        
        wp_send_json_success(array(
            'status' => $new,
            'message' => $message,
        ));
    }

    public function ajax_check_new_orders() {
        check_ajax_referer('bq_nonce', 'nonce');
        
        $last_check = isset($_POST['last_order_id']) ? intval($_POST['last_order_id']) : 0;
        
        $orders = wc_get_orders(array(
            'limit' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => array('bq-pending', 'processing'),
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
                    'status_label' => ($status === 'bq-pending') ? 'در انتظار تایید' : 'در حال پردازش',
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
        check_ajax_referer('bq_nonce', 'nonce');
        
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
        check_ajax_referer('bq_nonce', 'nonce');
        
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
        check_ajax_referer('bq_nonce', 'nonce');
        $is_open = $this->is_restaurant_open();
        wp_send_json_success(array(
            'is_open' => $is_open,
            'status_text' => $this->get_restaurant_status_text(),
        ));
    }
}

Boshqab_Plugin::instance();