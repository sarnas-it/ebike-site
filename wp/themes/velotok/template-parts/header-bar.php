<?php
/**
 * Верхняя полоса и основная шапка.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

$velotok_phone = velotok_get_option( 'phone' );
?>
<div class="topbar">
	<div class="wrap topbar-inner">
		<p><?php echo esc_html( velotok_get_option( 'topbar_text' ) ); ?></p>
		<div class="topbar-right">
			<a href="<?php echo esc_url( velotok_phone_href() ); ?>"><?php echo esc_html( $velotok_phone ); ?></a>
			<span><?php echo esc_html( velotok_get_option( 'topbar_note' ) ); ?></span>
		</div>
	</div>
</div>

<header class="site-header">
	<div class="wrap header-inner">
		<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php esc_attr_e( 'ВЕЛОТОК — на главную', 'velotok' ); ?>">
			<svg class="logo-mark" viewBox="0 0 40 40" aria-hidden="true">
				<circle cx="20" cy="20" r="19" fill="#12211a"/>
				<path d="M22.6 8.5 13 22.1h6.2l-1.8 9.4L27.2 17h-6.3l1.7-8.5Z" fill="#d8f26a"/>
			</svg>
			<span class="logo-text"><?php echo esc_html( get_bloginfo( 'name' ) ); ?><small><?php esc_html_e( 'электровелосипеды', 'velotok' ); ?></small></span>
		</a>

		<nav class="nav" id="main-nav" aria-label="<?php esc_attr_e( 'Основная навигация', 'velotok' ); ?>">
			<?php
			wp_nav_menu( array(
				'theme_location' => 'primary',
				'container'      => false,
				'items_wrap'     => '%3$s',
				'depth'          => 1,
				'fallback_cb'    => 'velotok_primary_menu_fallback',
			) );
			?>
		</nav>

		<div class="header-actions">
			<a class="header-phone" href="<?php echo esc_url( velotok_phone_href() ); ?>"><?php echo esc_html( $velotok_phone ); ?></a>
			<a class="cart-btn" href="<?php echo esc_url( velotok_cart_url() ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: количество товаров. */ __( 'Корзина, товаров: %s', 'velotok' ), (string) velotok_cart_count() ) ); ?>">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<path d="M3 4h2l2.2 10.3a2 2 0 0 0 2 1.7h7.7a2 2 0 0 0 2-1.6L20.5 8H6.1"/>
					<circle cx="10" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/>
				</svg>
				<?php echo velotok_cart_count_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- разметка экранируется внутри функции. ?>
			</a>
			<button class="menu-btn" id="menu-toggle" type="button" aria-expanded="false" aria-controls="main-nav" aria-label="<?php esc_attr_e( 'Открыть меню', 'velotok' ); ?>">
				<span></span><span></span><span></span>
			</button>
		</div>
	</div>
</header>
