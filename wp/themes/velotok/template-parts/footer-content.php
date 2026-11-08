<?php
/**
 * Содержимое подвала.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

$velotok_phone   = velotok_get_option( 'phone' );
$velotok_email   = velotok_get_option( 'email' );
$velotok_address = velotok_get_option( 'address' );
$velotok_hours   = velotok_get_option( 'hours' );
?>
<div class="wrap">
	<div class="footer-top">
		<div class="footer-brand">
			<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php esc_attr_e( 'ВЕЛОТОК — на главную', 'velotok' ); ?>">
				<svg class="logo-mark" viewBox="0 0 40 40" aria-hidden="true">
					<circle cx="20" cy="20" r="19" fill="#d8f26a"/>
					<path d="M22.6 8.5 13 22.1h6.2l-1.8 9.4L27.2 17h-6.3l1.7-8.5Z" fill="#12211a"/>
				</svg>
				<span class="logo-text"><?php echo esc_html( get_bloginfo( 'name' ) ); ?><small><?php esc_html_e( 'электровелосипеды', 'velotok' ); ?></small></span>
			</a>
			<p><?php esc_html_e( 'Магазин и мастерская электровелосипедов. Помогаем выбрать технику под маршрут, а не под красивую цифру в характеристиках.', 'velotok' ); ?></p>
		</div>

		<div class="footer-col">
			<h3><?php esc_html_e( 'Каталог', 'velotok' ); ?></h3>
			<a href="<?php echo esc_url( velotok_category_url( 'city' ) ); ?>"><?php esc_html_e( 'Городские', 'velotok' ); ?></a>
			<a href="<?php echo esc_url( velotok_category_url( 'mountain' ) ); ?>"><?php esc_html_e( 'Горные', 'velotok' ); ?></a>
			<a href="<?php echo esc_url( velotok_category_url( 'cargo' ) ); ?>"><?php esc_html_e( 'Грузовые', 'velotok' ); ?></a>
			<a href="<?php echo esc_url( velotok_category_url( 'folding' ) ); ?>"><?php esc_html_e( 'Складные', 'velotok' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/#models' ) ); ?>"><?php esc_html_e( 'Аксессуары', 'velotok' ); ?></a>
		</div>

		<div class="footer-col">
			<h3><?php esc_html_e( 'Покупателям', 'velotok' ); ?></h3>
			<?php
			wp_nav_menu( array(
				'theme_location' => 'footer',
				'container'      => false,
				'menu_class'     => 'footer-menu',
				'depth'          => 1,
				'fallback_cb'    => 'velotok_footer_menu_fallback',
			) );
			?>
		</div>

		<div class="footer-col">
			<h3><?php esc_html_e( 'Контакты', 'velotok' ); ?></h3>
			<a href="<?php echo esc_url( velotok_phone_href() ); ?>"><?php echo esc_html( $velotok_phone ); ?></a>
			<a href="<?php echo esc_url( 'mailto:' . $velotok_email ); ?>"><?php echo esc_html( $velotok_email ); ?></a>
			<address><?php echo esc_html( $velotok_address ); ?></address>
			<address><?php echo esc_html( $velotok_hours ); ?></address>
		</div>
	</div>

	<div class="footer-bottom">
		<p class="footer-legal"><?php echo wp_kses_post( velotok_get_option( 'legal' ) ); ?></p>
		<p><?php echo esc_html( velotok_get_option( 'footer_note' ) ); ?></p>
	</div>
</div>
