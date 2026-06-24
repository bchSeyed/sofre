# AGENTS.md

## What this is

**Boshqab (بشقاب)** — WordPress plugin (requires WooCommerce) for restaurant menu display and order management. Persian/Farsi UI, RTL layout. All user-facing text is in Farsi.

## Architecture

- **Main file**: `boshqab.php` — singleton `Boshqab_Plugin` class, bootstrap, admin pages, AJAX handlers, order status registration
- **Includes**: Manual `require_once` in `includes/()` method (no autoloader, no Composer)
- **No build step**: Raw PHP/JS/CSS. No package.json, no webpack, no transpilation.
- **Prefix**: All WP options use `bq_` prefix. All JS localized data uses `bq_ajax`.
- **Nonce**: Single nonce `bq_nonce` used across all AJAX handlers (`wp_create_nonce('bq_nonce')` / `check_ajax_referer('bq_nonce', 'nonce')`)

## File structure

```
boshqab.php          Main plugin class (admin, AJAX, statuses, pages)
includes/class-menu.php     [boshqab_menu] shortcode — renders restaurant menu
includes/class-frontend.php WooCommerce hooks: order status column, checkout info
includes/class-orders.php   Custom order statuses in WC, order tracking, admin metabox
includes/class-otp-login.php [boshqab_otp] shortcode — phone OTP login (optional Digits plugin)
includes/class-drawer.php   Sliding cart drawer (AJAX-powered)
includes/class-services.php Delivery/Pickup/Serving selector, fee calculation
includes/class-shipping.php Registers 3 WooCommerce shipping methods
includes/shipping/          WC_Shipping_Method subclasses (motorcycle, pickup, serving)
assets/admin.js             Admin dashboard JS (toggle, order polling, status change)
assets/admin.css            Admin styles
assets/frontend.js          Menu page JS (load categories, add-to-cart via AJAX)
assets/frontend.css         Menu page styles
assets/fonts/               Custom fonts
assets/images/              Static images
```

## Shortcodes

- `[boshqab_menu]` — full restaurant menu page (creates a WP page on activation)
- `[boshqab_otp]` — OTP phone login form
- `[boshqab_services]` — delivery method selector

## Custom order statuses (WooCommerce)

`bq-pending` → `bq-preparing` → `bq-ready` → `bq-delivering` → `bq-delivered`

Registered via both `register_post_status()` in main class and `wc_order_statuses` filter in `class-orders.php`.

## Key gotchas

- **Persian week starts Saturday** — business hours day keys are `saturday`, `sunday`, ..., `friday` (Friday is closed by default)
- **OTP fallback**: Without the Digits plugin, OTP codes are logged via `error_log()` and stored in WP options (`bq_otp_debug_*`) for testing. Never ship this to production without an SMS provider.
- **Options stored in two ways**: `bq_business_hours` is serialized array; most other settings are individual `bq_*` options.
- **No `.gitignore`** — watch for accidentally committing large assets or debug files
- **No tests, no lint, no CI** — manual verification only
- **Single commit repo** — no branch history to reference

## Conventions

- PHP: WordPress coding style, no namespaces, singleton pattern on all classes
- JS: jQuery only (no framework), inline scripts in PHP files for OTP/drawer/services
- CSS: Inline styles in PHP for OTP drawer, cart drawer, services; separate CSS files for admin and frontend menu
- All AJAX endpoints require `bq_nonce` verification
- Version bump: update `BQ_VERSION` constant in `boshqab.php` and the plugin header
