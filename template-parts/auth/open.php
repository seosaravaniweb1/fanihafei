<?php
/**
 * آغاز سند برای صفحه‌ی ورود — بدون سربرگ و پاورقی.
 *
 * صفحه‌ی ورود نباید حواس کاربر را پرت کند: نه منو، نه سبد، نه پاورقی. فقط
 * همان یک کارت. چون get_header() کل ساختار سایت را می‌آورد، این صفحه سند
 * خودش را باز می‌کند و در پایان با template-parts/auth/close آن را می‌بندد.
 *
 * wp_head و wp_body_open سر جایشان می‌مانند تا استایل، اسکریپت و هر افزونه‌ای
 * که به آن‌ها وصل است کار کند.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'fs-authpage' ); ?>>
<?php wp_body_open(); ?>

<main class="fs-authpage__main" id="fs-main">
