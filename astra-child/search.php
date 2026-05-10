<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$search_query = get_search_query();
$paged        = max( 1, (int) get_query_var( 'paged' ) );

$query = new WP_Query( array(
	'post_type'      => 'post',
	'posts_per_page' => 24,
	'paged'          => $paged,
	's'              => $search_query,
) );

get_header();
?>
<div class="nsw-archive-page ast-container">
	<?php if ( $search_query ) : ?>
	<h1 class="nsw-archive-heading">
		Search results for: <span style="color:#0073aa;"><?php echo esc_html( $search_query ); ?></span>
	</h1>
	<?php if ( $query->found_posts ) : ?>
	<p class="nsw-search-count"><?php echo $query->found_posts; ?> game<?php echo $query->found_posts > 1 ? 's' : ''; ?> found</p>
	<?php endif; ?>
	<?php endif; ?>

	<?php if ( $query->have_posts() ) : ?>
	<div class="nsw-rom-grid">
		<?php foreach ( $query->posts as $post ) : ?>
			<?php nswpedia_rom_card( $post->ID ); ?>
		<?php endforeach; ?>
	</div>
	<?php echo '<div class="nsw-pagination">' . paginate_links( array(
		'total'   => $query->max_num_pages,
		'current' => $paged,
	) ) . '</div>'; ?>
	<?php else : ?>
	<p class="nsw-no-results">No games found for <strong><?php echo esc_html( $search_query ); ?></strong>. Try a different search term.</p>
	<?php endif; ?>
</div>
<?php
wp_reset_postdata();
get_footer();
