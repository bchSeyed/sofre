<?php
/**
 * کلاس ورود با OTP - فرم ورود/ثبت‌نام با شماره موبایل
 * پشتیبانی از افزونه Digits برای ارسال SMS
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sofre_OTP_Login {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_shortcode('sofre_otp', array($this, 'render_otp_form'));
        
        // Ajax handlers
        add_action('wp_ajax_nopriv_sf_otp_send_code', array($this, 'ajax_send_otp_code'));
        add_action('wp_ajax_nopriv_sf_otp_verify_code', array($this, 'ajax_verify_otp_code'));
        add_action('wp_ajax_nopriv_sf_otp_login', array($this, 'ajax_otp_login'));
        
        // حذف لاگین ووکامرس از checkout وقتی OTP فعاله
        add_action('wp_enqueue_scripts', array($this, 'frontend_assets'));
        
        // نمایش فرم OTP در checkout
        add_action('woocommerce_before_checkout_form', array($this, 'maybe_show_otp_before_checkout'), 5);
    }

    public function frontend_assets() {
        if (!is_checkout() && !is_account_page()) {
            return;
        }
        
        wp_add_inline_style('sf-frontend', $this->get_otp_styles());
        wp_add_inline_script('sf-frontend', $this->get_otp_script(), 'before');
    }

    private function get_otp_styles() {
        return '
        .sf-otp-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.6);
            z-index: 99998;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sf-otp-drawer {
            background: #fff;
            border-radius: 20px 20px 0 0;
            padding: 30px 24px;
            max-width: 420px;
            width: 100%;
            position: fixed;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            z-index: 99999;
            box-shadow: 0 -8px 40px rgba(0,0,0,0.2);
            direction: rtl;
            text-align: center;
        }
        .sf-otp-drawer h2 {
            font-size: 20px;
            color: #1d1a18;
            margin-bottom: 8px;
        }
        .sf-otp-drawer p {
            font-size: 14px;
            color: #555;
            margin-bottom: 24px;
        }
        .sf-otp-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            text-align: center;
            margin-bottom: 16px;
            direction: ltr;
            transition: border-color 0.2s;
        }
        .sf-otp-input:focus {
            border-color: #036666;
            outline: none;
        }
        .sf-otp-btn {
            width: 100%;
            padding: 14px;
            background: #036666;
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .sf-otp-btn:hover {
            background: #024d4d;
        }
        .sf-otp-btn:disabled {
            background: #999;
            cursor: not-allowed;
        }
        .sf-otp-error {
            color: #f44336;
            font-size: 13px;
            margin-top: 8px;
            display: none;
        }
        .sf-otp-success {
            color: #4CAF50;
            font-size: 13px;
            margin-top: 8px;
            display: none;
        }
        .sf-otp-loader {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: sf-spin 0.6s linear infinite;
            margin: 0 auto;
        }
        .sf-otp-alternate {
            margin-top: 16px;
            font-size: 13px;
            color: #888;
        }
        .sf-otp-alternate a {
            color: #036666;
            text-decoration: underline;
            cursor: pointer;
        }
        .sf-otp-back {
            display: block;
            margin-top: 12px;
            color: #888;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
        }
        .sf-otp-back:hover {
            color: #555;
        }
        .sf-otp-input-group {
            display: flex;
            gap: 8px;
            direction: ltr;
            justify-content: center;
            margin-bottom: 16px;
        }
        .sf-otp-digit {
            width: 48px;
            height: 56px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 24px;
            text-align: center;
            transition: border-color 0.2s;
            font-family: monospace;
        }
        .sf-otp-digit:focus {
            border-color: #036666;
            outline: none;
        }
        .sf-otp-resend {
            font-size: 13px;
            color: #036666;
            cursor: pointer;
            margin-top: 8px;
            display: inline-block;
        }
        .sf-otp-resend:disabled {
            color: #999;
            cursor: not-allowed;
        }
        ';
    }

    private function get_otp_script() {
        return '
        jQuery(document).ready(function($) {
            // ارسال درخواست کد OTP
            $(document).on("click", ".sf-otp-send-btn", function() {
                var phone = $("#sf-otp-phone").val();
                var btn = $(this);
                var loader = btn.siblings(".sf-otp-loader");
                var error = $("#sf-otp-error");
                
                error.hide();
                btn.prop("disabled", true);
                loader.show();
                
                $.post(sf_ajax.ajax_url, {
                    action: "sf_otp_send_code",
                    phone: phone,
                    nonce: sf_ajax.nonce
                }, function(response) {
                    loader.hide();
                    if (response.success) {
                        $("#sf-otp-step-1").hide();
                        $("#sf-otp-step-2").show();
                        $("#sf-otp-phone-display").text(phone);
                        startResendTimer();
                    } else {
                        btn.prop("disabled", false);
                        error.text(response.data.message || "خطا در ارسال کد").show();
                    }
                }).fail(function() {
                    loader.hide();
                    btn.prop("disabled", false);
                    error.text("خطا در ارتباط با سرور").show();
                });
            });
            
            // تایید کد OTP
            $(document).on("click", ".sf-otp-verify-btn", function() {
                var code = "";
                $(".sf-otp-digit").each(function() {
                    code += $(this).val();
                });
                var phone = $("#sf-otp-phone").val();
                var btn = $(this);
                var loader = btn.siblings(".sf-otp-loader");
                var error = $("#sf-otp-error-2");
                
                error.hide();
                btn.prop("disabled", true);
                loader.show();
                
                $.post(sf_ajax.ajax_url, {
                    action: "sf_otp_verify_code",
                    phone: phone,
                    code: code,
                    nonce: sf_ajax.nonce
                }, function(response) {
                    loader.hide();
                    if (response.success) {
                        $("#sf-otp-success-2").text("ورود موفق. در حال انتقال...").show();
                        window.location.reload();
                    } else {
                        btn.prop("disabled", false);
                        error.text(response.data.message || "کد وارد شده اشتباه است").show();
                    }
                }).fail(function() {
                    loader.hide();
                    btn.prop("disabled", false);
                    error.text("خطا در ارتباط با سرور").show();
                });
            });
            
            // حرکت خودکار بین فیلدهای OTP
            $(document).on("input", ".sf-otp-digit", function() {
                if ($(this).val().length > 0) {
                    $(this).next(".sf-otp-digit").focus();
                }
            });
            
            $(document).on("keydown", ".sf-otp-digit", function(e) {
                if (e.key === "Backspace" && $(this).val().length === 0) {
                    $(this).prev(".sf-otp-digit").focus();
                }
            });
            
            // ارسال مجدد کد
            $(document).on("click", ".sf-otp-resend:not(:disabled)", function() {
                $("#sf-otp-step-2").hide();
                $("#sf-otp-step-1").show();
                $(".sf-otp-send-btn").prop("disabled", false);
            });
            
            function startResendTimer() {
                var timeLeft = 120;
                $(".sf-otp-resend").prop("disabled", true);
                var timer = setInterval(function() {
                    timeLeft--;
                    $(".sf-otp-resend-text").text("ارسال مجدد تا " + timeLeft + " ثانیه دیگر");
                    if (timeLeft <= 0) {
                        clearInterval(timer);
                        $(".sf-otp-resend").prop("disabled", false);
                        $(".sf-otp-resend-text").text("ارسال مجدد کد");
                    }
                }, 1000);
            }
            
            // بستن فرم OTP
            $(document).on("click", ".sf-otp-overlay", function() {
                $(this).remove();
                $(".sf-otp-drawer").remove();
            });
            
            // لینک ورود با گذرواژه
            $(document).on("click", ".sf-otp-password-link", function() {
                $(".sf-otp-drawer, .sf-otp-overlay").remove();
                $(".woocommerce-form-login").show();
            });
        });
        ';
    }

    public function render_otp_form() {
        if (is_user_logged_in()) {
            return '<div style="text-align:center;padding:20px;">شما قبلاً وارد شده‌اید.</div>';
        }
        
        ob_start();
        ?>
        <div class="sf-otp-overlay"></div>
        <div class="sf-otp-drawer">
            <h2>ورود / ثبت‌نام</h2>
            <p>شماره موبایل خود را وارد کنید</p>
            
            <!-- مرحله ۱: وارد کردن شماره موبایل -->
            <div id="sf-otp-step-1">
                <input type="tel" id="sf-otp-phone" class="sf-otp-input" 
                       placeholder="۰۹۱۲۳۴۵۶۷۸۹" maxlength="11" dir="ltr" 
                       value="<?php echo isset($_GET['phone']) ? esc_attr($_GET['phone']) : ''; ?>">
                <button class="sf-otp-btn sf-otp-send-btn">
                    <span class="sf-otp-btn-text">ارسال کد تأیید</span>
                    <span class="sf-otp-loader"></span>
                </button>
                <div id="sf-otp-error" class="sf-otp-error"></div>
                
                <?php if (class_exists('WooCommerce')): ?>
                <div class="sf-otp-alternate">
                    <a class="sf-otp-password-link">ورود با نام کاربری و گذرواژه</a>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- مرحله ۲: وارد کردن کد تأیید -->
            <div id="sf-otp-step-2" style="display:none;">
                <p>کد تأیید به شماره <strong id="sf-otp-phone-display"></strong> ارسال شد</p>
                <div class="sf-otp-input-group">
                    <input type="text" class="sf-otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input type="text" class="sf-otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input type="text" class="sf-otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input type="text" class="sf-otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                </div>
                <button class="sf-otp-btn sf-otp-verify-btn">
                    <span class="sf-otp-btn-text">تأیید کد</span>
                    <span class="sf-otp-loader"></span>
                </button>
                <div id="sf-otp-error-2" class="sf-otp-error"></div>
                <div id="sf-otp-success-2" class="sf-otp-success"></div>
                
                <div class="sf-otp-resend">
                    <span class="sf-otp-resend-text">ارسال مجدد کد</span>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function maybe_show_otp_before_checkout() {
        if (is_user_logged_in()) {
            return;
        }
        
        ?>
        <div class="sf-checkout-login-prompt" style="background:#f8f8f8;padding:20px;border-radius:12px;margin-bottom:24px;text-align:center;">
            <p style="margin:0 0 12px;font-size:15px;color:#555;">برای ادامه خرید، وارد شوید یا ثبت‌نام کنید</p>
            <button class="button sf-show-otp-btn" onclick="jQuery('#sf-otp-form-area').toggle();" 
                    style="background:#036666;color:#fff;padding:12px 30px;border:none;border-radius:25px;cursor:pointer;">
                ورود با شماره موبایل
            </button>
            <div id="sf-otp-form-area" style="display:none;margin-top:16px;">
                <?php echo do_shortcode('[sofre_otp]'); ?>
            </div>
        </div>
        <?php
    }

    public function ajax_send_otp_code() {
        check_ajax_referer('sf_nonce', 'nonce');
        
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        
        if (empty($phone)) {
            wp_send_json_error(array('message' => 'لطفاً شماره موبایل را وارد کنید.'));
        }
        
        // پشتیبانی از افزونه Digits
        if (function_exists('df_digits_send_code')) {
            $result = df_digits_send_code($phone);
            if ($result) {
                update_option('sf_otp_phone_' . $phone, time());
                wp_send_json_success(array('message' => 'کد تأیید ارسال شد.'));
            } else {
                // Digits failed, fallback to simple code
                $this->send_simple_otp($phone);
            }
        } else {
            // بدون Digits - کد ساده (فقط برای تست)
            $this->send_simple_otp($phone);
        }
    }

    private function send_simple_otp($phone) {
        $code = wp_rand(1000, 9999);
        $hashed = wp_hash($code . $phone . date('Y-m-d H'));
        
        set_transient('sf_otp_' . $hashed, array(
            'phone' => $phone,
            'code' => $code,
            'time' => time(),
        ), 120);
        
        update_option('sf_otp_pending_' . $phone, $hashed);
        
        // در محیط واقعی اینجا SMS ارسال می‌شود
        // فعلاً کد رو در لاگ ذخیره می‌کنیم برای تست
        error_log('Sofre OTP Code for ' . $phone . ': ' . $code);
        
        // اگر Digits نصب نباشه، کد رو برمی‌گردونیم (فقط برای دیباگ)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            wp_send_json_success(array(
                'message' => 'کد تأیید ارسال شد. (Debug: ' . $code . ')',
                'debug_code' => $code,
            ));
        } else {
            update_option('sf_otp_debug_' . $phone, $code);
            wp_send_json_success(array('message' => 'کد تأیید ارسال شد.'));
        }
    }

    public function ajax_verify_otp_code() {
        check_ajax_referer('sf_nonce', 'nonce');
        
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $code = sanitize_text_field($_POST['code'] ?? '');
        
        if (empty($phone) || empty($code)) {
            wp_send_json_error(array('message' => 'اطلاعات ناقص است.'));
        }
        
        // بررسی کد
        if (function_exists('df_digits_verify_code')) {
            // استفاده از تابع Digits
            $verified = df_digits_verify_code($phone, $code);
            if (!$verified) {
                wp_send_json_error(array('message' => 'کد وارد شده اشتباه است.'));
            }
        } else {
            // بررسی کد ساده
            $hashed = get_option('sf_otp_pending_' . $phone);
            if (!$hashed) {
                wp_send_json_error(array('message' => 'ابتدا درخواست کد دهید.'));
            }
            
            $stored = get_transient('sf_otp_' . $hashed);
            if (!$stored || $stored['phone'] !== $phone || $stored['code'] !== intval($code)) {
                // بررسی کد دیباگ
                $debug_code = get_option('sf_otp_debug_' . $phone);
                if ($debug_code != $code) {
                    wp_send_json_error(array('message' => 'کد وارد شده اشتباه است.'));
                }
            }
        }
        
        // ورود یا ثبت‌نام کاربر
        $user = get_user_by('login', $phone);
        if (!$user) {
            $user = get_user_by('email', $phone . '@temp.local');
        }
        
        if (!$user) {
            // ایجاد کاربر جدید
            $username = $phone;
            $password = wp_generate_password();
            $email = $phone . '@temp.local';
            
            $user_id = wp_insert_user(array(
                'user_login' => $username,
                'user_pass' => $password,
                'user_email' => $email,
                'display_name' => $phone,
                'first_name' => $phone,
                'role' => 'customer',
            ));
            
            if (is_wp_error($user_id)) {
                wp_send_json_error(array('message' => 'خطا در ایجاد حساب کاربری.'));
            }
            
            $user = get_user_by('ID', $user_id);
            
            // ذخیره شماره موبایل
            update_user_meta($user_id, 'billing_phone', $phone);
        }
        
        // ورود کاربر
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);
        
        // پاکسازی
        delete_option('sf_otp_pending_' . $phone);
        delete_transient('sf_otp_' . $hashed ?? '');
        delete_option('sf_otp_debug_' . $phone);
        
        wp_send_json_success(array(
            'message' => 'ورود موفق.',
            'redirect' => wc_get_checkout_url(),
        ));
    }
}

Sofre_OTP_Login::instance();