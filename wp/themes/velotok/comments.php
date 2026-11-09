<?php
/**
 * Комментарии к статье.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="comments-area">
	<?php if ( have_comments() ) : ?>
		<h2 class="comments-title">
			<?php
			$velotok_comment_count = (int) get_comments_number();

			printf(
				/* translators: %s: количество комментариев. */
				esc_html( _n( '%s комментарий', '%s комментариев', $velotok_comment_count, 'velotok' ) ),
				esc_html( number_format_i18n( $velotok_comment_count ) )
			);
			?>
		</h2>

		<ol class="comment-list">
			<?php
			wp_list_comments( array(
				'style'       => 'ol',
				'short_ping'  => true,
				'avatar_size' => 48,
			) );
			?>
		</ol>

		<?php the_comments_navigation(); ?>

		<?php if ( ! comments_open() ) : ?>
			<p class="result-empty"><?php esc_html_e( 'Обсуждение закрыто.', 'velotok' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>

	<?php
	comment_form( array(
		'title_reply_before' => '<h2 id="reply-title" class="comment-reply-title">',
		'title_reply_after'  => '</h2>',
	) );
	?>
</section>
