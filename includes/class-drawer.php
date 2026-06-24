<?php
/**
 * کلاس سبد خرید Drawer کشویی - جایگزین Cart Bar ساده
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sofre_Drawer {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('wp', array($this, 'boot'));

        // Ajax handlers
        add_action('wp_ajax_sf_drawer_update_qty', array($this, 'ajax_update_quantity'));
        add_action('wp_ajax_nopriv_sf_drawer_update_qty', array($this, 'ajax_update_quantity'));
        add_action('wp_ajax_sf_drawer_remove_item', array($this, 'ajax_remove_item'));
        add_action('wp_ajax_nopriv_sf_drawer_remove_item', array($this, 'ajax_remove_item'));
        add_action('wp_ajax_sf_drawer_empty_cart', array($this, 'ajax_empty_cart'));
        add_action('wp_ajax_nopriv_sf_drawer_empty_cart', array($this, 'ajax_empty_cart'));
        add_action('wp_ajax_sf_get_drawer_content', array($this, 'ajax_get_drawer_content'));
        add_action('wp_ajax_nopriv_sf_get_drawer_content', array($this, 'ajax_get_drawer_content'));
        
        // Fragment refresh
        add_filter('woocommerce_add_to_cart_fragments', array($this, 'cart_fragments'));
    }

    public function boot() {
        if (!Sofre_Plugin::is_menu_context()) {
            return;
        }

        add_action('wp_footer', array($this, 'add_drawer'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_assets'), 25);
    }

    public function frontend_assets() {
        if (!wp_style_is('sf-frontend', 'enqueued')) {
            return;
        }

        wp_add_inline_style('sf-frontend', $this->get_drawer_styles());
        wp_add_inline_script('sf-frontend', $this->get_drawer_script(), 'before');
    }

    private function get_drawer_styles() {
        return '
        .sf-drawer-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.6);
            z-index: 99997;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }
        .sf-drawer-overlay.open {
            opacity: 1;
            visibility: visible;
        }
        .sf-drawer {
            position: fixed;
            top: 0; left: -380px;
            width: 380px;
            max-width: 90vw;
            height: 100%;
            background: #fff;
            z-index: 99998;
            direction: rtl;
            transition: left 0.3s ease;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 20px rgba(0,0,0,0.15);
        }
        .sf-drawer.open {
            left: 0;
        }
        .sf-drawer-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 20px 16px;
            border-bottom: 1px solid #eee;
        }
        .sf-drawer-header h3 {
            margin: 0;
            font-size: 18px;
            color: #1d1a18;
        }
        .sf-drawer-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #888;
            padding: 0 4px;
        }
        .sf-drawer-close:hover {
            color: #333;
        }
        .sf-drawer-empty-btn {
            background: none;
            border: none;
            color: #f44336;
            font-size: 13px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .sf-drawer-empty-btn:hover {
            text-decoration: underline;
        }
        .sf-drawer-body {
            flex: 1;
            overflow-y: auto;
            padding: 16px 20px;
        }
        .sf-drawer-body::-webkit-scrollbar {
            width: 4px;
        }
        .sf-drawer-body::-webkit-scrollbar-thumb {
            background: #ddd;
            border-radius: 4px;
        }
        .sf-drawer-empty {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .sf-drawer-empty-icon {
            font-size: 56px;
            margin-bottom: 16px;
        }
        .sf-drawer-empty p {
            margin: 0;
            font-size: 15px;
        }
        .sf-drawer-item {
            display: flex;
            gap: 12px;
            padding: 14px 0;
            border-bottom: 1px solid #f5f5f5;
            position: relative;
        }
        .sf-drawer-item-image {
            width: 70px;
            height: 70px;
            flex-shrink: 0;
            border-radius: 10px;
            overflow: hidden;
        }
        .sf-drawer-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .sf-drawer-item-info {
            flex: 1;
            min-width: 0;
        }
        .sf-drawer-item-name {
            font-size: 14px;
            font-weight: 600;
            color: #1d1a18;
            margin: 0 0 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sf-drawer-item-price {
            font-size: 14px;
            font-weight: 700;
            color: #036666;
        }
        .sf-drawer-item-subtotal {
            font-size: 12px;
            color: #888;
        }
        .sf-drawer-item-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
        }
        .sf-drawer-qty-btn {
            width: 28px;
            height: 28px;
            border: 1px solid #ddd;
            border-radius: 50%;
            background: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #555;
            transition: all 0.15s;
        }
        .sf-drawer-qty-btn:hover {
            background: #036666;
            color: #fff;
            border-color: #036666;
        }
        .sf-drawer-qty {
            font-size: 14px;
            font-weight: 600;
            min-width: 20px;
            text-align: center;
            color: #333;
        }
        .sf-drawer-remove-item {
            background: none;
            border: none;
            color: #f44336;
            font-size: 12px;
            cursor: pointer;
            margin-right: auto;
        }
        .sf-drawer-footer {
            padding: 16px 20px 24px;
            border-top: 1px solid #eee;
            background: #fafafa;
        }
        .sf-drawer-total {
            display: flex;
            justify-content: space-between;
            font-size: 16px;
            font-weight: 700;
            color: #1d1a18;
            margin-bottom: 12px;
        }
        .sf-drawer-total span:last-child {
            color: #036666;
        }
        .sf-drawer-checkout-btn {
            display: block;
            width: 100%;
            padding: 14px;
            background: #036666;
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s;
        }
        .sf-drawer-checkout-btn:hover {
            background: #024d4d;
            color: #fff;
        }
        .sf-drawer-floating-btn {
            position: fixed;
            bottom: 20px;
            left: 20px;
            z-index: 99995;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #036666;
            color: #fff;
            border: none;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 16px rgba(3,102,102,0.4);
            transition: transform 0.2s;
        }
        .sf-drawer-floating-btn:hover {
            transform: scale(1.1);
        }
        .sf-drawer-floating-btn svg {
            width: 24px;
            height: 24px;
            fill: #fff;
        }
        .sf-drawer-floating-count {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #f44336;
            color: #fff;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            font-size: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sf-drawer-loader {
            text-align: center;
            padding: 40px;
        }
        .sf-drawer-spinner {
            width: 30px;
            height: 30px;
            border: 3px solid #eee;
            border-top-color: #036666;
            border-radius: 50%;
            animation: sf-spin 0.6s linear infinite;
            margin: 0 auto 12px;
        }
        @media (max-width: 480px) {
            .sf-drawer { left: -100vw; width: 100vw; }
            .sf-drawer.open { left: 0; }
        }
        ';
    }

    private function get_drawer_script() {
        return '
        jQuery(document).ready(function($) {
            var drawerOpen = false;
            
            // باز کردن دراور
            $(document).on("click", ".sf-drawer-floating-btn, .sf-cart-bar", function() {
                openDrawer();
            });
            
            function openDrawer() {
                drawerOpen = true;
                $(".sf-drawer-overlay").addClass("open");
                $(".sf-drawer").addClass("open");
                $("body").css("overflow", "hidden");
                loadDrawerContent();
            }
            
            // بستن دراور
            function closeDrawer() {
                drawerOpen = false;
                $(".sf-drawer-overlay").removeClass("open");
                $(".sf-drawer").removeClass("open");
                $("body").css("overflow", "");
            }
            
            $(document).on("click", ".sf-drawer-close, .sf-drawer-overlay", function() {
                closeDrawer();
            });
            
            // بارگذاری محتوای دراور
            function loadDrawerContent() {
                $(".sf-drawer-body").html(
                    "<div class=\"sf-drawer-loader\">" +
                    "<div class=\"sf-drawer-spinner\"></div>" +
                    "<p style=\"color:#888;\">در حال بارگذاری...</p>" +
                    "</div>"
                );
                
                $.post(sf_ajax.ajax_url, {
                    action: "sf_get_drawer_content",
                    nonce: sf_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        $(".sf-drawer-body").html(response.data.html);
                        updateFooter(response.data);
                        updateFloatingButton(response.data);
                    }
                });
            }
            
            // تغییر تعداد
            $(document).on("click", ".sf-drawer-qty-minus", function() {
                var key = $(this).data("key");
                var qty = parseInt($(this).siblings(".sf-drawer-qty").text()) - 1;
                if (qty < 1) { qty = 1; }
                updateCartItem(key, qty);
            });
            
            $(document).on("click", ".sf-drawer-qty-plus", function() {
                var key = $(this).data("key");
                var qty = parseInt($(this).siblings(".sf-drawer-qty").text()) + 1;
                updateCartItem(key, qty);
            });
            
            function updateCartItem(key, qty) {
                $.post(sf_ajax.ajax_url, {
                    action: "sf_drawer_update_qty",
                    cart_key: key,
                    quantity: qty,
                    nonce: sf_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        $("body").trigger("wc_fragment_refresh");
                        loadDrawerContent();
                    }
                });
            }
            
            // حذف آیتم
            $(document).on("click", ".sf-drawer-remove-item", function() {
                var key = $(this).data("key");
                $.post(sf_ajax.ajax_url, {
                    action: "sf_drawer_remove_item",
                    cart_key: key,
                    nonce: sf_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        $("body").trigger("wc_fragment_refresh");
                        loadDrawerContent();
                    }
                });
            });
            
            // خالی کردن سبد
            $(document).on("click", ".sf-drawer-empty-btn", function() {
                if (!confirm("سبد خرید خالی شود؟")) return;
                $.post(sf_ajax.ajax_url, {
                    action: "sf_drawer_empty_cart",
                    nonce: sf_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        $("body").trigger("wc_fragment_refresh");
                        loadDrawerContent();
                    }
                });
            });
            
            function updateFooter(data) {
                if (data.count > 0) {
                    $(".sf-drawer-footer").html(
                        "<div class=\"sf-drawer-total\">" +
                        "<span>جمع کل</span>" +
                        "<span>" + data.total + "</span>" +
                        "</div>" +
                        "<a href=\"" + data.checkout_url + "\" class=\"sf-drawer-checkout-btn\">" +
                        "تسویه حساب (" + data.count + " آیتم)" +
                        "</a>"
                    ).show();
                } else {
                    $(".sf-drawer-footer").hide();
                }
            }
            
            function updateFloatingButton(data) {
                if (data.count > 0) {
                    $(".sf-drawer-floating-btn").show();
                    $(".sf-drawer-floating-count").text(data.count);
                } else {
                    $(".sf-drawer-floating-btn").hide();
                }
            }
            
            // به‌روزرسانی از طریق fragments
            $(document).on("wc_fragments_refreshed", function() {
                var count = $(".cart-contents .count, .cart-total .count, .widget_shopping_cart .count").first().text();
                count = parseInt(count) || 0;
                if (count > 0) {
                    $(".sf-drawer-floating-btn").show();
                    $(".sf-drawer-floating-count").text(count);
                } else {
                    $(".sf-drawer-floating-btn").hide();
                }
            });
            
            // چک کردن اولیه
            setTimeout(function() {
                var count = $(".cart-contents .count, .widget_shopping_cart .count").first().text();
                count = parseInt(count) || 0;
                if (count > 0) {
                    $(".sf-drawer-floating-btn").show();
                    $(".sf-drawer-floating-count").text(count);
                }
            }, 1000);
        });
        ';
    }

    public function add_drawer() {
        ?>
        <button class="sf-drawer-floating-btn" id="sf-drawer-floating-btn">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49A1.003 1.003 0 0020 4H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/>
            </svg>
            <span class="sf-drawer-floating-count" style="display:none;">0</span>
        </button>
        <div class="sf-drawer-overlay"></div>
        <div class="sf-drawer">
            <div class="sf-drawer-header">
                <h3>🛒 سبد خرید</h3>
                <div>
                    <button class="sf-drawer-empty-btn">🗑️ خالی کردن</button>
                    <button class="sf-drawer-close">&times;</button>
                </div>
            </div>
            <div class="sf-drawer-body">
                <div class="sf-drawer-empty">
                    <div class="sf-drawer-empty-icon">🛒</div>
                    <p>سبد خرید شما خالی است</p>
                </div>
            </div>
            <div class="sf-drawer-footer" style="display:none;"></div>
        </div>
        <?php
    }

    public function ajax_get_drawer_content() {
        check_ajax_referer('sf_nonce', 'nonce');

        if (!WC()->cart) {
            wp_send_json_error();
        }
        
        ob_start();
        
        if (WC()->cart->is_empty()) {
            echo '<div class="sf-drawer-empty">';
            echo '<div class="sf-drawer-empty-icon">🛒</div>';
            echo '<p>سبد خرید شما خالی است</p>';
            echo '</div>';
        } else {
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $product = $cart_item['data'];
                $image = $product->get_image(array(70, 70));
                $price = $product->get_price();
                $subtotal = $cart_item['line_subtotal'] + $cart_item['line_subtotal_tax'];
                ?>
                <div class="sf-drawer-item">
                    <div class="sf-drawer-item-image">
                        <?php echo $image; ?>
                    </div>
                    <div class="sf-drawer-item-info">
                        <div class="sf-drawer-item-name"><?php echo esc_html($product->get_name()); ?></div>
                        <div class="sf-drawer-item-price"><?php echo wc_price($price); ?></div>
                        <div class="sf-drawer-item-actions">
                            <button class="sf-drawer-qty-btn sf-drawer-qty-minus" data-key="<?php echo esc_attr($cart_item_key); ?>">−</button>
                            <span class="sf-drawer-qty"><?php echo $cart_item['quantity']; ?></span>
                            <button class="sf-drawer-qty-btn sf-drawer-qty-plus" data-key="<?php echo esc_attr($cart_item_key); ?>">+</button>
                            <button class="sf-drawer-remove-item" data-key="<?php echo esc_attr($cart_item_key); ?>">🗑️ حذف</button>
                        </div>
                    </div>
                </div>
                <?php
            }
        }
        
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $html,
            'count' => WC()->cart->get_cart_contents_count(),
            'total' => wc_price(WC()->cart->get_total()),
            'checkout_url' => wc_get_checkout_url(),
        ));
    }

    public function ajax_update_quantity() {
        check_ajax_referer('sf_nonce', 'nonce');
        
        $cart_key = sanitize_text_field($_POST['cart_key'] ?? '');
        $quantity = intval($_POST['quantity'] ?? 1);
        
        if ($quantity < 1) {
            $quantity = 1;
        }
        
        if (!empty($cart_key) && isset(WC()->cart->get_cart()[$cart_key])) {
            WC()->cart->set_quantity($cart_key, $quantity, true);
            WC()->cart->calculate_totals();
            wp_send_json_success(array(
                'count' => WC()->cart->get_cart_contents_count(),
                'total' => wc_price(WC()->cart->get_total()),
            ));
        }
        
        wp_send_json_error();
    }

    public function ajax_remove_item() {
        check_ajax_referer('sf_nonce', 'nonce');
        
        $cart_key = sanitize_text_field($_POST['cart_key'] ?? '');
        
        if (!empty($cart_key) && isset(WC()->cart->get_cart()[$cart_key])) {
            WC()->cart->remove_cart_item($cart_key);
            WC()->cart->calculate_totals();
            wp_send_json_success(array(
                'count' => WC()->cart->get_cart_contents_count(),
                'total' => wc_price(WC()->cart->get_total()),
            ));
        }
        
        wp_send_json_error();
    }

    public function ajax_empty_cart() {
        check_ajax_referer('sf_nonce', 'nonce');
        WC()->cart->empty_cart();
        wp_send_json_success();
    }

    public function cart_fragments($fragments) {
        ob_start();
        ?>
        <span class="sf-drawer-floating-count"><?php echo WC()->cart->get_cart_contents_count(); ?></span>
        <?php
        $fragments['.sf-drawer-floating-count'] = ob_get_clean();
        
        return $fragments;
    }
}

Sofre_Drawer::instance();