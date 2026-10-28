<?php
/**
 * Страница 404.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="page-main">
	<section class="section error-404">
		<div class="wrap">
			<p class="label">404</p>
			<h1><?php esc_html_e( 'Такой страницы нет', 'velotok' ); ?></h1>
			<p class="result-note"><?php esc_html_e( 'Возможно, ссылка устарела или в адресе опечатка. Вернитесь на главную — там все модели, контакты и запись на тест-драйв.', 'velotok' ); ?></p>
			<a class="btn btn-dark" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'На главную', 'velotok' ); ?>
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
			</a>
		</div>
	</section>
</main>
<?php
get_footer();
