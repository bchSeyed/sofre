<?php
/**
 * قالب مستقل صفحه منو — بدون وابستگی به قالب وردپرس
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sofre_Template {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_filter('template_include', array($this, 'load_menu_template'), 99);
        add_filter('body_class', array($this, 'body_class'));
        add_action('wp_enqueue_scripts', array($this, 'standalone_styles'), 30);
    }

    public function load_menu_template($template) {
        if (!Sofre_Plugin::is_menu_context()) {
            return $template;
        }

        $plugin_template = SF_PATH . 'templates/menu-blank.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }

        return $template;
    }

    public function body_class($classes) {
        if (Sofre_Plugin::is_menu_context()) {
            $classes[] = 'sf-standalone-page';
        }
        return $classes;
    }

    public function standalone_styles() {
        if (!Sofre_Plugin::is_menu_context()) {
            return;
        }

        wp_add_inline_style('sf-frontend', '
            html, body.sf-standalone-page {
                margin: 0 !important;
                padding: 0 !important;
                background: var(--sf-bg, #1d1a18) !important;
                overflow-x: hidden;
            }
            body.sf-standalone-page .sf-standalone-main {
                min-height: 100vh;
                width: 100%;
            }
            body.sf-standalone-page #wpadminbar {
                display: none;
            }
            body.sf-standalone-page.admin-bar .sf-menu-page {
                margin-top: 0 !important;
            }
        ');
    }
}

Sofre_Template::instance();