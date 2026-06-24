<?php
/**
 * کلاس مدیریت منوی رستوران - نمایش و شورتکد منو
 */

if (!defined('ABSPATH')) {
    exit;
}

class Boshqab_Menu {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_shortcode('boshqab_menu', array($this, 'render_menu'));
    }

    /**
     * رندر کردن منوی رستوران
     */
    public function render_menu() {
        $boshqab = Boshqab_Plugin::instance();
        
        $primary = get_option('bq_primary_color', '#036666');
        $secondary = get_option('bq_secondary_color', '#ad8b4c');
        $bg_color = get_option('bq_bg_color', '#1d1a18');
        $card_bg = get_option('bq_card_bg_color', '#26211f');
        $text_color = get_option('bq_text_color', '#ffffff');
        $restaurant_name = get_option('bq_restaurant_name', get_bloginfo('name'));
        $restaurant_address = get_option('bq_restaurant_address', '');
        $restaurant_phone = get_option('bq_restaurant_phone', '');
        $logo = get_option('bq_restaurant_logo', '');
        $is_open = $boshqab->is_restaurant_open();
        $status_text = $boshqab->get_restaurant_status_text();
        
        ob_start();
        ?>
        <div class="bq-menu-page <?php echo !$is_open ? 'bq-menu-closed' : ''; ?>" 
             style="--bq-primary: <?php echo $primary; ?>; --bq-secondary: <?php echo $secondary; ?>; --bq-bg: <?php echo $bg_color; ?>; --bq-card-bg: <?php echo $card_bg; ?>; --bq-text: <?php echo $text_color; ?>;">
            
            <!-- هدر رستوران -->
            <div class="bq-header">
                <div class="bq-header-content">
                    <?php if ($logo): ?>
                        <div class="bq-header-logo">
                            <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($restaurant_name); ?>">
                        </div>
                    <?php endif; ?>
                    <h1 class="bq-restaurant-name"><?php echo esc_html($restaurant_name); ?></h1>
                    <?php if ($restaurant_address): ?>
                        <p class="bq-restaurant-address">📍 <?php echo esc_html($restaurant_address); ?></p>
                    <?php endif; ?>
                    <?php if ($restaurant_phone): ?>
                        <p class="bq-restaurant-phone">📞 <?php echo esc_html($restaurant_phone); ?></p>
                    <?php endif; ?>
                    <div class="bq-header-status <?php echo $is_open ? 'open' : 'closed'; ?>">
                        <span class="bq-header-status-dot"></span>
                        <span><?php echo $is_open ? 'باز' : 'بسته'; ?></span>
                        <?php if ($status_text): ?>
                            <span class="bq-header-hours"><?php echo esc_html($status_text); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!$is_open): ?>
            <!-- بنر رستوران بسته است -->
            <div class="bq-closed-banner">
                <div class="bq-closed-icon">🔴</div>
                <h2>رستوران بسته است</h2>
                <p>در حال حاضر رستوران قادر به پذیرش سفارش نیست.</p>
                <?php if ($status_text): ?>
                    <p class="bq-closed-hours"><?php echo esc_html($status_text); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- ناوبری دسته‌بندی‌ها -->
            <div class="bq-categories-nav" id="bq-categories-nav" <?php echo !$is_open ? 'style="display:none;"' : ''; ?>>
                <div class="bq-categories-scroll">
                    <!-- توسط JS پر می‌شود -->
                </div>
            </div>

            <!-- محتوای منو -->
            <div class="bq-menu-content" id="bq-menu-content">
                <div class="bq-loading">
                    <div class="bq-spinner"></div>
                    <p>در حال بارگذاری منو...</p>
                </div>
            </div>

            <!-- سبد خرید پایین صفحه -->
            <div class="bq-cart-bar" id="bq-cart-bar" style="display:none;">
                <div class="bq-cart-info">
                    <span class="bq-cart-count" id="bq-cart-count">0</span>
                    <span class="bq-cart-text">آیتم</span>
                    <span class="bq-cart-total" id="bq-cart-total">0 تومان</span>
                </div>
                <a href="<?php echo wc_get_cart_url(); ?>" class="bq-cart-btn">مشاهده سبد خرید</a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

Boshqab_Menu::instance();