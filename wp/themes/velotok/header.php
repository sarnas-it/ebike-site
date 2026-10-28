<?php
/**
 * Шапка документа.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#12211a">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="sr-only" href="#main"><?php esc_html_e( 'Перейти к основному содержанию', 'velotok' ); ?></a>
<?php get_template_part( 'template-parts/header-bar' ); ?>
