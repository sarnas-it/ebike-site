<?php
/**
 * Блок преимуществ.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

$velotok_advantages = array(
	array(
		'title' => __( 'Собираем и проверяем каждую модель', 'velotok' ),
		'text'  => __( 'Протягиваем крепёж, настраиваем тормоза и трансмиссию, делаем пробег 3 км до отправки.', 'velotok' ),
	),
	array(
		'title' => __( 'Тест-драйв по вашему маршруту', 'velotok' ),
		'text'  => __( '30 минут по знакомым улицам или ближайшему парку. Понравится — оформим на месте.', 'velotok' ),
	),
	array(
		'title' => __( 'Своя мастерская, а не «партнёры»', 'velotok' ),
		'text'  => __( 'Диагностика батареи, ремонт и апгрейд — у нас, по записи, обычно со сроком от одного дня.', 'velotok' ),
	),
	array(
		'title' => __( 'Доставка в 340 городов', 'velotok' ),
		'text'  => __( 'Собранный велосипед едет в жёсткой коробке. В Москве и Петербурге — на следующий день.', 'velotok' ),
	),
);
?>
<section class="section section--dark" id="advantages" aria-labelledby="adv-title">
	<div class="wrap adv-grid">
		<div class="adv-intro">
			<p class="label">03 — <?php esc_html_e( 'Почему мы', 'velotok' ); ?></p>
			<h2 id="adv-title"><?php esc_html_e( 'Велосипед — это', 'velotok' ); ?> <em class="serif"><?php esc_html_e( 'не только', 'velotok' ); ?></em> <?php esc_html_e( 'рама и мотор', 'velotok' ); ?></h2>
			<p><?php esc_html_e( 'Сервис, сборка и честный разговор о том, что подойдёт именно вам. Без «лучшего предложения на рынке» и мелкого шрифта.', 'velotok' ); ?></p>
			<a class="btn btn-primary" href="<?php echo esc_url( home_url( '/#lead' ) ); ?>"><?php esc_html_e( 'Записаться в шоурум', 'velotok' ); ?>
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
			</a>
		</div>
		<div class="adv-list">
			<?php foreach ( $velotok_advantages as $velotok_index => $velotok_item ) : ?>
				<div class="adv-item">
					<span class="adv-num"><?php echo esc_html( sprintf( '%02d', $velotok_index + 1 ) ); ?></span>
					<div>
						<h3><?php echo esc_html( $velotok_item['title'] ); ?></h3>
						<p><?php echo esc_html( $velotok_item['text'] ); ?></p>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
