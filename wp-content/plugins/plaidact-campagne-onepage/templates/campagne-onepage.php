<?php
/**
 * Template one-page rendu par le plugin pour une campagne.
 *
 * @package plaidact-campagne-onepage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="site-main plaidact-campagne-onepage-template">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</main>
<?php
get_footer();
