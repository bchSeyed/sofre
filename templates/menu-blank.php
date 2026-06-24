<?php
/**
 * قالب تمام‌صفحه منوی سفره — مستقل از قالب سایت
 */

if (!defined('ABSPATH')) {
    exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php wp_title('|', true, 'right'); bloginfo('name'); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class('sf-standalone-page'); ?>>
<?php wp_body_open(); ?>
<main class="sf-standalone-main" role="main">
<?php
while (have_posts()) {
    the_post();
    the_content();
}
?>
</main>
<?php wp_footer(); ?>
</body>
</html>