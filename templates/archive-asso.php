<?php

use Plaidact\CampaignCore\Association_Directory as Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="site-content" class="plaidact-asso-archive">
	<header class="plaidact-asso-header">
		<h1><?php post_type_archive_title(); ?></h1>
		<?php if ( term_description() ) : ?>
			<div class="plaidact-asso-intro"><?php echo wp_kses_post( term_description() ); ?></div>
		<?php endif; ?>
	</header>
	<?php Plugin::render_template( 'asso-directory-loop.php', [ 'posts_per_page' => 9 ] ); ?>
</main>
<?php get_footer(); ?>
