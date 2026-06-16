<?php
/**
 * کلاس مدیریت منوی رستوران - نمایش و شورتکد منو
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reyhoon_Simple_Menu {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_shortcode('reyhoon_simple_menu', array($this, 'render_menu'));
    }

    /**
     * رندر کردن منوی رستوران
     */
    public function render_menu() {
        $reyhoon = Reyhoon_Simple::instance();
        
        $primary = get_option('ryns_primary_color', '#036666');
        $secondary = get_option('ryns_secondary_color', '#ad8b4c');
        $bg_color = get_option('ryns_bg_color', '#1d1a18');
        $card_bg = get_option('ryns_card_bg_color', '#26211f');
        $text_color = get_option('ryns_text_color', '#ffffff');
        $restaurant_name = get_option('ryns_restaurant_name', get_bloginfo('name'));
        $restaurant_address = get_option('ryns_restaurant_address', '');
        $restaurant_phone = get_option('ryns_restaurant_phone', '');
        $logo = get_option('ryns_restaurant_logo', '');
        $is_open = $reyhoon->is_restaurant_open();
        $status_text = $reyhoon->get_restaurant_status_text();
        
        ob_start();
        ?>
        <div class="ryns-menu-page <?php echo !$is_open ? 'ryns-menu-closed' : ''; ?>" 
             style="--ryns-primary: <?php echo $primary; ?>; --ryns-secondary: <?php echo $secondary; ?>; --ryns-bg: <?php echo $bg_color; ?>; --ryns-card-bg: <?php echo $card_bg; ?>; --ryns-text: <?php echo $text_color; ?>;">
            
            <!-- هدر رستوران -->
            <div class="ryns-header">
                <div class="ryns-header-content">
                    <?php if ($logo): ?>
                        <div class="ryns-header-logo">
                            <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($restaurant_name); ?>">
                        </div>
                    <?php endif; ?>
                    <h1 class="ryns-restaurant-name"><?php echo esc_html($restaurant_name); ?></h1>
                    <?php if ($restaurant_address): ?>
                        <p class="ryns-restaurant-address">📍 <?php echo esc_html($restaurant_address); ?></p>
                    <?php endif; ?>
                    <?php if ($restaurant_phone): ?>
                        <p class="ryns-restaurant-phone">📞 <?php echo esc_html($restaurant_phone); ?></p>
                    <?php endif; ?>
                    <div class="ryns-header-status <?php echo $is_open ? 'open' : 'closed'; ?>">
                        <span class="ryns-header-status-dot"></span>
                        <span><?php echo $is_open ? 'باز' : 'بسته'; ?></span>
                        <?php if ($status_text): ?>
                            <span class="ryns-header-hours"><?php echo esc_html($status_text); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!$is_open): ?>
            <!-- بنر رستوران بسته است -->
            <div class="ryns-closed-banner">
                <div class="ryns-closed-icon">🔴</div>
                <h2>رستوران بسته است</h2>
                <p>در حال حاضر رستوران قادر به پذیرش سفارش نیست.</p>
                <?php if ($status_text): ?>
                    <p class="ryns-closed-hours"><?php echo esc_html($status_text); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- ناوبری دسته‌بندی‌ها -->
            <div class="ryns-categories-nav" id="ryns-categories-nav" <?php echo !$is_open ? 'style="display:none;"' : ''; ?>>
                <div class="ryns-categories-scroll">
                    <!-- توسط JS پر می‌شود -->
                </div>
            </div>

            <!-- محتوای منو -->
            <div class="ryns-menu-content" id="ryns-menu-content">
                <div class="ryns-loading">
                    <div class="ryns-spinner"></div>
                    <p>در حال بارگذاری منو...</p>
                </div>
            </div>

            <!-- سبد خرید پایین صفحه -->
            <div class="ryns-cart-bar" id="ryns-cart-bar" style="display:none;">
                <div class="ryns-cart-info">
                    <span class="ryns-cart-count" id="ryns-cart-count">0</span>
                    <span class="ryns-cart-text">آیتم</span>
                    <span class="ryns-cart-total" id="ryns-cart-total">0 تومان</span>
                </div>
                <a href="<?php echo wc_get_cart_url(); ?>" class="ryns-cart-btn">مشاهده سبد خرید</a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

Reyhoon_Simple_Menu::instance();