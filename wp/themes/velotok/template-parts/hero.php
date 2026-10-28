<?php
/**
 * Первый экран.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

$velotok_models = velotok_get_home_models();
$velotok_offer  = ! empty( $velotok_models[0] ) ? $velotok_models[0] : null;
$velotok_range  = ( $velotok_offer && ! empty( $velotok_offer['specs'][0][1] ) ) ? $velotok_offer['specs'][0][1] : '';
$velotok_price  = $velotok_offer ? wp_strip_all_tags( $velotok_offer['price_html'] ) : '';
?>
<section class="hero" id="top" aria-labelledby="hero-title">
	<img class="hero-bg" src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/hero-city-1-web.jpg' ); ?>" width="1536" height="864" alt="<?php esc_attr_e( 'Городской электровелосипед кремово-оливкового цвета на мощёной улице на закате', 'velotok' ); ?>" fetchpriority="high" decoding="async">
	<div class="hero-shade" aria-hidden="true"></div>
	<div class="hero-inner">
		<p class="eyebrow"><?php echo esc_html( velotok_get_option( 'hero_eyebrow' ) ); ?></p>
		<h1 id="hero-title"><?php echo esc_html( velotok_get_option( 'hero_title' ) ); ?><br><em><?php echo esc_html( velotok_get_option( 'hero_title_accent' ) ); ?></em></h1>
		<p class="hero-lead"><?php echo esc_html( velotok_get_option( 'hero_lead' ) ); ?></p>
		<div class="hero-actions">
			<a class="btn btn-primary" href="<?php echo esc_url( home_url( '/#models' ) ); ?>">
				<?php echo esc_html( velotok_get_option( 'hero_primary_label' ) ); ?>
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
			</a>
			<a class="btn btn-ghost" href="<?php echo esc_url( home_url( '/#lead' ) ); ?>"><?php echo esc_html( velotok_get_option( 'hero_secondary_label' ) ); ?></a>
		</div>
		<dl class="hero-stats">
			<div><dt><?php esc_html_e( 'Типы моделей', 'velotok' ); ?></dt><dd>4</dd></div>
			<div><dt><?php esc_html_e( 'Запас хода', 'velotok' ); ?></dt><dd><?php esc_html_e( '20–120 км', 'velotok' ); ?></dd></div>
			<div><dt><?php esc_html_e( 'Гарантия', 'velotok' ); ?></dt><dd><?php esc_html_e( '2 года', 'velotok' ); ?></dd></div>
		</dl>
	</div>
	<?php if ( $velotok_offer ) : ?>
		<a class="hero-offer" href="<?php echo esc_url( '#' . $velotok_offer['card_id'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: model name, 2: price. */ __( 'Хит сезона — электровелосипед %1$s, %2$s', 'velotok' ), $velotok_offer['name'], $velotok_price ) ); ?>">
			<span class="offer-label"><?php esc_html_e( 'Хит сезона', 'velotok' ); ?></span>
			<span class="offer-name"><?php echo esc_html( $velotok_offer['name'] ); ?></span>
			<span class="offer-price"><?php echo esc_html( $velotok_price . ( $velotok_range ? ' · ' . $velotok_range : '' ) ); ?></span>
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M8 7h9v9"/></svg>
		</a>
	<?php endif; ?>
</section>

<div class="ticker" aria-hidden="true">
	<div class="ticker-track">
		<?php for ( $velotok_i = 0; $velotok_i < 2; $velotok_i++ ) : ?>
			<div class="ticker-group">
				<span><?php esc_html_e( 'Бесплатный тест-драйв', 'velotok' ); ?></span>
				<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.7 2 5 14.2h5.6L9.2 22 18.8 9.4h-5.7L13.7 2Z"/></svg>
				<span><?php esc_html_e( 'Своя мастерская', 'velotok' ); ?></span>
				<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.7 2 5 14.2h5.6L9.2 22 18.8 9.4h-5.7L13.7 2Z"/></svg>
				<span><?php esc_html_e( 'Рассрочка 0%', 'velotok' ); ?></span>
				<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.7 2 5 14.2h5.6L9.2 22 18.8 9.4h-5.7L13.7 2Z"/></svg>
				<span><?php esc_html_e( 'Доставка в 340 городов', 'velotok' ); ?></span>
				<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.7 2 5 14.2h5.6L9.2 22 18.8 9.4h-5.7L13.7 2Z"/></svg>
				<span><?php esc_html_e( 'Гарантия 2 года', 'velotok' ); ?></span>
				<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.7 2 5 14.2h5.6L9.2 22 18.8 9.4h-5.7L13.7 2Z"/></svg>
			</div>
		<?php endfor; ?>
	</div>
</div>
