<?php
/**
 * کلاس مدیریت منوی رستوران - نمایش و شورتکد منو
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sofre_Menu {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_shortcode('sofre_menu', array($this, 'render_menu'));
    }

    /**
     * رندر کردن منوی رستوران
     */
    public function render_menu() {
        $sofre = Sofre_Plugin::instance();
        
        $primary = get_option('sf_primary_color', '#036666');
        $secondary = get_option('sf_secondary_color', '#ad8b4c');
        $bg_color = get_option('sf_bg_color', '#1d1a18');
        $card_bg = get_option('sf_card_bg_color', '#26211f');
        $text_color = get_option('sf_text_color', '#ffffff');
        $restaurant_name = get_option('sf_restaurant_name', get_bloginfo('name'));
        $restaurant_address = get_option('sf_restaurant_address', '');
        $restaurant_phone = get_option('sf_restaurant_phone', '');
        $logo = get_option('sf_restaurant_logo', '');
        $is_open = $sofre->is_restaurant_open();
        $status_text = $sofre->get_restaurant_status_text();
        
        ob_start();
        ?>
        <div class="sf-menu-page <?php echo !$is_open ? 'sf-menu-closed' : ''; ?>" 
             style="--sf-primary: <?php echo $primary; ?>; --sf-secondary: <?php echo $secondary; ?>; --sf-bg: <?php echo $bg_color; ?>; --sf-card-bg: <?php echo $card_bg; ?>; --sf-text: <?php echo $text_color; ?>;">
            
            <!-- هدر رستوران -->
            <div class="sf-header">
                <div class="sf-header-content">
                    <?php if ($logo): ?>
                        <div class="sf-header-logo">
                            <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($restaurant_name); ?>">
                        </div>
                    <?php endif; ?>
                    <h1 class="sf-restaurant-name"><?php echo esc_html($restaurant_name); ?></h1>
                    <?php if ($restaurant_address): ?>
                        <p class="sf-restaurant-address">📍 <?php echo esc_html($restaurant_address); ?></p>
                    <?php endif; ?>
                    <?php if ($restaurant_phone): ?>
                        <p class="sf-restaurant-phone">📞 <?php echo esc_html($restaurant_phone); ?></p>
                    <?php endif; ?>
                    <div class="sf-header-status <?php echo $is_open ? 'open' : 'closed'; ?>">
                        <span class="sf-header-status-dot"></span>
                        <span><?php echo $is_open ? 'باز' : 'بسته'; ?></span>
                        <?php if ($status_text): ?>
                            <span class="sf-header-hours"><?php echo esc_html($status_text); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!$is_open): ?>
            <!-- بنر رستوران بسته است -->
            <div class="sf-closed-banner">
                <div class="sf-closed-icon">🔴</div>
                <h2>رستوران بسته است</h2>
                <p>در حال حاضر رستوران قادر به پذیرش سفارش نیست.</p>
                <?php if ($status_text): ?>
                    <p class="sf-closed-hours"><?php echo esc_html($status_text); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- ناوبری دسته‌بندی‌ها -->
            <div class="sf-categories-nav" id="sf-categories-nav" <?php echo !$is_open ? 'style="display:none;"' : ''; ?>>
                <div class="sf-categories-scroll">
                    <!-- توسط JS پر می‌شود -->
                </div>
            </div>

            <!-- محتوای منو -->
            <div class="sf-menu-content" id="sf-menu-content">
                <div class="sf-loading">
                    <div class="sf-spinner"></div>
                    <p>در حال بارگذاری منو...</p>
                </div>
            </div>

            <!-- سبد خرید پایین صفحه -->
            <div class="sf-cart-bar" id="sf-cart-bar" style="display:none;">
                <div class="sf-cart-info">
                    <span class="sf-cart-count" id="sf-cart-count">0</span>
                    <span class="sf-cart-text">آیتم</span>
                    <span class="sf-cart-total" id="sf-cart-total">0 تومان</span>
                </div>
                <a href="<?php echo wc_get_cart_url(); ?>" class="sf-cart-btn">مشاهده سبد خرید</a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

Sofre_Menu::instance();