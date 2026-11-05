<?php
/**
 * Блок заявки и контактов.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

$velotok_phone   = velotok_get_option( 'phone' );
$velotok_email   = velotok_get_option( 'email' );
$velotok_address = velotok_get_option( 'address' );
$velotok_hours   = velotok_get_option( 'hours' );
$velotok_has_cf7 = shortcode_exists( 'contact-form-7' );
?>
<section class="section section--lead" id="lead" aria-labelledby="lead-title">
	<div class="wrap">
		<div class="lead-grid">
			<div class="lead-copy">
				<p class="label">06 — <?php esc_html_e( 'Заявка', 'velotok' ); ?></p>
				<h2 id="lead-title"><?php esc_html_e( 'Приезжайте', 'velotok' ); ?> <em class="serif"><?php esc_html_e( 'прокатиться', 'velotok' ); ?></em></h2>
				<p><?php esc_html_e( 'Покажем модели вживую, подберём раму по росту и дадим проехать по району. Запись занимает пару минут.', 'velotok' ); ?></p>
				<div class="contact-list">
					<a href="<?php echo esc_url( velotok_phone_href() ); ?>"><span><?php esc_html_e( 'Телефон', 'velotok' ); ?></span><?php echo esc_html( $velotok_phone ); ?></a>
					<a href="<?php echo esc_url( 'mailto:' . $velotok_email ); ?>"><span><?php esc_html_e( 'Почта', 'velotok' ); ?></span><?php echo esc_html( $velotok_email ); ?></a>
					<address><span><?php esc_html_e( 'Шоурум', 'velotok' ); ?></span><?php echo esc_html( $velotok_address ); ?></address>
					<address><span><?php esc_html_e( 'Часы работы', 'velotok' ); ?></span><?php echo esc_html( $velotok_hours ); ?></address>
				</div>
			</div>
			<div class="lead-form-wrap">
				<h3><?php esc_html_e( 'Оставьте заявку на тест-драйв', 'velotok' ); ?></h3>
				<p class="form-sub"><?php esc_html_e( 'Перезвоним в течение рабочего дня и согласуем удобное время.', 'velotok' ); ?></p>

				<?php if ( $velotok_has_cf7 ) : ?>
					<div class="velotok-form">
						<?php echo do_shortcode( '[contact-form-7 title="test-drive"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- разметку формирует Contact Form 7. ?>
					</div>
					<details class="lead-consult">
						<summary><?php esc_html_e( 'Нужна консультация вместо тест-драйва?', 'velotok' ); ?></summary>
						<div class="velotok-form">
							<?php echo do_shortcode( '[contact-form-7 title="consultation"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- разметку формирует Contact Form 7. ?>
						</div>
					</details>
				<?php else : ?>
					<div class="velotok-form-unavailable" role="status">
						<p><?php esc_html_e( 'Форма заявки временно недоступна. Позвоните или напишите нам — поможем выбрать модель и запишем на тест-драйв.', 'velotok' ); ?></p>
						<p>
							<a class="btn btn-dark" href="<?php echo esc_url( velotok_phone_href() ); ?>"><?php echo esc_html( $velotok_phone ); ?></a>
							<a class="text-link" href="<?php echo esc_url( 'mailto:' . $velotok_email ); ?>"><?php echo esc_html( $velotok_email ); ?></a>
						</p>
						<p class="form-note"><?php esc_html_e( 'Для работы форм установите и настройте Contact Form 7 с формами «consultation» и «test-drive».', 'velotok' ); ?></p>
					</div>
				<?php endif; ?>

				<p class="form-note"><?php esc_html_e( 'Отправляя заявку, вы соглашаетесь на обработку персональных данных.', 'velotok' ); ?></p>
			</div>
		</div>
	</div>
</section>
