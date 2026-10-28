<?php
/**
 * Главная страница.
 *
 * @package Velotok
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main">
	<?php
	get_template_part( 'template-parts/hero' );
	get_template_part( 'template-parts/categories' );
	get_template_part( 'template-parts/models' );
	get_template_part( 'template-parts/advantages' );
	get_template_part( 'template-parts/picker' );
	get_template_part( 'template-parts/reviews' );
	get_template_part( 'template-parts/lead-form' );
	?>
</main>
<?php
get_footer();
