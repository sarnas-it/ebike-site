<?php
/**
 * Блок подбора модели.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="section section--picker" id="picker" aria-labelledby="picker-title">
	<div class="wrap picker-grid">
		<div class="picker-intro">
			<p class="label">04 — <?php esc_html_e( 'Подбор', 'velotok' ); ?></p>
			<h2 id="picker-title"><?php esc_html_e( 'Как выбрать', 'velotok' ); ?> <em class="serif"><?php esc_html_e( 'электровелосипед', 'velotok' ); ?></em></h2>
			<p><?php esc_html_e( 'Начните не с мощности мотора, а с маршрута. Три коротких вопроса помогут сузить выбор до одной модели — дальше можно приехать и прокатиться.', 'velotok' ); ?></p>
			<p class="picker-note"><?php esc_html_e( 'Если сомневаетесь между двумя вариантами, напишите нам. Сравним батареи, вес и стоимость обслуживания без давления на выбор.', 'velotok' ); ?></p>
		</div>

		<div class="quiz">
			<div class="quiz-q">
				<h3><span>01</span><?php esc_html_e( 'Где чаще будете ездить?', 'velotok' ); ?></h3>
				<div class="chips">
					<button class="chip" type="button" data-q="place" data-v="city" aria-pressed="false"><?php esc_html_e( 'Город', 'velotok' ); ?></button>
					<button class="chip" type="button" data-q="place" data-v="mountain" aria-pressed="false"><?php esc_html_e( 'Горы и лес', 'velotok' ); ?></button>
					<button class="chip" type="button" data-q="place" data-v="family" aria-pressed="false"><?php esc_html_e( 'Дача с детьми', 'velotok' ); ?></button>
					<button class="chip" type="button" data-q="place" data-v="commute" aria-pressed="false"><?php esc_html_e( 'Дорога на работу', 'velotok' ); ?></button>
				</div>
			</div>
			<div class="quiz-q">
				<h3><span>02</span><?php esc_html_e( 'Что важнее всего?', 'velotok' ); ?></h3>
				<div class="chips">
					<button class="chip" type="button" data-q="need" data-v="range" aria-pressed="false"><?php esc_html_e( 'Запас хода', 'velotok' ); ?></button>
					<button class="chip" type="button" data-q="need" data-v="cargo" aria-pressed="false"><?php esc_html_e( 'Перевозить груз', 'velotok' ); ?></button>
					<button class="chip" type="button" data-q="need" data-v="compact" aria-pressed="false"><?php esc_html_e( 'Компактность', 'velotok' ); ?></button>
					<button class="chip" type="button" data-q="need" data-v="speed" aria-pressed="false"><?php esc_html_e( 'Скорость', 'velotok' ); ?></button>
				</div>
			</div>
			<div class="quiz-q">
				<h3><span>03</span><?php esc_html_e( 'Какой бюджет комфортен?', 'velotok' ); ?></h3>
				<div class="chips">
					<button class="chip" type="button" data-q="budget" data-v="low" aria-pressed="false"><?php esc_html_e( 'до 80 000 ₽', 'velotok' ); ?></button>
					<button class="chip" type="button" data-q="budget" data-v="mid" aria-pressed="false"><?php esc_html_e( '80–120 000 ₽', 'velotok' ); ?></button>
					<button class="chip" type="button" data-q="budget" data-v="high" aria-pressed="false"><?php esc_html_e( 'от 120 000 ₽', 'velotok' ); ?></button>
				</div>
			</div>
			<div class="quiz-result" id="quiz-result" aria-live="polite" aria-atomic="true">
				<p class="result-empty"><?php esc_html_e( 'Ответьте на три вопроса — покажем модель, которая подходит под ваш маршрут и бюджет.', 'velotok' ); ?></p>
			</div>
			<button class="quiz-reset" type="button" id="quiz-reset" hidden><?php esc_html_e( 'Сбросить ответы', 'velotok' ); ?></button>
		</div>
	</div>
</section>
